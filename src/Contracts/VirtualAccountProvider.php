<?php

declare(strict_types=1);

namespace PayZephyr\VirtualAccounts\Contracts;

use Illuminate\Http\Request;
use PayZephyr\VirtualAccounts\DataObjects\IncomingTransferDTO;
use PayZephyr\VirtualAccounts\DataObjects\VirtualAccountDTO;
use PayZephyr\VirtualAccounts\Exceptions\VirtualAccountException;
use PayZephyr\VirtualAccounts\Exceptions\WebhookParseException;

/**
 * Contract defining the interface for virtual account providers.
 *
 * This interface ensures all provider drivers implement a consistent API
 * for creating accounts, verifying webhooks, and parsing incoming transfers.
 */
interface VirtualAccountProvider
{
    /**
     * Create a virtual account with the provider.
     *
     * @param  array<string, mixed>  $payload  Customer and account creation data
     * @return VirtualAccountDTO Normalized virtual account details
     *
     * @throws VirtualAccountException
     */
    public function createAccount(array $payload): VirtualAccountDTO;

    /**
     * Verify webhook authenticity from the provider.
     *
     * @param  Request  $request  Incoming webhook request
     * @return bool True if webhook is valid, false otherwise
     */
    public function verifyWebhook(Request $request): bool;

    /**
     * Parse incoming transfer data from webhook payload.
     *
     * @param  Request  $request  Webhook request containing transfer data
     * @return IncomingTransferDTO Normalized transfer details
     *
     * @throws WebhookParseException
     */
    public function parseIncomingTransfer(Request $request): IncomingTransferDTO;

    /**
     * Get provider name identifier.
     *
     * @return string Provider name (e.g., 'flutterwave', 'monipoint')
     */
    public function getName(): string;

    /**
     * Check provider health/connectivity.
     *
     * @return bool True if provider is reachable and operational
     */
    public function healthCheck(): bool;

    /**
     * Get supported currencies for this provider.
     *
     * @return array<int, string> Array of ISO currency codes
     */
    public function getSupportedCurrencies(): array;

    /**
     * Fetch account details from provider (optional reconciliation support).
     *
     * @param  string  $accountReference  Provider's account reference
     * @return VirtualAccountDTO Current account state from provider
     *
     * @throws VirtualAccountException
     */
    public function fetchAccount(string $accountReference): VirtualAccountDTO;

    /**
     * Get account balance from provider (if supported).
     *
     * @param  string  $accountReference  Provider's account reference
     * @return float|null Current balance or null if not supported
     */
    public function getBalance(string $accountReference): ?float;

    /**
     * Check if the provider is working (cached result).
     *
     * @return bool True if provider is healthy
     */
    public function getCachedHealthCheck(): bool;

    /**
     * Check if this provider supports a specific currency.
     *
     * @param  string  $currency  Currency code (e.g., 'NGN', 'USD')
     * @return bool True if currency is supported
     */
    public function isCurrencySupported(string $currency): bool;
}
