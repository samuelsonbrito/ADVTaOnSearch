<?php

namespace App\Enums;

use App\Data\Djen\FiltroConsulta;
use Carbon\CarbonImmutable;

enum TipoAlvo: string
{
    case Oab = 'oab';
    case Parte = 'parte';
    case Processo = 'processo';

    public function rotulo(): string
    {
        return match ($this) {
            self::Oab => 'OAB',
            self::Parte => 'Nome da parte',
            self::Processo => 'Número do processo',
        };
    }

    /** Filtro de consulta para monitorar este alvo num período. */
    public function filtro(string $valor, ?string $uf, CarbonImmutable $inicio, CarbonImmutable $fim, int $pagina, int $porPagina): FiltroConsulta
    {
        return new FiltroConsulta(
            numeroOab: $this === self::Oab ? $valor : null,
            ufOab: $this === self::Oab ? $uf : null,
            nomeParte: $this === self::Parte ? $valor : null,
            numeroProcesso: $this === self::Processo ? $valor : null,
            dataInicio: $inicio,
            dataFim: $fim,
            pagina: $pagina,
            porPagina: $porPagina,
        );
    }
}
