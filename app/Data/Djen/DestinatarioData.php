<?php

namespace App\Data\Djen;

/** Destinatário (parte) de uma comunicação (contrato do app, não da API). */
final readonly class DestinatarioData
{
    public function __construct(
        public string $nome,
    ) {}
}
