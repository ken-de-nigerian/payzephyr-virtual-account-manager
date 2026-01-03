<?php

declare(strict_types=1);

namespace PayZephyr\VirtualAccounts\DataObjects;

/**
 * Virtual Account Data Transfer Object
 *
 * Immutable DTO representing a virtual account from any provider.
 */
final readonly class VirtualAccountDTO
{
    /**
     * @param  array<string, mixed>  $metadata  Additional provider-specific data
     */
    public function __construct(
        public string $accountNumber,
        public string $accountName,
        public string $bankName,
        public string $bankCode,
        public string $providerReference,
        public string $provider,
        public string $currency,
        public ?string $customerId = null,
        public array $metadata = [],
    ) {}

    /**
     * Create DTO from array data.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            accountNumber: (string) ($data['account_number'] ?? $data['accountNumber'] ?? ''),
            accountName: (string) ($data['account_name'] ?? $data['accountName'] ?? ''),
            bankName: (string) ($data['bank_name'] ?? $data['bankName'] ?? ''),
            bankCode: (string) ($data['bank_code'] ?? $data['bankCode'] ?? ''),
            providerReference: (string) ($data['provider_reference'] ?? $data['providerReference'] ?? ''),
            provider: (string) ($data['provider'] ?? ''),
            currency: (string) ($data['currency'] ?? 'NGN'),
            customerId: isset($data['customer_id']) ? (string) $data['customer_id'] : null,
            metadata: $data['metadata'] ?? [],
        );
    }

    /**
     * Convert to array representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'account_number' => $this->accountNumber,
            'account_name' => $this->accountName,
            'bank_name' => $this->bankName,
            'bank_code' => $this->bankCode,
            'provider_reference' => $this->providerReference,
            'provider' => $this->provider,
            'currency' => $this->currency,
            'customer_id' => $this->customerId,
            'metadata' => $this->metadata,
        ];
    }
}
