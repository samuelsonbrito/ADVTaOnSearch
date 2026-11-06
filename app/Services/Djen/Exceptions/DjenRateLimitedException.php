<?php

namespace App\Services\Djen\Exceptions;

/** HTTP 429 persistente: a API pediu para esperar antes de novas chamadas. */
final class DjenRateLimitedException extends DjenException
{
    public function __construct(
        string $message,
        public readonly ?int $retryAfterSeconds = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 429, $previous);
    }
}
