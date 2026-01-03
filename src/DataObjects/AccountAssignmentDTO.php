<?php

declare(strict_types=1);

namespace PayZephyr\VirtualAccounts\DataObjects;

/**
 * Account Assignment Data Transfer Object
 *
 * Immutable DTO for creating a new virtual account assignment.
 */
final readonly class AccountAssignmentDTO
{
    /**
     * @param  array<string, mixed>  $metadata  Additional customer/account data
     */
    public function __construct(
        public string $customerId,
        public string $customerName,
        public string $customerEmail,
        public ?string $customerPhone = null,
        public ?string $bvn = null,
        public string $currency = 'NGN',
        public ?string $preferredBank = null,
        public array $metadata = [],
    ) {}

    /**
     * Convert to array for provider API payload.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'customer_id' => $this->customerId,
            'customer_name' => $this->customerName,
            'customer_email' => $this->customerEmail,
            'customer_phone' => $this->customerPhone,
            'bvn' => $this->bvn,
            'currency' => $this->currency,
            'preferred_bank' => $this->preferredBank,
            'metadata' => $this->metadata,
        ];
    }
}
