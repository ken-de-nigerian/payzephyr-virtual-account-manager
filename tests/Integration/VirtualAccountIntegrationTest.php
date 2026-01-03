<?php

declare(strict_types=1);

namespace PayZephyr\VirtualAccounts\Tests\Integration;

use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Event;
use PayZephyr\VirtualAccounts\Events\DepositConfirmed;
use PayZephyr\VirtualAccounts\Events\VirtualAccountCreated;
use PayZephyr\VirtualAccounts\Facades\VirtualAccounts;
use PayZephyr\VirtualAccounts\Tests\TestCase;

class VirtualAccountIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Event::fake();
    }

    public function test_complete_virtual_account_workflow(): void
    {
        // Step 1: Create virtual account
        $response = new Response(200, [], json_encode([
            'status' => 'success',
            'data' => [
                'account_number' => '1234567890',
                'note' => 'John Doe',
                'bank_name' => 'Wema Bank',
                'bank_code' => '035',
                'flw_ref' => 'FLW_REF_123',
            ],
        ]));

        $manager = $this->setupMockedProvider('flutterwave', [$response]);

        $account = $manager->assignTo('customer_123')
            ->name('John Doe')
            ->email('john@example.com')
            ->phone('+2348012345678')
            ->currency('NGN')
            ->using('flutterwave')
            ->create();

        $this->assertNotNull($account);
        $this->assertEquals('1234567890', $account->accountNumber);

        // Verify account was saved
        $this->assertDatabaseHas('virtual_accounts', [
            'customer_id' => 'customer_123',
            'account_number' => '1234567890',
        ]);

        // Verify event was dispatched
        Event::assertDispatched(VirtualAccountCreated::class);

        // Step 2: Retrieve account
        $retrieved = $manager->getAccount('customer_123');
        $this->assertNotNull($retrieved);
        $this->assertEquals('1234567890', $retrieved->accountNumber);

        // Step 3: Simulate incoming transfer via webhook
        // The webhook secret is already set in TestCase setUp, so we should use that
        // But we need to ensure the driver gets the updated config
        // Update config and recreate manager to pick up webhook secret
        $this->app['config']->set('virtual-accounts.providers.flutterwave.webhook_secret', 'test_secret');

        // Forget all instances to force recreation with new config
        $this->app->forgetInstance('virtual-accounts.config');
        $this->app->forgetInstance(\PayZephyr\VirtualAccounts\Services\VirtualAccountManager::class);
        $this->app->forgetInstance(\PayZephyr\VirtualAccounts\Services\DriverFactory::class);

        // Clear any cached drivers
        $reflection = new \ReflectionClass($this->app->make(\PayZephyr\VirtualAccounts\Services\VirtualAccountManager::class));
        $driversProperty = $reflection->getProperty('drivers');
        $driversProperty->setAccessible(true);
        $driversProperty->setValue($this->app->make(\PayZephyr\VirtualAccounts\Services\VirtualAccountManager::class), []);

        $response = $this->postJson('/virtual-accounts/webhook/flutterwave', [
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
        ], [
            'verif-hash' => 'test_secret',
        ]);

        $response->assertStatus(202);

        // Process the job synchronously
        \Illuminate\Support\Facades\Queue::fake();

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

        // Verify transfer was recorded
        $this->assertDatabaseHas('incoming_transfers', [
            'transaction_reference' => 'TX_REF_123',
            'account_number' => '1234567890',
            'amount' => 5000.00,
            'status' => 'confirmed',
        ]);

        // Verify event was dispatched
        Event::assertDispatched(DepositConfirmed::class, function ($event) {
            return $event->getAmount() === 5000.00
                && $event->getCustomerId() === 'customer_123';
        });
    }

    public function test_facade_works_correctly(): void
    {
        $response = new Response(200, [], json_encode([
            'status' => 'success',
            'data' => [
                'account_number' => '1234567890',
                'note' => 'John Doe',
                'bank_name' => 'Wema Bank',
                'bank_code' => '035',
                'flw_ref' => 'FLW_REF_123',
            ],
        ]));

        $this->setupMockedProvider('flutterwave', [$response]);

        $account = VirtualAccounts::assignTo('customer_123')
            ->name('John Doe')
            ->email('john@example.com')
            ->currency('NGN')
            ->using('flutterwave')
            ->create();

        $this->assertNotNull($account);
        $this->assertEquals('1234567890', $account->accountNumber);

        $retrieved = VirtualAccounts::getAccount('customer_123');
        $this->assertNotNull($retrieved);
    }

    public function test_multiple_providers_workflow(): void
    {
        // Test that we can switch between providers
        $flutterwaveResponse = new Response(200, [], json_encode([
            'status' => 'success',
            'data' => [
                'account_number' => '1234567890',
                'note' => 'John Doe',
                'bank_name' => 'Wema Bank',
                'bank_code' => '035',
                'flw_ref' => 'FLW_REF_123',
            ],
        ]));

        $manager = $this->setupMockedProvider('flutterwave', [$flutterwaveResponse]);

        $account1 = $manager->assignTo('customer_1')
            ->name('John Doe')
            ->email('john@example.com')
            ->currency('NGN')
            ->using('flutterwave')
            ->create();

        $this->assertEquals('flutterwave', $account1->provider);

        // Test default provider - need another mock response
        $flutterwaveResponse2 = new Response(200, [], json_encode([
            'status' => 'success',
            'data' => [
                'account_number' => '0987654321',
                'note' => 'Jane Doe',
                'bank_name' => 'GTBank',
                'bank_code' => '058',
                'flw_ref' => 'FLW_REF_456',
            ],
        ]));

        // Setup new mock for second account
        $manager2 = $this->setupMockedProvider('flutterwave', [$flutterwaveResponse2]);

        $account2 = $manager2->assignTo('customer_2')
            ->name('Jane Doe')
            ->email('jane@example.com')
            ->currency('NGN')
            ->create();

        $this->assertEquals('flutterwave', $account2->provider);
    }
}
