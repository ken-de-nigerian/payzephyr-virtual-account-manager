<?php

declare(strict_types=1);

namespace PayZephyr\VirtualAccounts\Drivers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use PayZephyr\VirtualAccounts\Constants\VirtualAccountConstants;
use PayZephyr\VirtualAccounts\Contracts\VirtualAccountProvider;
use PayZephyr\VirtualAccounts\Exceptions\InvalidConfigurationException;
use PayZephyr\VirtualAccounts\Exceptions\VirtualAccountException;
use PayZephyr\VirtualAccounts\Traits\HasLogSanitization;
use PayZephyr\VirtualAccounts\Traits\HasNetworkErrorHandling;
use PayZephyr\VirtualAccounts\Traits\HasWebhookValidation;
use Psr\Http\Message\ResponseInterface;
use Random\RandomException;
use Throwable;

/**
 * Abstract Driver - Base Class for All Virtual Account Providers
 *
 * This is the parent class that all provider drivers extend.
 * It provides common functionality like HTTP requests, health checks,
 * currency validation, and reference generation.
 */
abstract class AbstractDriver implements VirtualAccountProvider
{
    use HasLogSanitization;
    use HasNetworkErrorHandling;
    use HasWebhookValidation;

    protected Client $client;

    /** @var array<string, mixed> */
    protected array $config;

    protected string $name;

    /**
     * Create a new virtual account driver instance.
     *
     * @param  array<string, mixed>  $config  Provider configuration
     *
     * @throws InvalidConfigurationException If required, config is missing.
     */
    public function __construct(array $config = [])
    {
        $this->config = $config;
        $this->validateConfig();
        $this->initializeClient();
    }

    /**
     * Check that all required configuration is present (API keys, etc.).
     * Each driver implements this to check for their specific requirements.
     *
     * @throws InvalidConfigurationException If something is missing.
     */
    abstract protected function validateConfig(): void;

    /**
     * Set up the HTTP client for making API requests to the virtual account provider.
     */
    protected function initializeClient(): void
    {
        $this->client = new Client([
            'base_uri' => $this->config['base_url'] ?? '',
            'timeout' => $this->config['timeout'] ?? VirtualAccountConstants::DEFAULT_TIMEOUT_SECONDS,
            'verify' => ! ($this->config['testing_mode'] ?? false),
            'headers' => $this->getDefaultHeaders(),
        ]);
    }

