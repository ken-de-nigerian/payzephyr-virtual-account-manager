<?php

declare(strict_types=1);

namespace PayZephyr\VirtualAccounts\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Virtual Account Model
 *
 * Represents a virtual bank account created via a provider.
 *
 * @property int $id
 * @property string $customer_id
 * @property string $account_number
 * @property string $account_name
 * @property string $bank_name
 * @property string $bank_code
 * @property string $provider_reference
 * @property string $provider
 * @property string $currency
 * @property string $status
 * @property array|null $metadata
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class VirtualAccount extends Model
{
    protected $table = 'virtual_accounts';

    protected $fillable = [
        'customer_id',
        'account_number',
        'account_name',
        'bank_name',
        'bank_code',
        'provider_reference',
        'provider',
        'currency',
        'status',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    /**
     * Get incoming transfers for this account.
     */
    public function transfers(): HasMany
    {
        return $this->hasMany(IncomingTransfer::class, 'account_number', 'account_number');
    }

    /**
     * Scope to get active accounts.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope to filter by provider.
     */
    public function scopeProvider(Builder $query, string $provider): Builder
    {
        return $query->where('provider', $provider);
    }

    /**
     * Check if account is active.
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Deactivate account.
     */
    public function deactivate(): bool
    {
        return $this->update(['status' => 'inactive']);
    }
}
