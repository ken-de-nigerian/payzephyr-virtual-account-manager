<?php

declare(strict_types=1);

namespace PayZephyr\VirtualAccounts\Tests\Fixtures;

use Illuminate\Http\Request;
use PayZephyr\VirtualAccounts\Contracts\VirtualAccountProvider;
use PayZephyr\VirtualAccounts\DataObjects\IncomingTransferDTO;
use PayZephyr\VirtualAccounts\DataObjects\VirtualAccountDTO;
use PayZephyr\VirtualAccounts\Drivers\AbstractDriver;
use PayZephyr\VirtualAccounts\Exceptions\InvalidConfigurationException;

class CustomTestDriver extends AbstractDriver implements VirtualAccountProvider
{
    protected string $name = 'custom';

    protected function validateConfig(): void
    {
        if (empty($this->config['api_key'])) {
            throw new InvalidConfigurationException('API key is required');
        }
    }

    protected function getDefaultHeaders(): array
    {
        return array_merge(parent::getDefaultHeaders(), [
            'Authorization' => 'Bearer '.$this->config['api_key'],
        ]);
    }

    public function createAccount(array $payload): VirtualAccountDTO
    {
        return VirtualAccountDTO::fromArray([
            'account_number' => '1234567890',
            'account_name' => $payload['customer_name'] ?? 'Test Account',
            'bank_name' => 'Test Bank',
            'bank_code' => '001',
            'provider_reference' => 'CUSTOM_REF_123',
            'provider' => $this->getName(),
            'currency' => $payload['currency'] ?? 'NGN',
            'metadata' => [],
        ]);
    }

    public function verifyWebhook(Request $request): bool
    {
        return true;
    }

    public function parseIncomingTransfer(Request $request): IncomingTransferDTO
    {
        $data = $request->all();

        return IncomingTransferDTO::fromArray([
            'transaction_reference' => $data['reference'] ?? 'CUSTOM_TX_123',
            'provider_reference' => $data['provider_ref'] ?? 'CUSTOM_REF_123',
            'account_number' => $data['account_number'] ?? '1234567890',
            'amount' => (float) ($data['amount'] ?? 0),
            'currency' => $data['currency'] ?? 'NGN',
            'sender_name' => $data['sender_name'] ?? 'Test Sender',
            'metadata' => $data,
        ]);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function healthCheck(): bool
    {
        return true;
    }

    public function getSupportedCurrencies(): array
    {
        return ['NGN', 'USD'];
    }

    public function fetchAccount(string $accountReference): VirtualAccountDTO
    {
        return $this->createAccount(['customer_name' => 'Test']);
    }

    public function getBalance(string $accountReference): ?float
    {
        return null;
    }
}
