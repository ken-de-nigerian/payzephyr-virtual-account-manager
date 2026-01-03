<?php

declare(strict_types=1);

namespace PayZephyr\VirtualAccounts\DataObjects;

use DateTimeImmutable;
use DateTimeInterface;
use Exception;

/**
 * Incoming Transfer Data Transfer Object
 *
 * Immutable DTO representing an incoming transfer from any provider.
 */
final readonly class IncomingTransferDTO
{
    /**
     * @param array<string, mixed> $metadata Additional provider-specific data
     */
    public function __construct(
        public string             $transactionReference,
        public string             $providerReference,
        public string             $accountNumber,
        public float              $amount,
        public string             $currency,
        public string             $senderName,
        public ?string            $senderAccount = null,
        public ?string            $senderBank = null,
        public ?string            $narration = null,
        public ?string            $sessionId = null,
        public ?DateTimeInterface $settledAt = null,
        public array              $metadata = [],
    ) {}

    /**
     * Get idempotency key for this transfer (SHA256 hash).
     *
     * Uses transaction reference + account number + amount as basis.
     */
    public function getIdempotencyKey(): string
    {
        $key = sprintf(
            '%s:%s:%.2f:%s',
            $this->transactionReference,
            $this->accountNumber,
            $this->amount,
            $this->currency
        );

        return hash('sha256', $key);
    }

    /**
     * Create DTO from array data.
     *
     * @param array<string, mixed> $data
     * @throws Exception
     */
    public static function fromArray(array $data): self
    {
        $settledAt = null;
        if (isset($data['settled_at'])) {
            $settledAt = is_string($data['settled_at'])
                ? new DateTimeImmutable($data['settled_at'])
                : $data['settled_at'];
        }

        return new self(
            transactionReference: (string) ($data['transaction_reference'] ?? $data['transactionReference'] ?? ''),
            providerReference: (string) ($data['provider_reference'] ?? $data['providerReference'] ?? ''),
            accountNumber: (string) ($data['account_number'] ?? $data['accountNumber'] ?? ''),
            amount: (float) ($data['amount'] ?? 0.0),
            currency: (string) ($data['currency'] ?? 'NGN'),
            senderName: (string) ($data['sender_name'] ?? $data['senderName'] ?? ''),
            senderAccount: isset($data['sender_account']) || isset($data['senderAccount'])
                ? (string) ($data['sender_account'] ?? $data['senderAccount'])
                : null,
            senderBank: isset($data['sender_bank']) || isset($data['senderBank'])
                ? (string) ($data['sender_bank'] ?? $data['senderBank'])
                : null,
            narration: isset($data['narration']) ? (string) $data['narration'] : null,
            sessionId: isset($data['session_id']) || isset($data['sessionId'])
                ? (string) ($data['session_id'] ?? $data['sessionId'])
                : null,
            settledAt: $settledAt,
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
            'transaction_reference' => $this->transactionReference,
            'provider_reference' => $this->providerReference,
            'account_number' => $this->accountNumber,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'sender_name' => $this->senderName,
            'sender_account' => $this->senderAccount,
            'sender_bank' => $this->senderBank,
            'narration' => $this->narration,
            'session_id' => $this->sessionId,
            'settled_at' => $this->settledAt?->format('Y-m-d H:i:s'),
            'metadata' => $this->metadata,
        ];
    }
}

