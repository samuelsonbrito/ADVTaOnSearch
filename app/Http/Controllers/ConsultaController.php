<?php

namespace App\Http\Controllers;

use App\Http\Requests\ConsultaRequest;
use App\Http\Resources\Djen\ResultadoConsultaResource;
use App\Http\Resources\Djen\TribunalResource;
use App\Services\Djen\Contracts\ComunicacaoGateway;
use App\Services\Djen\Exceptions\DjenBlockedException;
use App\Services\Djen\Exceptions\DjenException;
use App\Services\Djen\Exceptions\DjenMappingPendingException;
use App\Services\Djen\Exceptions\DjenRateLimitedException;
use App\Services\Djen\Exceptions\DjenUnavailableException;
use Inertia\Inertia;
use Inertia\Response;

/** Aba Consulta: pública, filtros na query string. */
class ConsultaController extends Controller
{
    public function __invoke(ConsultaRequest $request, ComunicacaoGateway $gateway): Response
    {
        $filtro = $request->filtro();
        $resultados = null;
        $erro = null;

        if (! $request->invalido() && $filtro->temCriterio()) {
            try {
                $resultados = (new ResultadoConsultaResource($gateway->buscar($filtro)))->resolve($request);
            } catch (DjenException $e) {
                $erro = self::erro($e);
            }
        }

        $props = [
            'filtros' => [
                'oab' => $request->query('oab'),
                'uf' => $request->query('uf'),
                'parte' => $request->query('parte'),
                'advogado' => $request->query('advogado'),
                'processo' => $request->query('processo'),
                'tribunal' => $request->query('tribunal'),
                'inicio' => $request->query('inicio'),
                'fim' => $request->query('fim'),
            ],
            'resultados' => $resultados,
            'erro' => $erro,
            'tribunais' => fn (): array => $this->tribunais($gateway),
            'ufs' => ConsultaRequest::UFS,
        ];

        if ($request->invalido()) {
            $props['errors'] = $request->erros();
        }

        return Inertia::render('Consulta/Index', $props);
    }

    /**
     * @return list<array{sigla: string, nome: string}>
     */
    private function tribunais(ComunicacaoGateway $gateway): array
    {
        try {
            return TribunalResource::collection($gateway->tribunais())->resolve();
        } catch (DjenException) {
            return [];
        }
    }

    /**
     * Espelhado em resources/js/types/djen.ts (ErroConsulta).
     *
     * @return array{tipo: string, mensagem: string}
     */
    private static function erro(DjenException $e): array
    {
        if (! $e instanceof DjenMappingPendingException) {
            report($e);
        }

        return match (true) {
            $e instanceof DjenMappingPendingException => ['tipo' => 'pendente', 'mensagem' => $e->getMessage()],
            $e instanceof DjenRateLimitedException => ['tipo' => 'limite', 'mensagem' => 'Muitas consultas ao CNJ em pouco tempo. Aguarde um minuto e tente de novo.'],
            $e instanceof DjenBlockedException => ['tipo' => 'bloqueado', 'mensagem' => 'O CNJ recusou o acesso a partir deste servidor. Tente novamente mais tarde.'],
            $e instanceof DjenUnavailableException => ['tipo' => 'indisponivel', 'mensagem' => 'O serviço do CNJ está fora do ar ou muito lento no momento. Tente novamente em alguns minutos.'],
            default => ['tipo' => 'inesperado', 'mensagem' => 'Não foi possível concluir a consulta ao CNJ. Tente novamente.'],
        };
    }
}
