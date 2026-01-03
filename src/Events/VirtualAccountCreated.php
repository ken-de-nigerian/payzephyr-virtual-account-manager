<?php

declare(strict_types=1);

namespace PayZephyr\VirtualAccounts\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use PayZephyr\VirtualAccounts\DataObjects\VirtualAccountDTO;

final class VirtualAccountCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly VirtualAccountDTO $account,
        public readonly string $customerId,
    ) {}
}