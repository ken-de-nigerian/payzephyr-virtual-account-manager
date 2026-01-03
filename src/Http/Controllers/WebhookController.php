<?php

declare(strict_types=1);

namespace PayZephyr\VirtualAccounts\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use PayZephyr\VirtualAccounts\Jobs\ProcessIncomingTransfer;
use PayZephyr\VirtualAccounts\Models\ProviderWebhookLog;
use PayZephyr\VirtualAccounts\Services\VirtualAccountManager;
use Throwable;

/**
 * Webhook Controller
 *
 * Handles incoming webhooks from virtual account providers.
 */
final class WebhookController extends Controller
{
    public function __construct(
        protected VirtualAccountManager $manager
    ) {}

    /**
     * Handle provider webhook.
     */
    public function handle(Request $request, string $provider): JsonResponse
    {
        try {
            // Log raw webhook immediately
            $webhookLog = $this->logWebhook($provider, $request);

            // Verify webhook signature
            $driver = $this->manager->driver($provider);

            if (! $driver->verifyWebhook($request)) {
                Log::warning('Webhook signature verification failed', [
                    'provider' => $provider,
                    'ip' => $request->ip(),
                ]);

                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid signature',
                ], 403);
            }

            // Dispatch to queue for processing
            ProcessIncomingTransfer::dispatch($provider, $request->all(), $webhookLog->id);

            Log::info('Webhook queued for processing', [
                'provider' => $provider,
                'log_id' => $webhookLog->id,
            ]);

            return response()->json([
                'status' => 'accepted',
                'message' => 'Webhook received and queued',
            ], 202);

        } catch (Throwable $e) {
            Log::error('Webhook processing failed', [
                'provider' => $provider,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Webhook processing failed',
            ], 500);
        }
    }

    /**
     * Log webhook for audit trail.
     */
    protected function logWebhook(string $provider, Request $request): ProviderWebhookLog
    {
        $payload = $request->all();

        // Extract event type (provider-specific)
        $eventType = $payload['event']
            ?? $payload['eventType']
            ?? $payload['event_type']
            ?? 'unknown';

        // Extract transaction reference if available
        $transactionRef = $payload['data']['tx_ref']
            ?? $payload['data']['reference']
            ?? $payload['data']['transaction_reference']
            ?? null;

        return ProviderWebhookLog::create([
            'provider' => $provider,
            'event_type' => $eventType,
            'payload' => $payload,
            'transaction_reference' => $transactionRef,
            'processed' => false,
        ]);
    }
}
