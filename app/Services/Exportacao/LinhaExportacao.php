<?php

namespace App\Services\Exportacao;

use App\Data\Djen\ComunicacaoData;

/** Uma comunicação a exportar, com o alvo quando vem do monitoramento. */
final readonly class LinhaExportacao
{
    public function __construct(
        public ComunicacaoData $comunicacao,
        public ?string $alvo = null,
        public ?bool $lida = null,
    ) {}
}
