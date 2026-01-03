<?php

declare(strict_types=1);

namespace PayZephyr\VirtualAccounts\Tests\Unit;

use GuzzleHttp\Psr7\Response;
use Illuminate\Http\Request;
use PayZephyr\VirtualAccounts\Drivers\FlutterwaveDriver;
use PayZephyr\VirtualAccounts\Exceptions\InvalidConfigurationException;
use PayZephyr\VirtualAccounts\Exceptions\VirtualAccountException;
use PayZephyr\VirtualAccounts\Tests\TestCase;

class FlutterwaveDriverTest extends TestCase
{
    protected function createDriver(array $config = []): FlutterwaveDriver
    {
        $defaultConfig = array_merge([
            'secret_key' => 'FLWSECK_TEST_xxx',
            'webhook_secret' => 'test_webhook_secret',
            'base_url' => 'https://api.flutterwave.com/v3',
        ], $config);

        return new FlutterwaveDriver($defaultConfig);
    }

    public function test_throws_exception_when_secret_key_missing(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('secret key');

        new FlutterwaveDriver([]);
    }

    public function test_can_create_virtual_account(): void
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

        $mock = new \GuzzleHttp\Handler\MockHandler([$response]);
        $handlerStack = \GuzzleHttp\HandlerStack::create($mock);
        $client = new \GuzzleHttp\Client(['handler' => $handlerStack]);

        $driver = $this->createDriver();
        $driver->setClient($client);

        $account = $driver->createAccount([
            'customer_id' => 'customer_123',
            'customer_name' => 'John Doe',
            'customer_email' => 'john@example.com',
            'customer_phone' => '+2348012345678',
            'currency' => 'NGN',
        ]);

        $this->assertEquals('1234567890', $account->accountNumber);
        $this->assertEquals('John Doe', $account->accountName);
        $this->assertEquals('Wema Bank', $account->bankName);
        $this->assertEquals('flutterwave', $driver->getName());
    }

    public function test_throws_exception_on_api_error(): void
    {
        $response = new Response(400, [], json_encode([
            'status' => 'error',
            'message' => 'Invalid request',
            'code' => 'INVALID_REQUEST',
        ]));

        $mock = new \GuzzleHttp\Handler\MockHandler([$response]);
        $handlerStack = \GuzzleHttp\HandlerStack::create($mock);
        $client = new \GuzzleHttp\Client(['handler' => $handlerStack]);

        $driver = $this->createDriver();
        $driver->setClient($client);

        $this->expectException(VirtualAccountException::class);

        $driver->createAccount([
            'customer_name' => 'John Doe',
            'customer_email' => 'john@example.com',
            'currency' => 'NGN',
        ]);
    }

    public function test_can_verify_webhook(): void
    {
        $driver = $this->createDriver([
            'webhook_secret' => 'test_secret',
        ]);

        $request = Request::create('/webhook', 'POST', [], [], [], [
            'HTTP_VERIF-HASH' => 'test_secret',
        ]);

        $result = $driver->verifyWebhook($request);

        $this->assertTrue($result);
    }

    public function test_rejects_webhook_with_invalid_signature(): void
    {
        $driver = $this->createDriver([
            'webhook_secret' => 'test_secret',
        ]);

        $request = Request::create('/webhook', 'POST', [], [], [], [
            'HTTP_VERIF-HASH' => 'wrong_secret',
        ]);

        $result = $driver->verifyWebhook($request);

        $this->assertFalse($result);
    }

    public function test_can_parse_incoming_transfer(): void
    {
        $driver = $this->createDriver();

        $request = Request::create('/webhook', 'POST', [
            'event' => 'charge.completed',
            'data' => [
                'tx_ref' => 'TX_REF_123',
                'flw_ref' => 'FLW_REF_123',
                'account_number' => '1234567890',
                'amount' => 5000.00,
                'currency' => 'NGN',
                'customer' => [
                    'name' => 'John Doe',
                    'account_number' => '9876543210',
                    'bank' => 'GTBank',
                ],
                'narration' => 'Payment for order',
                'created_at' => '2024-01-01T00:00:00Z',
            ],
        ]);

        $transfer = $driver->parseIncomingTransfer($request);

        $this->assertEquals('TX_REF_123', $transfer->transactionReference);
        $this->assertEquals('FLW_REF_123', $transfer->providerReference);
        $this->assertEquals('1234567890', $transfer->accountNumber);
        $this->assertEquals(5000.00, $transfer->amount);
        $this->assertEquals('NGN', $transfer->currency);
    }

    public function test_throws_exception_on_invalid_webhook_event(): void
    {
        $driver = $this->createDriver();

        $request = Request::create('/webhook', 'POST', [
            'event' => 'charge.failed',
            'data' => [],
        ]);

        $this->expectException(\PayZephyr\VirtualAccounts\Exceptions\WebhookParseException::class);

        $driver->parseIncomingTransfer($request);
    }

    public function test_returns_supported_currencies(): void
    {
        $driver = $this->createDriver();

        $currencies = $driver->getSupportedCurrencies();

        $this->assertContains('NGN', $currencies);
        $this->assertContains('KES', $currencies);
        $this->assertContains('ZAR', $currencies);
    }

    public function test_can_fetch_account(): void
    {
        $response = new Response(200, [], json_encode([
            'status' => 'success',
            'data' => [
                'account_number' => '1234567890',
                'note' => 'John Doe',
                'bank_name' => 'Wema Bank',
                'bank_code' => '035',
                'flw_ref' => 'FLW_REF_123',
                'currency' => 'NGN',
            ],
        ]));

        $mock = new \GuzzleHttp\Handler\MockHandler([$response]);
        $handlerStack = \GuzzleHttp\HandlerStack::create($mock);
        $client = new \GuzzleHttp\Client(['handler' => $handlerStack]);

        $driver = $this->createDriver();
        $driver->setClient($client);

        $account = $driver->fetchAccount('FLW_REF_123');

        $this->assertEquals('1234567890', $account->accountNumber);
        $this->assertEquals('John Doe', $account->accountName);
    }

    public function test_health_check_returns_false_on_failure(): void
    {
        $response = new Response(500, [], json_encode(['status' => 'error']));

        $mock = new \GuzzleHttp\Handler\MockHandler([$response]);
        $handlerStack = \GuzzleHttp\HandlerStack::create($mock);
        $client = new \GuzzleHttp\Client(['handler' => $handlerStack]);

        $driver = $this->createDriver();
        $driver->setClient($client);

        $result = $driver->healthCheck();

        $this->assertFalse($result);
    }
}

