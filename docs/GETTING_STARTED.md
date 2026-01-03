# Getting Started with Virtual Account Manager

A comprehensive guide to getting started with the PayZephyr Virtual Account Manager package.

## Installation

### Step 1: Install via Composer

```bash
composer require payzephyr/laravel-virtual-account-manager
```

### Step 2: Publish Configuration

```bash
php artisan vendor:publish --tag=virtual-accounts-config
```

### Step 3: Publish Migrations

```bash
php artisan vendor:publish --tag=virtual-accounts-migrations
```

### Step 4: Run Migrations

```bash
php artisan migrate
```

## Configuration

### Environment Variables

Add the following to your `.env` file:

```env
# Default Provider
VIRTUAL_ACCOUNTS_DEFAULT_PROVIDER=flutterwave

# Flutterwave Configuration
FLUTTERWAVE_SECRET_KEY=your_secret_key_here
FLUTTERWAVE_WEBHOOK_SECRET=your_webhook_secret_here
FLUTTERWAVE_BASE_URL=https://api.flutterwave.com/v3
FLUTTERWAVE_ENABLED=true

# Moniepoint Configuration (Optional)
MONIPOINT_API_KEY=your_api_key
MONIPOINT_SECRET_KEY=your_secret_key
MONIPOINT_CONTRACT_CODE=your_contract_code
MONIPOINT_ENABLED=false

# Providus Configuration (Optional)
PROVIDUS_CLIENT_ID=your_client_id
PROVIDUS_CLIENT_SECRET=your_client_secret
PROVIDUS_ENABLED=false

# Webhook Configuration
VIRTUAL_ACCOUNTS_WEBHOOK_PATH=/virtual-accounts/webhook
VIRTUAL_ACCOUNTS_WEBHOOK_VERIFY=true
VIRTUAL_ACCOUNTS_WEBHOOK_RATE_LIMIT=120,1

# Reconciliation
VIRTUAL_ACCOUNTS_RECONCILIATION_ENABLED=true
VIRTUAL_ACCOUNTS_RECONCILIATION_SCHEDULE=daily
```

## Basic Usage

### Creating a Virtual Account

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

// Access account details
echo $account->accountNumber;  // 1234567890
echo $account->bankName;       // Wema Bank
echo $account->accountName;     // John Doe
```

### Retrieving an Account

```php
$account = VirtualAccounts::getAccount($userId);

if ($account) {
    echo "Account: {$account->accountNumber}";
    echo "Bank: {$account->bankName}";
}
```

### Deactivating an Account

```php
VirtualAccounts::deactivateAccount($accountNumber);
```

## Webhook Setup

### 1. Configure Webhook URLs

In your provider dashboard, set the webhook URL to:
- Flutterwave: `https://yourdomain.com/virtual-accounts/webhook/flutterwave`
- Moniepoint: `https://yourdomain.com/virtual-accounts/webhook/monipoint`
- Providus: `https://yourdomain.com/virtual-accounts/webhook/providus`

### 2. Listen for Deposits

Register event listeners in `app/Providers/EventServiceProvider.php`:

```php
use PayZephyr\VirtualAccounts\Events\DepositConfirmed;

protected $listen = [
    DepositConfirmed::class => [
        \App\Listeners\CreditUserWallet::class,
    ],
];
```

### 3. Create Event Listener

```php
namespace App\Listeners;

use PayZephyr\VirtualAccounts\Events\DepositConfirmed;
use Illuminate\Support\Facades\Log;

class CreditUserWallet
{
    public function handle(DepositConfirmed $event): void
    {
        $customerId = $event->getCustomerId();
        $amount = $event->getAmount();
        $transfer = $event->transfer;
        
        // Credit user wallet
        $user = User::find($customerId);
        $user->wallet->credit($amount);
        
        Log::info('User wallet credited', [
            'user_id' => $customerId,
            'amount' => $amount,
            'transfer_id' => $transfer->id,
        ]);
    }
}
```

### 4. Ensure Queue Workers Are Running

```bash
php artisan queue:work
```

## Reconciliation

The package includes automatic reconciliation to detect inconsistencies:

```bash
php artisan virtual-accounts:reconcile
```

This command:
- Detects duplicate transfers
- Identifies stale pending transfers (>24 hours)
- Finds missing confirmations
- Checks for provider inconsistencies

Reconciliation is automatically scheduled based on your configuration.

## Health Checks

Check provider health status:

```php
$healthy = VirtualAccounts::driver('flutterwave')->getCachedHealthCheck();

if (!$healthy) {
    // Switch to backup provider
    $account = VirtualAccounts::assignTo($userId)
        ->name($user->name)
        ->using('monipoint') // Fallback provider
        ->create();
}
```

Or use the health endpoint:

```bash
curl https://yourdomain.com/virtual-accounts/health
```

## Next Steps

- Read the [API Reference](API_REFERENCE.md)
- Learn about [Architecture](ARCHITECTURE.md)
- See [Advanced Usage](ADVANCED_USAGE.md)
- Check [Security Best Practices](SECURITY.md)

