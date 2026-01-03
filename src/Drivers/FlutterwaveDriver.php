<?php

declare(strict_types=1);

namespace PayZephyr\VirtualAccounts\Drivers;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use PayZephyr\VirtualAccounts\DataObjects\IncomingTransferDTO;
use PayZephyr\VirtualAccounts\DataObjects\VirtualAccountDTO;
use PayZephyr\VirtualAccounts\Exceptions\InvalidConfigurationException;
use PayZephyr\VirtualAccounts\Exceptions\VirtualAccountException;
use PayZephyr\VirtualAccounts\Exceptions\WebhookParseException;
use Throwable;

/**
 * Flutterwave Driver Implementation
 *
 * Handles virtual account creation and webhook processing for Flutterwave.
 */
final class FlutterwaveDriver extends AbstractDriver
{
    protected string $name = 'flutterwave';

    /**
     * Validate Flutterwave configuration.
     *
     * @throws VirtualAccountException
     */
    protected function validateConfig(): void
    {
        if (empty($this->config['secret_key'])) {
            throw new InvalidConfigurationException(
                'Flutterwave secret key is required in configuration'
            );
        }
    }

    /**
     * Get default headers with Flutterwave authentication.
     *
     * @return array<string, string>
     */
    protected function getDefaultHeaders(): array
    {
        return array_merge(parent::getDefaultHeaders(), [
            'Authorization' => 'Bearer '.$this->config['secret_key'],
        ]);
    }

