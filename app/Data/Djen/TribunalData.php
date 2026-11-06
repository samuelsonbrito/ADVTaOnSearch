<?php

namespace App\Data\Djen;

final readonly class TribunalData
{
    public function __construct(
        public string $sigla,
        public string $nome,
    ) {}
}
