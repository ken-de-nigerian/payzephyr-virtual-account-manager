<?php

declare(strict_types=1);

namespace PayZephyr\VirtualAccounts\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Provider Webhook Log Model
 *
 * Audit trail for all webhooks received from providers.
 */
class ProviderWebhookLog extends Model
{
    protected $table = 'provider_webhook_logs';

    protected $fillable = [
        'provider',
        'event_type',
        'payload',
        'transaction_reference',
        'processed',
        'error_message',
    ];

    protected $casts = [
        'payload' => 'array',
        'processed' => 'boolean',
    ];

    /**
     * Scope to get unprocessed webhooks.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeUnprocessed(Builder $query): Builder
    {
        return $query->where('processed', false);
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
     * Mark webhook as processed.
     */
    public function markProcessed(): bool
    {
        return $this->update(['processed' => true]);
    }

    /**
     * Mark webhook as failed with error message.
     */
    public function markFailed(string $errorMessage): bool
    {
        return $this->update([
            'processed' => true,
            'error_message' => $errorMessage,
        ]);
    }

    /**
     * Check if webhook is processed.
     */
    public function isProcessed(): bool
    {
        return $this->processed === true;
    }
}

