# Build Prompt — Laravel Virtual Account Manager

> **Role:** You are a senior Laravel & FinTech infrastructure engineer with experience building payment systems, webhook-driven architectures, and provider abstractions in African and global fintech ecosystems.

---

## 🎯 Objective

Design and implement a **Laravel Virtual Account Manager** package using the **same architectural principles, folder structure, and coding standards as PayZephyr**.

This package must act as a **provider orchestration and normalization layer**, NOT a bank or payment processor.

It manages:
- Virtual account creation via third-party providers
- Deposit detection via webhooks
- Normalized reconciliation and logging
- Event-driven settlement triggers

---

## 🚫 Explicit Non-Goals (Important)

- Do NOT move or hold money
- Do NOT act as a bank
- Do NOT issue virtual accounts directly
- Do NOT bypass provider terms or compliance

This package **only orchestrates providers and normalizes events**.

---

## 🧠 Architectural Philosophy (Same as PayZephyr)

- Driver-based provider architecture
- Unified internal API regardless of provider
- Idempotent, event-driven processing
- Webhook-first design
- Strong logging and traceability
- Safe-by-default, extensible-by-design

---

## 📦 Package Identity

- **Package name:** laravel-virtual-account-manager
- **Namespace:** `PayZephyr\VirtualAccounts`
- **Composer style:** identical to PayZephyr
- **Providers implemented via drivers**

---

## 🗂 Folder Structure (Mirror PayZephyr)

```
src/
├── Contracts/
│   └── VirtualAccountProvider.php
├── Drivers/
│   ├── FlutterwaveDriver.php
│   ├── MoniepointDriver.php
│   └── ProvidusDriver.php
├── DataObjects/
│   ├── VirtualAccountDTO.php
│   ├── IncomingTransferDTO.php
├── Models/
│   ├── VirtualAccount.php
│   ├── IncomingTransfer.php
│   └── ProviderWebhookLog.php
├── Events/
│   └── DepositConfirmed.php
├── Services/
│   ├── VirtualAccountManager.php
│   ├── DepositDetector.php
│   └── ReconciliationService.php
├── Facades/
│   └── VirtualAccounts.php
├── Console/
│   └── ReconcileVirtualAccountsCommand.php
├── Http/
│   └── Controllers/WebhookController.php
└── VirtualAccountServiceProvider.php
```

---

## 1️⃣ Provider Contract (Critical)

```php
interface VirtualAccountProvider
{
    public function createAccount(array $payload): VirtualAccountDTO;

    public function verifyWebhook(Request $request): bool;

    public function parseIncomingTransfer(Request $request): IncomingTransferDTO;
}
```

---

## 2️⃣ Unified API Example

```php
VirtualAccounts::assignTo($user)
    ->using('flutterwave')
    ->create();
```

---

## 3️⃣ Webhook Processing Flow

1. Resolve provider driver  
2. Verify webhook signature  
3. Persist raw payload  
4. Normalize incoming transfer  
5. Ensure idempotency  
6. Dispatch `DepositConfirmed` event  

---

## 4️⃣ Reconciliation

Nightly command:

```bash
php artisan virtual-accounts:reconcile
```

Detects:
- Duplicate transfers
- Missing confirmations
- Provider inconsistencies

---

## 🎯 Quality Bar

- Fintech-grade correctness
- Event-driven
- Traceable
- Provider-agnostic
- Production-minded

Build this package as if it will power **real African fintech systems**, while remaining fully open-source and safe.
