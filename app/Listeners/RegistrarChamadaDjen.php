<?php

namespace App\Listeners;

use App\Events\ChamadaDjenConcluida;
use App\Services\Rastreio\Rastreador;

class RegistrarChamadaDjen
{
    public function __construct(
        private readonly Rastreador $rastreador,
    ) {}

    public function handle(ChamadaDjenConcluida $e): void
    {
        $this->rastreador->chamadaDjen(
            $e->endpoint, $e->parametros, $e->status, $e->resultado,
            $e->duracaoMs, $e->itens, $e->ratelimitRestante, $e->erro,
        );
    }
}
