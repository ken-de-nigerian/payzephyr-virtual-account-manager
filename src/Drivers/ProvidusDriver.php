<?php

declare(strict_types=1);

namespace PayZephyr\VirtualAccounts\Drivers;

use Illuminate\Http\Request;
use PayZephyr\VirtualAccounts\DataObjects\IncomingTransferDTO;
use PayZephyr\VirtualAccounts\DataObjects\VirtualAccountDTO;
use PayZephyr\VirtualAccounts\Exceptions\InvalidConfigurationException;
use PayZephyr\VirtualAccounts\Exceptions\VirtualAccountException;

/**
 * Providus Bank Driver Implementation (Stub)
 *
 * Stub implementation for Providus Bank virtual accounts.
 * TODO: Implement full API integration when provider credentials are available.
 */
final class ProvidusDriver extends AbstractDriver
{
    protected string $name = 'providus';

    /**
     * Validate Providus configuration.
     */
    protected function validateConfig(): void
    {
        if (empty($this->config['client_id']) || empty($this->config['client_secret'])) {
            throw new InvalidConfigurationException(
                'Providus client ID and client secret are required'
            );
        }
    }

    /**
     * Get default headers with Providus authentication.
     *
     * @return array<string, string>
     */
    protected function getDefaultHeaders(): array
    {
        // TODO: Implement OAuth2 token retrieval for Providus
        // Providus typically uses OAuth2 client credentials flow
        return array_merge(parent::getDefaultHeaders(), [
            // 'Authorization' => 'Bearer ' . $this->getAccessToken(),
        ]);
    }

    /**
     * Create virtual account with Providus (stub).
     *
     * @param array<string, mixed> $payload
     */
    public function createAccount(array $payload): VirtualAccountDTO
    {
        // TODO: Implement Providus API integration
        throw VirtualAccountException::providerError(
            $this->getName(),
            'Providus driver is not yet implemented. Please use Flutterwave or implement this driver.'
        );
    }

    /**
     * Verify Providus webhook signature (stub).
     */
    public function verifyWebhook(Request $request): bool
    {
        // TODO: Implement Providus webhook signature verification
        // Providus typically uses HMAC SHA256 or similar
        return true; // Stub - always return true for now
    }

    /**
     * Parse incoming transfer from Providus webhook (stub).
     */
    public function parseIncomingTransfer(Request $request): IncomingTransferDTO
    {
        // TODO: Implement Providus webhook parsing
        throw VirtualAccountException::providerError(
            $this->getName(),
            'Providus webhook parsing is not yet implemented'
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
     * Get supported currencies for Providus.
     *
     * @return array<int, string>
     */
    public function getSupportedCurrencies(): array
    {
        return ['NGN'];
    }

    /**
     * Fetch account details from Providus (stub).
     */
    public function fetchAccount(string $accountReference): VirtualAccountDTO
    {
        throw VirtualAccountException::providerError(
            $this->getName(),
            'Providus fetch account is not yet implemented'
        );
    }

    /**
     * Get account balance from Providus (stub).
     */
    public function getBalance(string $accountReference): ?float
    {
        // TODO: Implement if Providus API supports balance queries
        return null;
    }

    /**
     * Health check for Providus API (stub).
     */
    public function healthCheck(): bool
    {
        // TODO: Implement Providus health check
        return false;
    }
}

