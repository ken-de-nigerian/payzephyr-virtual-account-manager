<?php

declare(strict_types=1);

namespace PayZephyr\VirtualAccounts\Tests\Feature;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use PayZephyr\VirtualAccounts\Events\DepositConfirmed;
use PayZephyr\VirtualAccounts\Models\IncomingTransfer;
use PayZephyr\VirtualAccounts\Models\VirtualAccount;
use PayZephyr\VirtualAccounts\Tests\TestCase;

class WebhookTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Event::fake();
    }

    public function test_can_receive_webhook(): void
    {
        VirtualAccount::create([
            'customer_id' => 'customer_123',
            'account_number' => '1234567890',
            'account_name' => 'John Doe',
            'bank_name' => 'Wema Bank',
            'bank_code' => '035',
            'provider_reference' => 'FLW_REF_123',
            'provider' => 'flutterwave',
            'currency' => 'NGN',
            'status' => 'active',
        ]);

        config(['virtual-accounts.providers.flutterwave.webhook_secret' => 'test_secret']);

        $response = $this->postJson('/virtual-accounts/webhook/flutterwave', [
            'event' => 'charge.completed',
            'data' => [
                'tx_ref' => 'TX_REF_123',
                'flw_ref' => 'FLW_REF_123',
                'account_number' => '1234567890',
                'amount' => 5000.00,
                'currency' => 'NGN',
                'customer' => [
                    'name' => 'John Doe',
                ],
                'created_at' => '2024-01-01T00:00:00Z',
            ],
        ], [
            'verif-hash' => 'test_secret',
        ]);

        $response->assertStatus(202);

        // Verify webhook was logged
        $this->assertDatabaseHas('provider_webhook_logs', [
            'provider' => 'flutterwave',
        ]);

        // Verify job was dispatched
        Queue::assertPushed(\PayZephyr\VirtualAccounts\Jobs\ProcessIncomingTransfer::class);
    }

    public function test_rejects_webhook_with_invalid_signature(): void
    {
        config(['virtual-accounts.providers.flutterwave.webhook_secret' => 'test_secret']);

        $response = $this->postJson('/virtual-accounts/webhook/flutterwave', [
            'event' => 'charge.completed',
            'data' => [],
        ], [
            'verif-hash' => 'wrong_secret',
        ]);

        $response->assertStatus(403);
    }

    public function test_processes_incoming_transfer(): void
    {
        VirtualAccount::create([
            'customer_id' => 'customer_123',
            'account_number' => '1234567890',
            'account_name' => 'John Doe',
            'bank_name' => 'Wema Bank',
            'bank_code' => '035',
            'provider_reference' => 'FLW_REF_123',
            'provider' => 'flutterwave',
            'currency' => 'NGN',
            'status' => 'active',
        ]);

        // Create webhook log
        $webhookLog = \PayZephyr\VirtualAccounts\Models\ProviderWebhookLog::create([
            'provider' => 'flutterwave',
            'event_type' => 'charge.completed',
            'payload' => [
                'event' => 'charge.completed',
                'data' => [
                    'tx_ref' => 'TX_REF_123',
                    'flw_ref' => 'FLW_REF_123',
                    'account_number' => '1234567890',
                    'amount' => 5000.00,
                    'currency' => 'NGN',
                    'customer' => ['name' => 'John Doe'],
                    'created_at' => '2024-01-01T00:00:00Z',
                ],
            ],
            'processed' => false,
        ]);

        $job = new \PayZephyr\VirtualAccounts\Jobs\ProcessIncomingTransfer(
            'flutterwave',
            [
                'event' => 'charge.completed',
                'data' => [
                    'tx_ref' => 'TX_REF_123',
                    'flw_ref' => 'FLW_REF_123',
                    'account_number' => '1234567890',
                    'amount' => 5000.00,
                    'currency' => 'NGN',
                    'customer' => ['name' => 'John Doe'],
                    'created_at' => '2024-01-01T00:00:00Z',
                ],
            ],
            $webhookLog->id
        );

        $job->handle(app(\PayZephyr\VirtualAccounts\Services\DepositDetector::class));

        // Verify transfer was saved
        $this->assertDatabaseHas('incoming_transfers', [
            'transaction_reference' => 'TX_REF_123',
            'account_number' => '1234567890',
            'amount' => 5000.00,
        ]);

        // Verify event was dispatched
        Event::assertDispatched(DepositConfirmed::class, function ($event) {
            return $event->getAmount() === 5000.00
                && $event->getCustomerId() === 'customer_123';
        });
    }

    public function test_prevents_duplicate_transfers(): void
    {
        VirtualAccount::create([
            'customer_id' => 'customer_123',
            'account_number' => '1234567890',
            'account_name' => 'John Doe',
            'bank_name' => 'Wema Bank',
            'bank_code' => '035',
            'provider_reference' => 'FLW_REF_123',
            'provider' => 'flutterwave',
            'currency' => 'NGN',
            'status' => 'active',
        ]);

        // Create existing transfer with correct idempotency key
        // The idempotency key is calculated as: hash('sha256', 'TX_REF_123:1234567890:5000.00:NGN')
        $idempotencyKey = hash('sha256', 'TX_REF_123:1234567890:5000.00:NGN');

        IncomingTransfer::create([
            'transaction_reference' => 'TX_REF_123',
            'provider_reference' => 'FLW_REF_123',
            'account_number' => '1234567890',
            'amount' => 5000.00,
            'currency' => 'NGN',
            'sender_name' => 'John Doe',
            'provider' => 'flutterwave',
            'status' => 'confirmed',
            'idempotency_key' => $idempotencyKey,
        ]);

        // Create webhook log
        $webhookLog = \PayZephyr\VirtualAccounts\Models\ProviderWebhookLog::create([
            'provider' => 'flutterwave',
            'event_type' => 'charge.completed',
            'payload' => [
                'event' => 'charge.completed',
                'data' => [
                    'tx_ref' => 'TX_REF_123',
                    'flw_ref' => 'FLW_REF_123',
                    'account_number' => '1234567890',
                    'amount' => 5000.00,
                    'currency' => 'NGN',
                    'customer' => ['name' => 'John Doe'],
                ],
            ],
            'processed' => false,
        ]);

        $job = new \PayZephyr\VirtualAccounts\Jobs\ProcessIncomingTransfer(
            'flutterwave',
            [
                'event' => 'charge.completed',
                'data' => [
                    'tx_ref' => 'TX_REF_123',
                    'flw_ref' => 'FLW_REF_123',
                    'account_number' => '1234567890',
                    'amount' => 5000.00,
                    'currency' => 'NGN',
                    'customer' => ['name' => 'John Doe'],
                ],
            ],
            $webhookLog->id
        );

        $job->handle(app(\PayZephyr\VirtualAccounts\Services\DepositDetector::class));

        // Verify only one transfer exists
        $this->assertEquals(1, IncomingTransfer::where('transaction_reference', 'TX_REF_123')->count());
    }
}
