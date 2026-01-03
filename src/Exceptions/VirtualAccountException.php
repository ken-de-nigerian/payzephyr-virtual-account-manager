<?php

declare(strict_types=1);

namespace PayZephyr\VirtualAccounts\Exceptions;

use Exception;
use Throwable;

/**
 * Base virtual account exception.
 */
class VirtualAccountException extends Exception
{
    /** @var array<string, mixed> */
    protected array $context = [];

    /**
     * Set context.
     *
     * @param  array<string, mixed>  $context
     */
    public function setContext(array $context): static
    {
        $this->context = $context;

        return $this;
    }

    /**
     * Get context.
     *
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Create exception with context.
     *
     * @param  array<string, mixed>  $context
     */
    public static function withContext(string $message, array $context = [], ?Throwable $previous = null): static
    {
        return (new static($message, 0, $previous))->setContext($context);
    }

    public static function accountNotFound(string $identifier): self
    {
        return new self("Virtual account not found: $identifier");
    }

    public static function accountAlreadyExists(string $customerId, string $provider): self
    {
        return new self("Account already exists for customer $customerId with provider $provider");
    }

    public static function providerError(string $provider, string $message): self
    {
        return new self("Provider $provider error: $message");
    }
}
