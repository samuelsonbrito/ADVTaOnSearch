<?php

namespace App\Enums;

enum OrigemExportacao: string
{
    case Consulta = 'consulta';
    case Monitoramento = 'monitoramento';

    public function rotulo(): string
    {
        return match ($this) {
            self::Consulta => 'Consulta',
            self::Monitoramento => 'Monitoramento',
        };
    }
}