    /**
     * Create virtual account with Flutterwave.
     *
     * @param  array<string, mixed>  $payload
     *
     * @throws VirtualAccountException
     */
    public function createAccount(array $payload): VirtualAccountDTO
    {
        $this->validateCurrency($payload['currency'] ?? 'NGN');

        try {
            $response = $this->makeRequest('POST', '/virtual-account-numbers', [
                'json' => [
                    'email' => $payload['customer_email'],
                    'firstname' => $this->extractFirstName($payload['customer_name']),
                    'lastname' => $this->extractLastName($payload['customer_name']),
                    'phonenumber' => $payload['customer_phone'] ?? '',
                    'tx_ref' => $this->generateReference('VA'),
                    'is_permanent' => true,
                    'bvn' => $payload['bvn'] ?? '',
                ],
            ]);

            $data = $this->parseResponse($response);

            if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
                $this->handleError($data, 'Account creation');
            }

            $data = $data['data'] ?? [];

            if (empty($data)) {
                throw VirtualAccountException::providerError(
                    $this->getName(),
                    'Empty response from Flutterwave API'
                );
            }

            // Flutterwave returns account details in this structure
            $accountData = [
                'account_number' => (string) ($data['account_number'] ?? ''),
                'account_name' => (string) ($data['note'] ?? $payload['customer_name']),
                'bank_name' => (string) ($data['bank_name'] ?? ''),
                'bank_code' => (string) ($data['bank_code'] ?? ''),
                'provider_reference' => (string) ($data['flw_ref'] ?? $data['tx_ref'] ?? ''),
                'provider' => $this->getName(),
                'currency' => $payload['currency'] ?? 'NGN',
                'customer_id' => $payload['customer_id'] ?? null,
                'metadata' => $data,
            ];

            $this->log('info', 'Flutterwave virtual account created', [
                'account_number' => $accountData['account_number'],
                'provider_reference' => $accountData['provider_reference'],
            ]);

            return VirtualAccountDTO::fromArray($accountData);

        } catch (VirtualAccountException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw VirtualAccountException::providerError(
                $this->getName(),
                'Failed to create virtual account: '.$e->getMessage()
            );
        }
    }

    /**
     * Verify Flutterwave webhook signature.
     */
    public function verifyWebhook(Request $request): bool
    {
        $secretHash = $this->config['webhook_secret'] ?? null;

        if (! $secretHash) {
            // If no secret configured, skip verification (not recommended for production)
            Log::warning('Flutterwave webhook secret not configured, skipping verification');

            return true;
        }

        $signature = $request->header('verif-hash');

        if (! $signature) {
            return false;
        }

        return hash_equals($secretHash, $signature);
    }

    /**
     * Parse incoming transfer from Flutterwave webhook.
     *
     * @throws WebhookParseException
     * @throws Exception
     */
    public function parseIncomingTransfer(Request $request): IncomingTransferDTO
    {
        $payload = $request->all();

        // Flutterwave webhook structure: { event: 'charge.completed', data: {...} }
        $event = $payload['event'] ?? '';

        if ($event !== 'charge.completed') {
            throw WebhookParseException::invalidPayload(
                "Expected event 'charge.completed', got: $event"
            );
        }

        $data = $payload['data'] ?? [];

        if (empty($data)) {
            throw WebhookParseException::missingField('data');
        }

        // Extract transfer details from Flutterwave payload
        $transferData = [
            'transaction_reference' => $data['tx_ref'] ?? $data['flw_ref'] ?? '',
            'provider_reference' => $data['flw_ref'] ?? $data['id'] ?? '',
            'account_number' => $data['account_number'] ?? '',
            'amount' => (float) ($data['amount'] ?? 0),
            'currency' => $data['currency'] ?? 'NGN',
            'sender_name' => $data['customer']['name'] ?? $data['customer_name'] ?? 'Unknown',
            'sender_account' => $data['customer']['account_number'] ?? null,
            'sender_bank' => $data['customer']['bank'] ?? null,
            'narration' => $data['narration'] ?? $data['payment_type'] ?? null,
            'session_id' => $data['session_id'] ?? null,
            'settled_at' => $data['created_at'] ?? null,
            'metadata' => $data,
        ];

        // Validate required fields
        if (empty($transferData['transaction_reference'])) {
            throw WebhookParseException::missingField('transaction_reference (tx_ref)');
        }

        if (empty($transferData['account_number'])) {
            throw WebhookParseException::missingField('account_number');
        }

        if ($transferData['amount'] <= 0) {
            throw WebhookParseException::invalidPayload('Amount must be greater than zero');
        }

        return IncomingTransferDTO::fromArray($transferData);
    }

    /**
     * Get provider name.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get supported currencies for Flutterwave.
     *
     * @return array<int, string>
     */
    public function getSupportedCurrencies(): array
    {
        return ['NGN', 'KES', 'ZAR', 'GHS', 'UGX', 'TZS', 'ZMW'];
    }

    /**
     * Fetch account details from Flutterwave.
     *
     * @throws VirtualAccountException
     */
    public function fetchAccount(string $accountReference): VirtualAccountDTO
    {
        try {
            $response = $this->makeRequest('GET', "/virtual-account-numbers/$accountReference");

            $data = $this->parseResponse($response);

            if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
                $this->handleError($data, 'Fetch account');
            }

            $data = $data['data'] ?? [];

            return VirtualAccountDTO::fromArray([
                'account_number' => (string) ($data['account_number'] ?? ''),
                'account_name' => (string) ($data['note'] ?? ''),
                'bank_name' => (string) ($data['bank_name'] ?? ''),
                'bank_code' => (string) ($data['bank_code'] ?? ''),
                'provider_reference' => (string) ($data['flw_ref'] ?? $accountReference),
                'provider' => $this->getName(),
                'currency' => (string) ($data['currency'] ?? 'NGN'),
                'metadata' => $data,
            ]);

        } catch (Throwable $e) {
            throw VirtualAccountException::providerError(
                $this->getName(),
                'Failed to fetch account: '.$e->getMessage()
            );
        }
    }

    /**
     * Get account balance (Flutterwave doesn't provide this via API typically).
     */
    public function getBalance(string $accountReference): ?float
    {
        // Flutterwave virtual accounts don't expose balance via API
        return null;
    }

    /**
     * Health check for Flutterwave API.
     */
    public function healthCheck(): bool
    {
        try {
            // Use a lightweight endpoint - account verification or status
            $response = $this->makeRequest('GET', '/virtual-account-numbers', [
                'query' => ['perPage' => 1],
            ]);

            return $response->getStatusCode() >= 200 && $response->getStatusCode() < 300;
        } catch (Throwable $e) {
            $this->log('warning', 'Flutterwave health check failed', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Extract first name from full name.
     */
    protected function extractFirstName(string $fullName): string
    {
        $parts = explode(' ', trim($fullName), 2);

        return $parts[0] ?? $fullName;
    }

    /**
     * Extract last name from full name.
     */
    protected function extractLastName(string $fullName): string
    {
        $parts = explode(' ', trim($fullName), 2);

        return $parts[1] ?? $parts[0] ?? '';
    }
}
