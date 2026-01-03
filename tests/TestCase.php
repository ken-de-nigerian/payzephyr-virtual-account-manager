<?php

declare(strict_types=1);

namespace PayZephyr\VirtualAccounts\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase as Orchestra;
use PayZephyr\VirtualAccounts\VirtualAccountServiceProvider;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [
            VirtualAccountServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $app['config']->set('virtual-accounts.default', 'flutterwave');

        // Configure Flutterwave provider for testing
        $app['config']->set('virtual-accounts.providers.flutterwave', [
            'driver_class' => \PayZephyr\VirtualAccounts\Drivers\FlutterwaveDriver::class,
            'secret_key' => 'FLWSECK_TEST_xxx',
            'webhook_secret' => 'test_webhook_secret',
            'base_url' => 'https://api.flutterwave.com/v3',
            'enabled' => true,
        ]);

        $app['config']->set('virtual-accounts.providers.monipoint', [
            'driver_class' => \PayZephyr\VirtualAccounts\Drivers\MoniepointDriver::class,
            'api_key' => 'test_api_key',
            'secret_key' => 'test_secret_key',
            'contract_code' => 'test_contract',
            'base_url' => 'https://api.monnify.com',
            'enabled' => false,
        ]);

        $app['config']->set('virtual-accounts.providers.providus', [
            'driver_class' => \PayZephyr\VirtualAccounts\Drivers\ProvidusDriver::class,
            'client_id' => 'test_client_id',
            'client_secret' => 'test_client_secret',
            'base_url' => 'https://api.providusbank.com',
            'enabled' => false,
        ]);

        // Disable health checks in tests
        $app['config']->set('virtual-accounts.health_check.enabled', false);

        // Enable logging for tests
        $app['config']->set('virtual-accounts.logging.enabled', true);
        $app['config']->set('virtual-accounts.logging.channel', 'stack');
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->artisan('migrate', ['--database' => 'testing'])->run();
    }

    /**
     * Helper method to set up mocked HTTP client for a provider.
     */
    protected function setupMockedProvider(string $provider, array $responses): \PayZephyr\VirtualAccounts\Services\VirtualAccountManager
    {
        // Disable health checks for testing
        config(['virtual-accounts.health_check.enabled' => false]);

        // Ensure provider is enabled
        config(["virtual-accounts.providers.{$provider}.enabled" => true]);

        // Forget singleton instances
        $this->app->forgetInstance('virtual-accounts.config');
        $this->app->forgetInstance(\PayZephyr\VirtualAccounts\Services\VirtualAccountManager::class);
        $this->app->forgetInstance(\PayZephyr\VirtualAccounts\Services\DriverFactory::class);

        // Get fresh manager
        $manager = app(\PayZephyr\VirtualAccounts\Services\VirtualAccountManager::class);

        // Get the driver
        $driver = $manager->driver($provider);

        // Mock HTTP client
        $mock = new \GuzzleHttp\Handler\MockHandler($responses);
        $handlerStack = \GuzzleHttp\HandlerStack::create($mock);
        $client = new \GuzzleHttp\Client(['handler' => $handlerStack]);
        $driver->setClient($client);

        // Cache the driver in manager
        $reflection = new \ReflectionClass($manager);
        $driversProperty = $reflection->getProperty('drivers');
        $driversProperty->setAccessible(true);
        $drivers = $driversProperty->getValue($manager);
        $drivers[$provider] = $driver;
        $driversProperty->setValue($manager, $drivers);

        // Rebind manager
        $this->app->singleton(\PayZephyr\VirtualAccounts\Services\VirtualAccountManager::class, function () use ($manager) {
            return $manager;
        });

        return $manager;
    }
}

