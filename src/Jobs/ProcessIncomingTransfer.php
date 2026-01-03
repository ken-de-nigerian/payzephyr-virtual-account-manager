<?php

declare(strict_types=1);

namespace PayZephyr\VirtualAccounts\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PayZephyr\VirtualAccounts\Events\DepositConfirmed;
use PayZephyr\VirtualAccounts\Exceptions\WebhookParseException;
use PayZephyr\VirtualAccounts\Models\IncomingTransfer;
use PayZephyr\VirtualAccounts\Models\ProviderWebhookLog;
use PayZephyr\VirtualAccounts\Services\DepositDetector;
use Throwable;

/**
 * Job to process incoming transfer from webhook.
 */
final class ProcessIncomingTransfer implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        protected string $provider,
        protected array $payload,
        protected int $webhookLogId,
    ) {}

    /**
     * @throws Throwable
     * @throws WebhookParseException
     */
    public function handle(DepositDetector $detector): void
    {
        $webhookLog = ProviderWebhookLog::find($this->webhookLogId);

        if (!$webhookLog) {
            Log::error('Webhook log not found', ['id' => $this->webhookLogId]);
            return;
        }

        try {
            DB::transaction(function () use ($detector, $webhookLog) {
                // Parse transfer data
                $transferDTO = $detector->parseTransfer($this->provider, $this->payload);

                // Check for duplicate (idempotency)
                $existing = IncomingTransfer::where('idempotency_key', $transferDTO->getIdempotencyKey())
                    ->first();

                if ($existing) {
                    Log::info('Duplicate transfer detected (idempotent)', [
                        'idempotency_key' => $transferDTO->getIdempotencyKey(),
                        'transaction_reference' => $transferDTO->transactionReference,
                    ]);

                    $webhookLog->markProcessed();
                    return;
                }

                // Create transfer record
                $transfer = IncomingTransfer::create([
                    'idempotency_key' => $transferDTO->getIdempotencyKey(),
                    'transaction_reference' => $transferDTO->transactionReference,
                    'provider_reference' => $transferDTO->providerReference,
                    'account_number' => $transferDTO->accountNumber,
                    'amount' => $transferDTO->amount,
                    'currency' => $transferDTO->currency,
                    'sender_name' => $transferDTO->senderName,
                    'sender_account' => $transferDTO->senderAccount,
                    'sender_bank' => $transferDTO->senderBank,
                    'narration' => $transferDTO->narration,
                    'session_id' => $transferDTO->sessionId,
                    'provider' => $this->provider,
                    'status' => 'confirmed',
                    'settled_at' => $transferDTO->settledAt ?? now(),
                    'metadata' => $transferDTO->metadata,
                ]);

                // Mark webhook as processed
                $webhookLog->markProcessed();

                // Dispatch event
                DepositConfirmed::dispatch($transfer);

                Log::info('Incoming transfer processed', [
                    'transfer_id' => $transfer->id,
                    'account_number' => $transfer->account_number,
                    'amount' => $transfer->amount,
                    'provider' => $this->provider,
                ]);
            });

        } catch (Throwable $e) {
            $webhookLog->markFailed($e->getMessage());

            Log::error('Transfer processing failed', [
                'provider' => $this->provider,
                'webhook_log_id' => $this->webhookLogId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}