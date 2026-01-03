<?php

declare(strict_types=1);

namespace PayZephyr\VirtualAccounts\Facades;

use Illuminate\Support\Facades\Facade;
use PayZephyr\VirtualAccounts\Contracts\VirtualAccountProvider;
use PayZephyr\VirtualAccounts\DataObjects\VirtualAccountDTO;
use PayZephyr\VirtualAccounts\Services\AccountBuilder;

/**
 * @method static AccountBuilder assignTo(string $customerId)
 * @method static VirtualAccountDTO|null getAccount(string $customerId, ?string $provider = null)
 * @method static bool deactivateAccount(string $accountNumber)
 * @method static VirtualAccountProvider driver(?string $name = null)
 *
 * @see \PayZephyr\VirtualAccounts\Services\VirtualAccountManager
 */
final class VirtualAccounts extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'virtual-accounts';
    }
}
