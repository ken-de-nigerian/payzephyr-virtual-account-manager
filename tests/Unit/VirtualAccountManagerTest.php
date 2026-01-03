<?php

declare(strict_types=1);

namespace PayZephyr\VirtualAccounts\Tests\Unit;

use GuzzleHttp\Psr7\Response;
use PayZephyr\VirtualAccounts\DataObjects\AccountAssignmentDTO;
use PayZephyr\VirtualAccounts\Exceptions\DriverNotFoundException;
use PayZephyr\VirtualAccounts\Models\VirtualAccount;
use PayZephyr\VirtualAccounts\Tests\TestCase;

class VirtualAccountManagerTest extends TestCase
{
    public function test_can_get_driver(): void
    {
        $manager = app(\PayZephyr\VirtualAccounts\Services\VirtualAccountManager::class);

        $driver = $manager->driver('flutterwave');

        $this->assertInstanceOf(\PayZephyr\VirtualAccounts\Contracts\VirtualAccountProvider::class, $driver);
        $this->assertEquals('flutterwave', $driver->getName());
    }

    public function test_throws_exception_for_disabled_provider(): void
    {
        config(['virtual-accounts.providers.flutterwave.enabled' => false]);

        $manager = app(\PayZephyr\VirtualAccounts\Services\VirtualAccountManager::class);

        $this->expectException(DriverNotFoundException::class);
        $manager->driver('flutterwave');
    }

    public function test_uses_default_provider_when_none_specified(): void
    {
        config(['virtual-accounts.default' => 'flutterwave']);

        $manager = app(\PayZephyr\VirtualAccounts\Services\VirtualAccountManager::class);

        $driver = $manager->driver();

        $this->assertEquals('flutterwave', $driver->getName());
    }

    public function test_can_assign_account_to_customer(): void
    {
        $response = new Response(200, [], json_encode([
            'status' => 'success',
            'data' => [
                'account_number' => '1234567890',
                'account_name' => 'John Doe',
                'bank_name' => 'Wema Bank',
                'bank_code' => '035',
                'flw_ref' => 'FLW_REF_123',
            ],
        ]));

        $manager = $this->setupMockedProvider('flutterwave', [$response]);

        $assignment = new AccountAssignmentDTO(
            customerId: 'customer_123',
            customerName: 'John Doe',
            customerEmail: 'john@example.com',
            customerPhone: '+2348012345678',
            currency: 'NGN',
        );

        $account = $manager->assignAccount($assignment, 'flutterwave');

        $this->assertNotNull($account);
        $this->assertEquals('1234567890', $account->accountNumber);
        $this->assertEquals('customer_123', $account->customerId);

        // Verify account was saved to database
        $this->assertDatabaseHas('virtual_accounts', [
            'customer_id' => 'customer_123',
            'account_number' => '1234567890',
            'provider' => 'flutterwave',
        ]);
    }

    public function test_returns_existing_account_if_already_assigned(): void
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

        $manager = app(\PayZephyr\VirtualAccounts\Services\VirtualAccountManager::class);

        $assignment = new AccountAssignmentDTO(
            customerId: 'customer_123',
            customerName: 'John Doe',
            customerEmail: 'john@example.com',
            currency: 'NGN',
        );

        $account = $manager->assignAccount($assignment, 'flutterwave');

        $this->assertEquals('1234567890', $account->accountNumber);
        $this->assertEquals(1, VirtualAccount::where('customer_id', 'customer_123')->count());
    }

    public function test_can_get_account_by_customer_id(): void
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

        $manager = app(\PayZephyr\VirtualAccounts\Services\VirtualAccountManager::class);

        $account = $manager->getAccount('customer_123');

        $this->assertNotNull($account);
        $this->assertEquals('1234567890', $account->accountNumber);
    }

    public function test_returns_null_when_account_not_found(): void
    {
        $manager = app(\PayZephyr\VirtualAccounts\Services\VirtualAccountManager::class);

        $account = $manager->getAccount('nonexistent');

        $this->assertNull($account);
    }

    public function test_can_deactivate_account(): void
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

        $manager = app(\PayZephyr\VirtualAccounts\Services\VirtualAccountManager::class);

        $result = $manager->deactivateAccount('1234567890');

        $this->assertTrue($result);
        $this->assertDatabaseHas('virtual_accounts', [
            'account_number' => '1234567890',
            'status' => 'inactive',
        ]);
    }

    public function test_can_get_enabled_providers(): void
    {
        config([
            'virtual-accounts.providers.flutterwave.enabled' => true,
            'virtual-accounts.providers.monipoint.enabled' => false,
        ]);

        $manager = app(\PayZephyr\VirtualAccounts\Services\VirtualAccountManager::class);

        $providers = $manager->getEnabledProviders();

        $this->assertContains('flutterwave', $providers);
        $this->assertNotContains('monipoint', $providers);
    }

    public function test_fluent_api_builder(): void
    {
        $response = new Response(200, [], json_encode([
            'status' => 'success',
            'data' => [
                'account_number' => '1234567890',
                'account_name' => 'John Doe',
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
    }
}

