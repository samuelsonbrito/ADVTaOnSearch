<?php

namespace App\Enums;

enum StatusExportacao: string
{
    case Pendente = 'pendente';
    case Processando = 'processando';
    case Concluida = 'concluida';
    case Falhou = 'falhou';

    public function rotulo(): string
    {
        return match ($this) {
            self::Pendente => 'Na fila',
            self::Processando => 'Gerando',
            self::Concluida => 'Pronta',
            self::Falhou => 'Falhou',
        };
    }

    public function emAndamento(): bool
    {
        return $this === self::Pendente || $this === self::Processando;
    }
}
