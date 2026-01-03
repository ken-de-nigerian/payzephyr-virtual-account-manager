# Laravel Virtual Account Manager

A production-grade Laravel package for managing virtual bank accounts across multiple providers in African fintech ecosystems.

[![Latest Version](https://img.shields.io/packagist/v/payzephyr/laravel-virtual-account-manager.svg?style=flat-square)](https://packagist.org/packages/payzephyr/laravel-virtual-account-manager)
[![Total Downloads](https://img.shields.io/packagist/dt/payzephyr/laravel-virtual-account-manager.svg?style=flat-square)](https://packagist.org/packages/payzephyr/laravel-virtual-account-manager)
[![License](https://img.shields.io/packagist/l/payzephyr/laravel-virtual-account-manager.svg?style=flat-square)](https://packagist.org/packages/payzephyr/laravel-virtual-account-manager)

## Architecture Philosophy

This package acts as a **provider orchestration and normalization layer**. It does NOT:
- Move or hold money
- Act as a bank
- Issue virtual accounts directly
- Bypass provider terms or compliance

It ONLY:
- Orchestrates virtual account providers
- Normalizes webhook events
- Provides unified API across providers
- Ensures idempotent processing
- Maintains audit trails

## Installation

```bash
composer require payzephyr/laravel-virtual-account-manager
```

```bash
php artisan vendor:publish --tag=virtual-accounts-config
php artisan vendor:publish --tag=virtual-accounts-migrations
php artisan migrate
```

## Configuration

```env
VIRTUAL_ACCOUNTS_DEFAULT_PROVIDER=flutterwave

FLUTTERWAVE_SECRET_KEY=your_secret_key
FLUTTERWAVE_WEBHOOK_SECRET=your_webhook_secret
FLUTTERWAVE_ENABLED=true
```

## Basic Usage

### Create Virtual Account

```php
use PayZephyr\VirtualAccounts\Facades\VirtualAccounts;

// Fluent API
$account = VirtualAccounts::assignTo($user->id)
    ->name($user->name)
    ->email($user->email)
    ->phone($user->phone)
    ->currency('NGN')
    ->using('flutterwave')
    ->create();

// Returns VirtualAccountDTO
echo $account->accountNumber;  // 0123456789
echo $account->bankName;       // "Wema Bank"
echo $account->accountName;    // "John Doe"
```

### Retrieve Account

```php
$account = VirtualAccounts::getAccount($userId);

if ($account) {
    echo "Account Number: {$account->accountNumber}";
    echo "Bank: {$account->bankName}";
}
```

### Listen for Deposits

```php
// EventServiceProvider.php
protected $listen = [
    \PayZephyr\VirtualAccounts\Events\DepositConfirmed::class => [
        \App\Listeners\CreditUserWallet::class,
    ],
];

// App\Listeners\CreditUserWallet.php
public function handle(DepositConfirmed $event): void
{
    $customerId = $event->getCustomerId();
    $amount = $event->getAmount();
    
    // Credit user wallet
    User::find($customerId)->wallet->credit($amount);
    
    Log::info('User wallet credited', [
        'user_id' => $customerId,
        'amount' => $amount,
        'transfer_id' => $event->transfer->id,
    ]);
}
```

## Webhook Setup

### Register Webhook URLs

Configure these URLs in your provider dashboards:

- Flutterwave: `https://yourdomain.com/virtual-accounts/webhook/flutterwave`
- Monipoint: `https://yourdomain.com/virtual-accounts/webhook/monipoint`
- Providus: `https://yourdomain.com/virtual-accounts/webhook/providus`

### Webhook Processing Flow

1. Webhook received → Signature verified
2. Raw payload logged immediately
3. Job dispatched to queue
4. Transfer parsed and normalized
5. Idempotency check (prevents duplicates)
6. Transfer persisted to database
7. `DepositConfirmed` event dispatched

**CRITICAL:** Ensure your queue workers are running:

```bash
php artisan queue:work
```

## Reconciliation

Run nightly reconciliation to detect inconsistencies:

```bash
php artisan virtual-accounts:reconcile
```

This detects:
- Duplicate transfers
- Stale pending transfers (>24 hours)
- Missing confirmations
- Provider inconsistencies

Automatically scheduled based on config:

```php
'reconciliation' => [
    'enabled' => true,
    'schedule' => 'daily', // hourly, daily, weekly
    'stale_transfer_hours' => 24,
],
```

## Adding Custom Providers

### 1. Create Driver Class

```php
namespace App\VirtualAccounts\Drivers;

use PayZephyr\VirtualAccounts\Contracts\VirtualAccountProvider;
use PayZephyr\VirtualAccounts\DataObjects\VirtualAccountDTO;
use PayZephyr\VirtualAccounts\DataObjects\IncomingTransferDTO;

class CustomBankDriver implements VirtualAccountProvider
{
    public function createAccount(array $payload): VirtualAccountDTO
    {
        // Call provider API to create account
        // Return normalized VirtualAccountDTO
    }
    
    public function verifyWebhook(Request $request): bool
    {
        // Verify webhook signature
    }
    
    public function parseIncomingTransfer(Request $request): IncomingTransferDTO
    {
        // Parse webhook payload
        // Return normalized IncomingTransferDTO
    }
    
    // ... implement other interface methods
}
```

### 2. Register in Config

```php
'providers' => [
    'custombank' => [
        'driver_class' => \App\VirtualAccounts\Drivers\CustomBankDriver::class,
        'api_key' => env('CUSTOMBANK_API_KEY'),
        'enabled' => true,
    ],
],
```

## Testing

```php
use PayZephyr\VirtualAccounts\Tests\TestCase;
use PayZephyr\VirtualAccounts\Facades\VirtualAccounts;

class VirtualAccountTest extends TestCase
{
    public function test_creates_account()
    {
        $account = VirtualAccounts::assignTo('user-123')
            ->name('Test User')
            ->email('test@example.com')
            ->using('flutterwave')
            ->create();
            
        $this->assertNotEmpty($account->accountNumber);
        $this->assertEquals('user-123', $account->customerId);
    }
    
    public function test_processes_deposit()
    {
        Event::fake();
        
        // Simulate webhook
        $this->postJson('/virtual-accounts/webhook/flutterwave', [
            'event' => 'charge.completed',
            'data' => [
                'flw_ref' => 'FLW-123',
                'account_number' => '0123456789',
                'amount' => 5000,
                'currency' => 'NGN',
                'customer' => ['name' => 'John Doe'],
            ],
        ]);
        
        Event::assertDispatched(DepositConfirmed::class);
    }
}
```

## Security Considerations

### Webhook Verification
- All webhooks are signature-verified before processing
- Invalid signatures are rejected with 403
- Verification can be disabled in non-production (NOT recommended)

### Idempotency
- Every transfer has unique idempotency key (SHA256 hash)
- Duplicate webhooks are safely ignored
- No double-crediting possible

### Audit Trail
- All raw webhooks logged to `provider_webhook_logs`
- Full transfer history in `incoming_transfers`
- Account creation tracked in `virtual_accounts`

## Monitoring

### Key Metrics

```php
use PayZephyr\VirtualAccounts\Services\ReconciliationService;

$stats = app(ReconciliationService::class)->getStatistics();

// Returns:
[
    'total_accounts' => 1500,
    'total_transfers' => 8234,
    'confirmed_transfers' => 8200,
    'pending_transfers' => 34,
    'total_value' => 42500000.00,
    'providers' => [
        'flutterwave' => 1200,
        'monipoint' => 300,
    ],
]
```

### Health Checks

```php
$healthy = VirtualAccounts::driver('flutterwave')->healthCheck();

if (!$healthy) {
    // Alert operations team
    // Switch to backup provider
}
```

## Production Checklist

- [ ] Queue workers running (`supervisord` recommended)
- [ ] Webhook URLs configured in provider dashboards
- [ ] Webhook secrets stored securely in `.env`
- [ ] Database indices created (migrations handle this)
- [ ] Reconciliation scheduled (auto-configured)
- [ ] Monitoring alerts configured
- [ ] Event listeners implemented for `DepositConfirmed`
- [ ] Backup provider configured
- [ ] Test webhooks sent from provider dashboards
- [ ] Rate limiting configured for webhook endpoints

## Documentation

Comprehensive documentation is available in the `docs/` directory:

- [Getting Started](docs/GETTING_STARTED.md) - Installation and basic usage
- [API Reference](docs/API_REFERENCE.md) - Complete API documentation
- [Architecture](docs/ARCHITECTURE.md) - Design principles and architecture
- [Security](docs/SECURITY.md) - Security best practices

## Testing

Run the test suite:

```bash
composer test
```

Or with coverage:

```bash
composer test-coverage
```

## Contributing

Contributions are welcome! Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details.

## Support

This package is open-source and community-maintained. For issues:
- GitHub Issues: [payzephyr/laravel-virtual-account-manager](https://github.com/payzephyr/laravel-virtual-account-manager/issues)
- Documentation: See `docs/` directory

## License

MIT License - See LICENSE file

---

**Built for African FinTech Infrastructure**

This package is designed to power real fintech systems while remaining:
- Fully open-source
- Safe by default
- Extensible by design
- Production-minded
- Compliant with provider terms

---

**Built for African FinTech Infrastructure**

This package is designed to power real fintech systems while remaining:
- Fully open-source
- Safe by default
- Extensible by design
- Production-minded
- Compliant with provider terms