<?php

namespace App\Services\Djen\Exceptions;

use RuntimeException;

/**
 * Base de todas as falhas de comunicação com a Comunica API (DJEN/CNJ).
 *
 * Quem chama o DjenClient pode capturar só esta classe para exibir a
 * mensagem amigável de "API do CNJ indisponível".
 */
abstract class DjenException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?int $status = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $status ?? 0, $previous);
    }
}
