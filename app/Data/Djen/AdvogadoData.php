<?php

namespace App\Data\Djen;

/** Advogado vinculado a uma comunicação (contrato do app, não da API). */
final readonly class AdvogadoData
{
    public function __construct(
        public string $nome,
        public ?string $numeroOab = null,
        public ?string $ufOab = null,
    ) {}
}
