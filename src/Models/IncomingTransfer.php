<?php

declare(strict_types=1);

namespace PayZephyr\VirtualAccounts\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Incoming Transfer Model
 *
 * Represents an incoming transfer/deposit to a virtual account.
 *
 * @property int $id
 * @property string $idempotency_key
 * @property string $transaction_reference
 * @property string $provider_reference
 * @property string $account_number
 * @property float|string $amount
 * @property string $currency
 * @property string $sender_name
 * @property string|null $sender_account
 * @property string|null $sender_bank
 * @property string|null $narration
 * @property string|null $session_id
 * @property string $provider
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $settled_at
 * @property array|null $metadata
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class IncomingTransfer extends Model
{
    protected $table = 'incoming_transfers';

    protected $fillable = [
        'idempotency_key',
        'transaction_reference',
        'provider_reference',
        'account_number',
        'amount',
        'currency',
        'sender_name',
        'sender_account',
        'sender_bank',
        'narration',
        'session_id',
        'provider',
        'status',
        'settled_at',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'metadata' => 'array',
        'settled_at' => 'datetime',
    ];

    /**
     * Get associated virtual account.
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(VirtualAccount::class, 'account_number', 'account_number');
    }

    /**
     * Scope to get confirmed transfers.
     */
    public function scopeConfirmed(Builder $query): Builder
    {
        return $query->where('status', 'confirmed');
    }

    /**
     * Scope to filter by provider.
     */
    public function scopeProvider(Builder $query, string $provider): Builder
    {
        return $query->where('provider', $provider);
    }

    /**
     * Scope to get pending transfers older than given hours.
     */
    public function scopeStalePending(Builder $query, int $hours = 24): Builder
    {
        return $query->where('status', 'pending')
            ->where('created_at', '<', now()->subHours($hours));
    }

    /**
     * Check if transfer is confirmed.
     */
    public function isConfirmed(): bool
    {
        return $this->status === 'confirmed';
    }

    /**
     * Mark transfer as confirmed.
     */
    public function markConfirmed(): bool
    {
        return $this->update([
            'status' => 'confirmed',
            'settled_at' => $this->settled_at ?? now(),
        ]);
    }
}
