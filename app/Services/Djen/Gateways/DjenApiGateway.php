<?php

namespace App\Services\Djen\Gateways;

use App\Data\Djen\FiltroConsulta;
use App\Data\Djen\ResultadoConsulta;
use App\Data\Djen\TribunalData;
use App\Services\Djen\Contracts\ComunicacaoGateway;
use App\Services\Djen\DjenClient;
use App\Services\Djen\Mapping\ComunicacaoApiMapper;

/**
 * Comunica API do CNJ, chamada pelo nosso backend (o navegador nunca fala
 * com o CNJ). Exige servidor com IP no Brasil.
 *
 * Parâmetros de GET /comunicacao: `itensPorPagina` confirmado num request
 * real; os de filtro seguem o padrão informado no escopo do projeto e na
 * documentação pública, e são conferidos no servidor por
 * `php artisan djen:verificar-filtros`.
 */
final class DjenApiGateway implements ComunicacaoGateway
{
    public function __construct(
        private readonly DjenClient $client,
        private readonly ComunicacaoApiMapper $mapper,
    ) {}

    public function buscar(FiltroConsulta $filtro): ResultadoConsulta
    {
        $resposta = $this->client->getCached('comunicacao', self::parametros($filtro));

        return $this->mapper->resultado($resposta->json, $filtro->pagina, $filtro->porPagina, $resposta->fromCache);
    }

    public function tribunais(): array
    {
        return array_map(
            fn (string $sigla, string $nome): TribunalData => new TribunalData($sigla, $nome),
            array_keys(config('tribunais')),
            array_values(config('tribunais')),
        );
    }

    /**
     * @return array<string, string|int|null>
     */
    public static function parametros(FiltroConsulta $f): array
    {
        return [
            'numeroOab' => $f->numeroOab,
            'ufOab' => $f->ufOab,
            'nomeParte' => $f->nomeParte,
            'nomeAdvogado' => $f->nomeAdvogado,
            'numeroProcesso' => $f->numeroProcesso,
            'siglaTribunal' => $f->tribunal,
            'dataDisponibilizacaoInicio' => $f->dataInicio?->toDateString(),
            'dataDisponibilizacaoFim' => $f->dataFim?->toDateString(),
            'pagina' => $f->pagina,
            'itensPorPagina' => $f->porPagina,
        ];
    }
}