    /**
     * Get the default HTTP headers needed for API requests (like Authorization).
     * Each driver can override this with their provider's specific headers.
     *
     * @return array<string, string>
     */
    protected function getDefaultHeaders(): array
    {
        return [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
    }

    /**
     * Make an HTTP request to the virtual account provider's API.
     *
     * Network errors are caught and wrapped with more user-friendly messages
     * to prevent crashes and provide better error context.
     *
     * @param  string  $method  HTTP method (GET, POST, etc.)
     * @param  string  $uri  URI endpoint
     * @param  array<string, mixed>  $options  Additional Guzzle options (json, query, etc.)
     * @return ResponseInterface HTTP response
     *
     * @throws VirtualAccountException If the HTTP request fails.
     */
    protected function makeRequest(string $method, string $uri, array $options = []): ResponseInterface
    {
        try {
            return $this->client->request($method, $uri, $options);
        } catch (GuzzleException $e) {
            $this->handleNetworkError($e, $method, $uri);

            throw VirtualAccountException::providerError(
                $this->getName(),
                $this->getNetworkErrorMessage($e),
            );
        }
    }

    /**
     * Convert the HTTP response body from JSON to a PHP array.
     *
     * @return array<string, mixed>
     */
    protected function parseResponse(ResponseInterface $response): array
    {
        $body = (string) $response->getBody();

        return json_decode($body, true) ?? [];
    }

    /**
     * Get HTTP client instance with default headers (backward compatibility).
     *
     * @deprecated Use makeRequest() instead for better error handling
     */
    protected function http(): Client
    {
        return $this->client;
    }

    /**
     * Get base URL from config.
     */
    protected function getBaseUrl(): string
    {
        return (string) ($this->config['base_url'] ?? '');
    }

    /**
     * Build full URL from endpoint.
     */
    protected function url(string $endpoint): string
    {
        $base = rtrim($this->getBaseUrl(), '/');
        $endpoint = ltrim($endpoint, '/');

        return "$base/$endpoint";
    }

    /**
     * Create a unique transaction reference (like 'FLUTTERWAVE_1234567890_abc123def456').
     *
     * Format: PREFIX_TIMESTAMP_RANDOMHEX
     *
     * @param  string|null  $prefix  Custom prefix (defaults to provider name in uppercase)
     * @return string Unique reference
     *
     * @throws RandomException If random number generation fails.
     */
    protected function generateReference(?string $prefix = null): string
    {
        $prefix = $prefix ?? strtoupper($this->getName());

        return $prefix.'_'.time().'_'.bin2hex(random_bytes(8));
    }

    /**
     * Handle HTTP errors and throw appropriate exceptions.
     *
     * @param  array<string, mixed>  $response
     *
     * @throws VirtualAccountException
     */
    protected function handleError(array $response, string $operation = 'Operation'): void
    {
        $message = $response['message'] ?? $response['error'] ?? 'Unknown error';
        $code = $response['code'] ?? $response['status'] ?? 0;

        throw VirtualAccountException::providerError(
            $this->getName(),
            "$operation failed: $message (Code: $code)"
        );
    }

    /**
     * Check if the provider is working (cached result).
     *
     * The result is cached for a few minutes, so we don't check too often.
     * This prevents slowing down operations with repeated health checks.
     */
    public function getCachedHealthCheck(): bool
    {
        $cacheKey = 'virtual-accounts.health.'.$this->getName();
        $config = config('virtual-accounts', []);
        $cacheTtl = $config['health_check']['cache_ttl'] ?? VirtualAccountConstants::HEALTH_CHECK_CACHE_TTL_SECONDS;

        return Cache::remember($cacheKey, $cacheTtl, function () {
            return $this->healthCheck();
        });
    }

    /**
     * Default health check implementation.
     *
     * Override in child classes for provider-specific health checks.
     */
    public function healthCheck(): bool
    {
        // Default implementation - override in child classes
        try {
            // Simple connectivity check
            $response = $this->makeRequest('GET', '/health');

            return $response->getStatusCode() >= 200 && $response->getStatusCode() < 300;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Write a log message (for debugging and monitoring).
     *
     * @param  string  $level  Log level: 'info', 'warning', 'error', etc.
     * @param  string  $message  The log message
     * @param  array<string, mixed>  $context  Extra data to include in the log
     */
    protected function log(string $level, string $message, array $context = []): void
    {
        $config = config('virtual-accounts', []);
        if (! ($config['logging']['enabled'] ?? true)) {
            return;
        }

        $sanitizedContext = $this->sanitizeLogContext($context);
        $channelName = $config['logging']['channel'] ?? 'virtual-accounts';

        try {
            Log::channel($channelName)->{$level}("[{$this->getName()}] $message", $sanitizedContext);
        } catch (InvalidArgumentException) {
            Log::{$level}("[{$this->getName()}] $message", $sanitizedContext);
        }
    }

    /**
     * Default supported currencies.
     *
     * Override in child classes for provider-specific currencies.
     *
     * @return array<int, string>
     */
    public function getSupportedCurrencies(): array
    {
        return ['NGN']; // Default to NGN for Nigerian providers
    }

    /**
     * Check if this provider supports a specific currency (e.g., 'NGN', 'USD').
     */
    public function isCurrencySupported(string $currency): bool
    {
        return in_array(strtoupper($currency), $this->getSupportedCurrencies());
    }

    /**
     * Validate currency is supported.
     *
     * @throws VirtualAccountException
     */
    protected function validateCurrency(string $currency): void
    {
        if (! $this->isCurrencySupported($currency)) {
            throw VirtualAccountException::providerError(
                $this->getName(),
                "Currency $currency is not supported. Supported currencies: ".implode(', ', $this->getSupportedCurrencies())
            );
        }
    }

    /**
     * Get the name of this virtual account provider (e.g., 'flutterwave', 'moniepoint').
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Replace the HTTP client (mainly used for testing with mock clients).
     */
    public function setClient(Client $client): void
    {
        $this->client = $client;
    }
}
