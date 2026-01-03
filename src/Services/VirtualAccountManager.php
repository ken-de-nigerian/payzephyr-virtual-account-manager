<?php

declare(strict_types=1);

namespace PayZephyr\VirtualAccounts\Services;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PayZephyr\VirtualAccounts\Contracts\VirtualAccountProvider;
use PayZephyr\VirtualAccounts\DataObjects\AccountAssignmentDTO;
use PayZephyr\VirtualAccounts\DataObjects\VirtualAccountDTO;
use PayZephyr\VirtualAccounts\Events\VirtualAccountCreated;
use PayZephyr\VirtualAccounts\Exceptions\DriverNotFoundException;
use PayZephyr\VirtualAccounts\Exceptions\VirtualAccountException;
use PayZephyr\VirtualAccounts\Models\VirtualAccount;
use PayZephyr\VirtualAccounts\Services\DriverFactory;

/**
 * Virtual Account Manager
 *
 * Core service for managing virtual accounts across multiple providers.
 */
final class VirtualAccountManager
{

    /** @var array<string, VirtualAccountProvider> */
    protected array $drivers = [];

    /** @var array<string, mixed> */
    protected array $config;

    protected DriverFactory $driverFactory;

    public function __construct(?DriverFactory $driverFactory = null)
    {
        $this->config = Config::get('virtual-accounts', []);
        $this->driverFactory = $driverFactory ?? app(DriverFactory::class);
    }

    /**
     * Get provider driver instance.
     * @throws DriverNotFoundException
     */
    public function driver(?string $name = null): VirtualAccountProvider
    {
        $name = $name ?? $this->getDefaultProvider();

        if (isset($this->drivers[$name])) {
            return $this->drivers[$name];
        }

        $providerConfig = $this->config['providers'][$name] ?? null;

        if (!$providerConfig || !($providerConfig['enabled'] ?? true)) {
            throw new DriverNotFoundException("Provider [$name] not found or disabled");
        }

        $this->drivers[$name] = $this->driverFactory->create($name, $providerConfig);

        return $this->drivers[$name];
    }

    /**
     * Assign virtual account to customer.
     */
    public function assignAccount(
        AccountAssignmentDTO $assignment,
        ?string $provider = null
    ): VirtualAccountDTO {
        $provider = $provider ?? $this->getDefaultProvider();

        // Check for existing active account
        $existing = VirtualAccount::where('customer_id', $assignment->customerId)
            ->where('provider', $provider)
            ->where('status', 'active')
            ->first();

        if ($existing) {
            return VirtualAccountDTO::fromArray($existing->toArray());
        }

        return DB::transaction(function () use ($assignment, $provider) {
            $driver = $this->driver($provider);
            $accountDTO = $driver->createAccount($assignment->toArray());

            // Persist to database
            VirtualAccount::create([
                'customer_id' => $assignment->customerId,
                'account_number' => $accountDTO->accountNumber,
                'account_name' => $accountDTO->accountName,
                'bank_name' => $accountDTO->bankName,
                'bank_code' => $accountDTO->bankCode,
                'provider_reference' => $accountDTO->providerReference,
                'provider' => $provider,
                'currency' => $accountDTO->currency,
                'status' => 'active',
                'metadata' => $accountDTO->metadata,
            ]);

            Log::info('Virtual account created', [
                'customer_id' => $assignment->customerId,
                'account_number' => $accountDTO->accountNumber,
                'provider' => $provider,
            ]);

            // Dispatch event
            VirtualAccountCreated::dispatch($accountDTO, $assignment->customerId);

            return $accountDTO;
        });
    }

    /**
     * Get customer's virtual account.
     */
    public function getAccount(string $customerId, ?string $provider = null): ?VirtualAccountDTO
    {
        $query = VirtualAccount::where('customer_id', $customerId)
            ->where('status', 'active');

        if ($provider) {
            $query->where('provider', $provider);
        }

        $account = $query->first();

        return $account ? VirtualAccountDTO::fromArray($account->toArray()) : null;
    }

    /**
     * Deactivate virtual account.
     */
    public function deactivateAccount(string $accountNumber): bool
    {
        $account = VirtualAccount::where('account_number', $accountNumber)->first();

        if (!$account) {
            throw new VirtualAccountException("Account [$accountNumber] not found");
        }

        return $account->update(['status' => 'inactive']);
    }

    /**
     * Get all enabled providers.
     *
     * @return array<string>
     */
    public function getEnabledProviders(): array
    {
        $providers = [];

        foreach ($this->config['providers'] ?? [] as $name => $config) {
            if ($config['enabled'] ?? true) {
                $providers[] = $name;
            }
        }

        return $providers;
    }

    /**
     * Get default provider name.
     */
    protected function getDefaultProvider(): string
    {
        return $this->config['default'] ?? 'flutterwave';
    }


    /**
     * Fluent API builder for account assignment.
     */
    public function assignTo(string $customerId): AccountBuilder
    {
        return new AccountBuilder($this, $customerId);
    }
}

/**
 * Fluent builder for virtual account assignment.
 */
final class AccountBuilder
{
    protected VirtualAccountManager $manager;
    protected array $data = [];
    protected ?string $provider = null;

    public function __construct(VirtualAccountManager $manager, string $customerId)
    {
        $this->manager = $manager;
        $this->data['customer_id'] = $customerId;
    }

    public function name(string $name): self
    {
        $this->data['customer_name'] = $name;
        return $this;
    }

    public function email(string $email): self
    {
        $this->data['customer_email'] = $email;
        return $this;
    }

    public function phone(string $phone): self
    {
        $this->data['customer_phone'] = $phone;
        return $this;
    }

    public function bvn(string $bvn): self
    {
        $this->data['bvn'] = $bvn;
        return $this;
    }

    public function currency(string $currency): self
    {
        $this->data['currency'] = $currency;
        return $this;
    }

    public function preferredBank(string $bank): self
    {
        $this->data['preferred_bank'] = $bank;
        return $this;
    }

    public function using(string $provider): self
    {
        $this->provider = $provider;
        return $this;
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function metadata(array $metadata): self
    {
        $this->data['metadata'] = $metadata;
        return $this;
    }

    /**
     * Execute account creation.
     */
    public function create(): VirtualAccountDTO
    {
        $assignment = new AccountAssignmentDTO(
            customerId: $this->data['customer_id'],
            customerName: $this->data['customer_name'],
            customerEmail: $this->data['customer_email'],
            customerPhone: $this->data['customer_phone'] ?? null,
            bvn: $this->data['bvn'] ?? null,
            currency: $this->data['currency'] ?? 'NGN',
            preferredBank: $this->data['preferred_bank'] ?? null,
            metadata: $this->data['metadata'] ?? [],
        );

        return $this->manager->assignAccount($assignment, $this->provider);
    }
}