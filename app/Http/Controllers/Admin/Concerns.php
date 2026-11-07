<?php

namespace App\Http\Controllers\Admin;

use App\Support\Admin\Periodo;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/** Formatos comuns às telas da área administrativa. */
trait Concerns
{
    /**
     * @param  LengthAwarePaginator<int, mixed>  $p
     * @return array{pagina: int, porPagina: int, total: int, ultimaPagina: int}
     */
    protected static function meta(LengthAwarePaginator $p): array
    {
        return [
            'pagina' => $p->currentPage(),
            'porPagina' => $p->perPage(),
            'total' => $p->total(),
            'ultimaPagina' => $p->lastPage(),
        ];
    }

    /** Data/hora ISO no fuso de exibição. */
    protected static function quando(?CarbonInterface $data): ?string
    {
        return $data?->setTimezone(Periodo::fuso())->toIso8601String();
    }

    /** @return array<string, string> */
    protected static function presets(): array
    {
        return Periodo::PRESETS;
    }
}
