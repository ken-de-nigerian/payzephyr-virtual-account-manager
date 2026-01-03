# Architecture

This document describes the architectural design of the Virtual Account Manager package, following PayZephyr's architectural principles.

## Design Principles

### 1. Open/Closed Principle (OCP)

The package is designed to be **open for extension but closed for modification**. New providers can be added without modifying existing code.

**Example:**
```php
// Adding a new provider doesn't require changing existing code
class NewBankDriver extends AbstractDriver
{
    // Implement interface methods
}
```

### 2. Convention Over Configuration

The package uses naming conventions to automatically resolve components:

- Provider name `flutterwave` → `FlutterwaveDriver` class
- Provider name `monipoint` → `MoniepointDriver` class

**Example:**
```php
// Automatically resolves to FlutterwaveDriver
$driver = $manager->driver('flutterwave');
```

### 3. Interface-Driven Design

All providers implement the `VirtualAccountProvider` interface, ensuring consistency:

```php
interface VirtualAccountProvider
{
    public function createAccount(array $payload): VirtualAccountDTO;
    public function verifyWebhook(Request $request): bool;
    public function parseIncomingTransfer(Request $request): IncomingTransferDTO;
    // ... more methods
}
```

## Component Architecture

### Service Layer

```
VirtualAccountManager
    ├── DriverFactory (creates driver instances)
    └── Drivers (provider implementations)
```

### Driver Factory Pattern

The `DriverFactory` handles driver creation using:

1. **Convention Over Configuration**: Auto-resolves driver classes
2. **Configuration Override**: Allows custom driver classes via config
3. **Registration**: Supports runtime driver registration

### Abstract Driver

All drivers extend `AbstractDriver`, which provides:

- HTTP client management (Guzzle)
- Network error handling
- Webhook validation
- Log sanitization
- Health checks
- Currency validation
- Reference generation

### Traits

**HasNetworkErrorHandling**
- Handles network errors gracefully
- Provides user-friendly error messages
- Distinguishes error types (connection, server, request)

**HasWebhookValidation**
- Validates webhook timestamps
- Prevents replay attacks
- Extracts timestamps from payloads

**HasLogSanitization**
- Removes sensitive data from logs
- Prevents credential leakage
- Recursively sanitizes nested data

## Data Flow

### Account Creation Flow

```
User Request
    ↓
VirtualAccountManager
    ↓
DriverFactory (creates driver)
    ↓
Provider Driver (AbstractDriver)
    ↓
Provider API
    ↓
VirtualAccountDTO (normalized response)
    ↓
Database (VirtualAccount model)
    ↓
VirtualAccountCreated Event
```

### Webhook Processing Flow

```
Provider Webhook
    ↓
WebhookController
    ↓
Verify Signature (Driver)
    ↓
Log Raw Payload (ProviderWebhookLog)
    ↓
Dispatch Job (ProcessIncomingTransfer)
    ↓
Parse Transfer (Driver)
    ↓
Idempotency Check
    ↓
Save Transfer (IncomingTransfer)
    ↓
DepositConfirmed Event
```

## Error Handling

### Exception Hierarchy

```
VirtualAccountException (base)
    ├── DriverNotFoundException
    ├── InvalidConfigurationException
    └── WebhookParseException
```

### Network Error Handling

Network errors are caught and wrapped with context:

```php
try {
    $response = $this->makeRequest('POST', '/endpoint', $options);
} catch (GuzzleException $e) {
    $this->handleNetworkError($e, 'POST', '/endpoint');
    throw VirtualAccountException::providerError(...);
}
```

## Caching Strategy

### Health Check Caching

Health checks are cached to prevent excessive API calls:

```php
public function getCachedHealthCheck(): bool
{
    return Cache::remember($cacheKey, $cacheTtl, function () {
        return $this->healthCheck();
    });
}
```

## Logging Strategy

### Sanitized Logging

All logs are sanitized to prevent sensitive data leakage:

```php
protected function log(string $level, string $message, array $context = []): void
{
    $sanitizedContext = $this->sanitizeLogContext($context);
    Log::channel($channelName)->{$level}($message, $sanitizedContext);
}
```

## Testing Architecture

### Test Structure

```
tests/
    ├── Unit/          # Unit tests for individual components
    ├── Feature/       # Feature tests for workflows
    ├── Integration/   # Integration tests
    ├── Fixtures/      # Test fixtures and mocks
    └── Helpers/       # Test helper classes
```

### Mocking Strategy

Tests use Guzzle MockHandler to mock HTTP responses:

```php
$mock = new \GuzzleHttp\Handler\MockHandler([$response]);
$client = new \GuzzleHttp\Client(['handler' => $handlerStack]);
$driver->setClient($client);
```

## Extension Points

### Adding Custom Providers

1. Create driver class extending `AbstractDriver`
2. Implement `VirtualAccountProvider` interface
3. Register in config or use convention

### Custom Services

Services can be extended via dependency injection:

```php
$this->app->singleton(DriverFactory::class, function ($app) {
    return new CustomDriverFactory();
});
```

## Performance Considerations

- **Health Check Caching**: Reduces API calls
- **Driver Caching**: Drivers are cached per request
- **Idempotency**: Prevents duplicate processing
- **Queue Processing**: Webhooks processed asynchronously

## Security Considerations

- **Webhook Verification**: All webhooks verified
- **Log Sanitization**: Sensitive data never logged
- **Rate Limiting**: Webhook endpoints rate-limited
- **Idempotency Keys**: SHA256 hashed for uniqueness

