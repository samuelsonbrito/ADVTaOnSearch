<?php

namespace App\Support;

/**
 * Número de inscrição na OAB. Pode ter letra no fim, com ou sem hífen
 * (visto na API: 95187, 11811N, 20413-O, 309-B, 83498A).
 */
final class Oab
{
    public const REGEX = '/^\d{1,8}(-?[A-Z])?$/';

    /** Maiúsculas, sem espaços, pontos, barras nem "OAB". */
    public static function normalizar(string $valor): string
    {
        $v = mb_strtoupper(trim($valor));
        $v = (string) preg_replace('/^OAB\s*/', '', $v);

        return (string) preg_replace('/[\s.\/]/', '', $v);
    }
}
