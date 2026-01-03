<?php

declare(strict_types=1);

namespace PayZephyr\VirtualAccounts\Drivers;

use Illuminate\Http\Request;
use PayZephyr\VirtualAccounts\DataObjects\IncomingTransferDTO;
use PayZephyr\VirtualAccounts\DataObjects\VirtualAccountDTO;
use PayZephyr\VirtualAccounts\Exceptions\InvalidConfigurationException;
use PayZephyr\VirtualAccounts\Exceptions\VirtualAccountException;

/**
 * Moniepoint (Monnify) Driver Implementation (Stub)
 *
 * Stub implementation for Moniepoint virtual accounts.
 * TODO: Implement full API integration when provider credentials are available.
 */
final class MoniepointDriver extends AbstractDriver
{
    protected string $name = 'monipoint';

    /**
     * Validate Moniepoint configuration.
     */
    protected function validateConfig(): void
    {
        if (empty($this->config['api_key']) || empty($this->config['secret_key'])) {
            throw new InvalidConfigurationException(
                'Moniepoint API key and secret key are required'
            );
        }
    }

    /**
     * Get default headers with Moniepoint authentication.
     *
     * @return array<string, string>
     */
    protected function getDefaultHeaders(): array
    {
        return array_merge(parent::getDefaultHeaders(), [
            'Authorization' => 'Basic ' . base64_encode($this->config['api_key'] . ':' . $this->config['secret_key']),
        ]);
    }

    /**
     * Create virtual account with Moniepoint (stub).
     *
     * @param array<string, mixed> $payload
     */
    public function createAccount(array $payload): VirtualAccountDTO
    {
        // TODO: Implement Moniepoint API integration
        throw VirtualAccountException::providerError(
            $this->getName(),
            'Moniepoint driver is not yet implemented. Please use Flutterwave or implement this driver.'
        );
    }

    /**
     * Verify Moniepoint webhook signature (stub).
     */
    public function verifyWebhook(Request $request): bool
    {
        // TODO: Implement Moniepoint webhook signature verification
        // Moniepoint typically uses HMAC SHA512 with request body
        return true; // Stub - always return true for now
    }

    /**
     * Parse incoming transfer from Moniepoint webhook (stub).
     */
    public function parseIncomingTransfer(Request $request): IncomingTransferDTO
    {
        // TODO: Implement Moniepoint webhook parsing
        throw VirtualAccountException::providerError(
            $this->getName(),
            'Moniepoint webhook parsing is not yet implemented'
        );
    }

    /**
     * Get provider name.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get supported currencies for Moniepoint.
     *
     * @return array<int, string>
     */
    public function getSupportedCurrencies(): array
    {
        return ['NGN'];
    }

    /**
     * Fetch account details from Moniepoint (stub).
     */
    public function fetchAccount(string $accountReference): VirtualAccountDTO
    {
        throw VirtualAccountException::providerError(
            $this->getName(),
            'Moniepoint fetch account is not yet implemented'
        );
    }

    /**
     * Get account balance from Moniepoint (stub).
     */
    public function getBalance(string $accountReference): ?float
    {
        // TODO: Implement if Moniepoint API supports balance queries
        return null;
    }

    /**
     * Health check for Moniepoint API (stub).
     */
    public function healthCheck(): bool
    {
        // TODO: Implement Moniepoint health check
        return false;
    }
}

