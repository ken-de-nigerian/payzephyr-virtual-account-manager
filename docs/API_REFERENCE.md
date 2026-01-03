# API Reference

Complete API reference for the Virtual Account Manager package.

## Facade Methods

### `VirtualAccounts::assignTo(string $customerId)`

Start a fluent builder for creating a virtual account.

**Returns:** `AccountBuilder` instance

**Example:**
```php
$account = VirtualAccounts::assignTo('user_123')
    ->name('John Doe')
    ->email('john@example.com')
    ->create();
```

### `VirtualAccounts::getAccount(string $customerId, ?string $provider = null)`

Retrieve a virtual account for a customer.

**Parameters:**
- `$customerId` (string): Customer identifier
- `$provider` (string|null): Optional provider name

**Returns:** `VirtualAccountDTO|null`

**Example:**
```php
$account = VirtualAccounts::getAccount('user_123');
```

### `VirtualAccounts::deactivateAccount(string $accountNumber)`

Deactivate a virtual account.

**Parameters:**
- `$accountNumber` (string): Account number to deactivate

**Returns:** `bool`

**Example:**
```php
VirtualAccounts::deactivateAccount('1234567890');
```

### `VirtualAccounts::driver(?string $name = null)`

Get a provider driver instance.

**Parameters:**
- `$name` (string|null): Provider name (defaults to configured default)

**Returns:** `VirtualAccountProvider`

**Example:**
```php
$driver = VirtualAccounts::driver('flutterwave');
$healthy = $driver->getCachedHealthCheck();
```

## AccountBuilder Methods

### `name(string $name)`

Set customer name.

**Returns:** `AccountBuilder`

### `email(string $email)`

Set customer email.

**Returns:** `AccountBuilder`

### `phone(string $phone)`

Set customer phone number.

**Returns:** `AccountBuilder`

### `bvn(string $bvn)`

Set Bank Verification Number (BVN).

**Returns:** `AccountBuilder`

### `currency(string $currency)`

Set currency (default: NGN).

**Returns:** `AccountBuilder`

### `preferredBank(string $bank)`

Set preferred bank (if supported by provider).

**Returns:** `AccountBuilder`

### `using(string $provider)`

Specify provider to use.

**Returns:** `AccountBuilder`

### `metadata(array $metadata)`

Add custom metadata.

**Returns:** `AccountBuilder`

### `create()`

Execute account creation.

**Returns:** `VirtualAccountDTO`

## VirtualAccountProvider Interface

### `createAccount(array $payload): VirtualAccountDTO`

Create a virtual account with the provider.

**Parameters:**
- `$payload` (array): Account creation data

**Returns:** `VirtualAccountDTO`

### `verifyWebhook(Request $request): bool`

Verify webhook authenticity.

**Parameters:**
- `$request` (Request): Incoming webhook request

**Returns:** `bool`

### `parseIncomingTransfer(Request $request): IncomingTransferDTO`

Parse incoming transfer from webhook.

**Parameters:**
- `$request` (Request): Webhook request

**Returns:** `IncomingTransferDTO`

### `getName(): string`

Get provider name.

**Returns:** `string`

### `healthCheck(): bool`

Check provider health.

**Returns:** `bool`

### `getCachedHealthCheck(): bool`

Get cached health check result.

**Returns:** `bool`

### `getSupportedCurrencies(): array`

Get supported currencies.

**Returns:** `array<int, string>`

### `isCurrencySupported(string $currency): bool`

Check if currency is supported.

**Parameters:**
- `$currency` (string): Currency code

**Returns:** `bool`

### `fetchAccount(string $accountReference): VirtualAccountDTO`

Fetch account details from provider.

**Parameters:**
- `$accountReference` (string): Provider account reference

**Returns:** `VirtualAccountDTO`

### `getBalance(string $accountReference): ?float`

Get account balance (if supported).

**Parameters:**
- `$accountReference` (string): Provider account reference

**Returns:** `float|null`

## Data Transfer Objects

### VirtualAccountDTO

```php
class VirtualAccountDTO
{
    public string $accountNumber;
    public string $accountName;
    public string $bankName;
    public string $bankCode;
    public string $providerReference;
    public string $provider;
    public string $currency;
    public ?string $customerId;
    public array $metadata;
}
```

### IncomingTransferDTO

```php
class IncomingTransferDTO
{
    public string $transactionReference;
    public string $providerReference;
    public string $accountNumber;
    public float $amount;
    public string $currency;
    public string $senderName;
    public ?string $senderAccount;
    public ?string $senderBank;
    public ?string $narration;
    public ?string $sessionId;
    public ?string $settledAt;
    public array $metadata;
}
```

### AccountAssignmentDTO

```php
class AccountAssignmentDTO
{
    public string $customerId;
    public string $customerName;
    public string $customerEmail;
    public ?string $customerPhone;
    public ?string $bvn;
    public string $currency;
    public ?string $preferredBank;
    public array $metadata;
}
```

## Events

### VirtualAccountCreated

Dispatched when a virtual account is created.

**Properties:**
- `$account` (VirtualAccountDTO)
- `$customerId` (string)

### DepositConfirmed

Dispatched when a deposit is confirmed.

**Methods:**
- `getCustomerId(): string`
- `getAmount(): float`
- `getTransfer(): IncomingTransfer`

## Models

### VirtualAccount

Eloquent model for virtual accounts.

**Relationships:**
- `transfers()`: HasMany IncomingTransfer

### IncomingTransfer

Eloquent model for incoming transfers.

**Relationships:**
- `virtualAccount()`: BelongsTo VirtualAccount

### ProviderWebhookLog

Eloquent model for webhook logs.

## Exceptions

### DriverNotFoundException

Thrown when a driver cannot be found.

### InvalidConfigurationException

Thrown when configuration is invalid.

### VirtualAccountException

Base exception for virtual account errors.

### WebhookParseException

Thrown when webhook parsing fails.

