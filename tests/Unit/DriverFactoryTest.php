<?php

declare(strict_types=1);

namespace PayZephyr\VirtualAccounts\Tests\Unit;

use PayZephyr\VirtualAccounts\Exceptions\DriverNotFoundException;
use PayZephyr\VirtualAccounts\Services\DriverFactory;
use PayZephyr\VirtualAccounts\Tests\TestCase;

class DriverFactoryTest extends TestCase
{
    protected DriverFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = new DriverFactory();
    }

    public function test_can_create_flutterwave_driver(): void
    {
        $config = [
            'secret_key' => 'FLWSECK_TEST_xxx',
            'webhook_secret' => 'test_secret',
            'base_url' => 'https://api.flutterwave.com/v3',
        ];

        $driver = $this->factory->create('flutterwave', $config);

        $this->assertInstanceOf(\PayZephyr\VirtualAccounts\Contracts\VirtualAccountProvider::class, $driver);
        $this->assertEquals('flutterwave', $driver->getName());
    }

    public function test_throws_exception_for_nonexistent_driver(): void
    {
        $this->expectException(DriverNotFoundException::class);
        $this->expectExceptionMessage('Driver class');

        $this->factory->create('nonexistent', []);
    }

    public function test_can_register_custom_driver(): void
    {
        $this->factory->register('custom', \PayZephyr\VirtualAccounts\Tests\Fixtures\CustomTestDriver::class);

        $driver = $this->factory->create('custom', ['api_key' => 'test']);

        $this->assertInstanceOf(\PayZephyr\VirtualAccounts\Contracts\VirtualAccountProvider::class, $driver);
        $this->assertTrue($this->factory->isRegistered('custom'));
    }

    public function test_throws_exception_when_registering_invalid_class(): void
    {
        $this->expectException(DriverNotFoundException::class);

        $this->factory->register('invalid', 'NonExistentClass');
    }

    public function test_resolves_driver_from_config(): void
    {
        config(['virtual-accounts.providers.custom' => [
            'driver_class' => \PayZephyr\VirtualAccounts\Tests\Fixtures\CustomTestDriver::class,
            'api_key' => 'test',
        ]]);

        $driver = $this->factory->create('custom', ['api_key' => 'test']);

        $this->assertInstanceOf(\PayZephyr\VirtualAccounts\Tests\Fixtures\CustomTestDriver::class, $driver);
    }

    public function test_uses_convention_over_configuration(): void
    {
        $config = [
            'secret_key' => 'FLWSECK_TEST_xxx',
            'base_url' => 'https://api.flutterwave.com/v3',
        ];

        // Should resolve FlutterwaveDriver from 'flutterwave' name
        $driver = $this->factory->create('flutterwave', $config);

        $this->assertInstanceOf(\PayZephyr\VirtualAccounts\Drivers\FlutterwaveDriver::class, $driver);
    }
}

