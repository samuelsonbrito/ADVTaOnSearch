<?php

namespace App\Services\Djen\Contracts;

use App\Data\Djen\FiltroConsulta;
use App\Data\Djen\ResultadoConsulta;
use App\Data\Djen\TribunalData;
use App\Services\Djen\Exceptions\DjenException;

/**
 * Fonte de comunicações processuais para o app.
 *
 * Implementações: "api" (DjenApiGateway: Comunica API via DjenClient +
 * ComunicacaoApiMapper) e "fake" (FakeComunicacaoGateway: dados de exemplo).
 */
interface ComunicacaoGateway
{
    /**
     * @throws DjenException
     */
    public function buscar(FiltroConsulta $filtro): ResultadoConsulta;

    /**
     * @return list<TribunalData>
     *
     * @throws DjenException
     */
    public function tribunais(): array;
}
