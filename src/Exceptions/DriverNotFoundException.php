<?php

declare(strict_types=1);

namespace PayZephyr\VirtualAccounts\Exceptions;

final class DriverNotFoundException extends VirtualAccountException
{
    public static function forProvider(string $provider): self
    {
        return new self("Driver not found for provider: $provider");
    }
}