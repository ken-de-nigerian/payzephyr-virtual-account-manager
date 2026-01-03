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
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeConfirmed(Builder $query): Builder
    {
        return $query->where('status', 'confirmed');
    }

    /**
     * Scope to filter by provider.
     *
     * @param Builder $query
     * @param string $provider
     * @return Builder
     */
    public function scopeProvider(Builder $query, string $provider): Builder
    {
        return $query->where('provider', $provider);
    }

    /**
     * Scope to get pending transfers older than given hours.
     *
     * @param Builder $query
     * @param int $hours
     * @return Builder
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

