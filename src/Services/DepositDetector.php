<?php

declare(strict_types=1);

namespace PayZephyr\VirtualAccounts\Services;

use Illuminate\Http\Request;
use PayZephyr\VirtualAccounts\DataObjects\IncomingTransferDTO;
use PayZephyr\VirtualAccounts\Exceptions\WebhookParseException;
use Throwable;

/**
 * Deposit Detector Service
 *
 * Parses and normalizes incoming transfer data from webhooks.
 */
final class DepositDetector
{
    public function __construct(
        protected VirtualAccountManager $manager
    ) {}

    /**
     * Parse transfer from webhook payload.
     *
     * @param  array<string, mixed>  $payload
     *
     * @throws WebhookParseException
     */
    public function parseTransfer(string $provider, array $payload): IncomingTransferDTO
    {
        try {
            $driver = $this->manager->driver($provider);

            // Create mock request with payload
            $request = Request::create('/', 'POST', $payload);

            return $driver->parseIncomingTransfer($request);

        } catch (Throwable $e) {
            throw new WebhookParseException(
                "Failed to parse transfer from $provider: ".$e->getMessage(),
                0,
                $e
            );
        }
    }
}
