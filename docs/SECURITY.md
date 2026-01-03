# Security Guide

Security best practices for using the Virtual Account Manager package.

## Webhook Security

### Signature Verification

All webhooks are signature-verified before processing:

```php
public function verifyWebhook(Request $request): bool
{
    $secretHash = $this->config['webhook_secret'] ?? null;
    $signature = $request->header('verif-hash');
    
    return hash_equals($secretHash, $signature);
}
```

**Best Practices:**
- Always set `webhook_secret` in configuration
- Never disable webhook verification in production
- Use strong, randomly generated secrets
- Rotate secrets periodically

### Rate Limiting

Webhook endpoints are rate-limited by default:

```php
Route::group([
    'middleware' => ['api', 'throttle:120,1'],
], function () {
    Route::post('/{provider}', [WebhookController::class, 'handle']);
});
```

**Configuration:**
```php
'webhook' => [
    'rate_limit' => '120,1', // 120 requests per minute
],
```

### Timestamp Validation

Webhook timestamps are validated to prevent replay attacks:

```php
protected function validateWebhookTimestamp(array $payload, int $toleranceSeconds = 300): bool
{
    $timestamp = $this->extractWebhookTimestamp($payload);
    $timeDifference = abs(time() - $timestamp);
    
    return $timeDifference <= $toleranceSeconds;
}
```

## Idempotency

### Transfer Idempotency

Every transfer has a unique idempotency key:

```php
$idempotencyKey = hash('sha256', "{$provider}_{$transactionReference}");
```

**Benefits:**
- Prevents duplicate processing
- Safe to retry webhooks
- No double-crediting possible

### Database Constraints

Unique constraints prevent duplicate transfers:

```php
Schema::create('incoming_transfers', function (Blueprint $table) {
    $table->unique('idempotency_key');
    // ...
});
```

## Log Sanitization

### Sensitive Data Redaction

All logs are automatically sanitized:

```php
protected $sensitiveKeys = [
    'password',
    'secret',
    'token',
    'api_key',
    'webhook_secret',
    'bvn',
    'account_number',
];
```

**Example:**
```php
// Before sanitization
['api_key' => 'FLWSECK_TEST_xxx', 'amount' => 5000]

// After sanitization
['api_key' => '[REDACTED]', 'amount' => 5000]
```

### Token Pattern Detection

Long strings matching token patterns are redacted:

```php
if (preg_match('/^(sk_|pk_|whsec_|Bearer\s+)/i', $data)) {
    return '[REDACTED_TOKEN]';
}
```

## Configuration Security

### Environment Variables

Never commit secrets to version control:

```env
# ✅ Good: Use environment variables
FLUTTERWAVE_SECRET_KEY=${FLUTTERWAVE_SECRET_KEY}

# ❌ Bad: Hardcode in config
'secret_key' => 'FLWSECK_TEST_xxx',
```

### Configuration Validation

All drivers validate required configuration:

```php
protected function validateConfig(): void
{
    if (empty($this->config['secret_key'])) {
        throw new InvalidConfigurationException('Secret key is required');
    }
}
```

## Network Security

### HTTPS Only

Always use HTTPS in production:

```php
'base_url' => 'https://api.flutterwave.com/v3', // ✅
'base_url' => 'http://api.flutterwave.com/v3',  // ❌
```

### SSL Verification

SSL verification is enabled by default:

```php
$this->client = new Client([
    'verify' => !($this->config['testing_mode'] ?? false),
]);
```

## Database Security

### Sensitive Data Storage

- Account numbers are stored (required for reconciliation)
- API keys/secrets are NOT stored
- Webhook payloads are logged (sanitized in logs)

### Access Control

Implement proper access control:

```php
// ✅ Good: Check ownership
$account = VirtualAccount::where('customer_id', $userId)
    ->where('account_number', $accountNumber)
    ->firstOrFail();

// ❌ Bad: No ownership check
$account = VirtualAccount::where('account_number', $accountNumber)->first();
```

## Error Handling

### Error Messages

Never expose sensitive information in errors:

```php
// ✅ Good: Generic error
throw VirtualAccountException::providerError(
    $this->getName(),
    'Failed to create account'
);

// ❌ Bad: Exposes API key
throw new Exception("API key {$this->config['secret_key']} is invalid");
```

## Production Checklist

- [ ] Webhook secrets configured and secure
- [ ] HTTPS enabled for all API calls
- [ ] Rate limiting configured
- [ ] Log sanitization enabled
- [ ] Queue workers running (supervisord)
- [ ] Database backups configured
- [ ] Monitoring and alerting set up
- [ ] Error tracking configured (Sentry, etc.)
- [ ] Access control implemented
- [ ] Regular security audits scheduled

## Incident Response

### If Webhook Secret is Compromised

1. Rotate webhook secret immediately
2. Update configuration
3. Review webhook logs for suspicious activity
4. Check for unauthorized transfers
5. Notify affected customers if necessary

### If Duplicate Transfers Detected

1. Run reconciliation command
2. Review idempotency keys
3. Check for webhook replay attacks
4. Correct any duplicate credits/debits
5. Investigate root cause

## Compliance

### PCI DSS

Virtual accounts don't handle card data directly, but:
- Ensure secure transmission (HTTPS)
- Implement access controls
- Maintain audit logs

### GDPR

- Implement data retention policies
- Provide data export functionality
- Allow account deletion
- Log data access

