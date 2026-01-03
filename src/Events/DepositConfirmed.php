<?php

declare(strict_types=1);

// Events/DepositConfirmed.php

namespace PayZephyr\VirtualAccounts\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use PayZephyr\VirtualAccounts\Models\IncomingTransfer;
use PayZephyr\VirtualAccounts\Models\VirtualAccount;

/**
 * Deposit Confirmed Event
 *
 * Dispatched when an incoming transfer is confirmed and persisted.
 * Applications should listen to this event to credit user wallets, etc.
 */
final class DepositConfirmed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly IncomingTransfer $transfer
    ) {}

    /**
     * Get customer ID from virtual account.
     */
    public function getCustomerId(): ?string
    {
        $account = VirtualAccount::where('account_number', $this->transfer->account_number)->first();

        return $account?->customer_id;
    }

    /**
     * Get transfer amount.
     */
    public function getAmount(): float
    {
        return (float) $this->transfer->amount;
    }

    /**
     * Get transfer currency.
     */
    public function getCurrency(): string
    {
        return $this->transfer->currency;
    }
}
