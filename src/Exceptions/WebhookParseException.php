<?php

declare(strict_types=1);

namespace PayZephyr\VirtualAccounts\Exceptions;

final class WebhookParseException extends VirtualAccountException
{
    public static function invalidPayload(string $reason): self
    {
        return new self("Invalid webhook payload: $reason");
    }

    public static function missingField(string $field): self
    {
        return new self("Required field missing from webhook: $field");
    }
}