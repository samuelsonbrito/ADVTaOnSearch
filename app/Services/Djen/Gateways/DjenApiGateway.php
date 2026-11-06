<?php

namespace App\Services\Djen\Gateways;

use App\Data\Djen\FiltroConsulta;
use App\Data\Djen\ResultadoConsulta;
use App\Services\Djen\Contracts\ComunicacaoGateway;
use App\Services\Djen\DjenClient;
use App\Services\Djen\Exceptions\DjenMappingPendingException;

/**
 * Gateway da Comunica API.
 *
 * PENDENTE: a conversão FiltroConsulta → parâmetros da API e resposta →
 * ComunicacaoData será escrita a partir da fixture real
 * (php artisan djen:capturar-fixture). Até lá, lança
 * DjenMappingPendingException, que a interface exibe como aviso.
 */
final class DjenApiGateway implements ComunicacaoGateway
{
    public function __construct(
        private readonly DjenClient $client,
    ) {}

    public function buscar(FiltroConsulta $filtro): ResultadoConsulta
    {
        throw new DjenMappingPendingException;
    }

    public function tribunais(): array
    {
        throw new DjenMappingPendingException;
    }
}
