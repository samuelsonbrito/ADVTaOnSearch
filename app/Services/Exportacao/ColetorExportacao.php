<?php

namespace App\Services\Exportacao;

use App\Data\Djen\FiltroConsulta;
use App\Enums\OrigemExportacao;
use App\Models\Alerta;
use App\Models\Exportacao;
use App\Services\Djen\Contracts\ComunicacaoGateway;
use App\Services\Djen\Exceptions\DjenException;

/**
 * Transforma os parâmetros de uma exportação nas linhas a exportar.
 *
 * parametros (origem consulta):
 *   filtros: query string da Consulta (FiltroConsulta::toQuery, sem pagina)
 *   todos: bool — todas as páginas do resultado
 *   selecao: list<array{pagina: int, hashes: list<string>}>
 *
 * parametros (origem monitoramento):
 *   filtros: {alvo: int|null, status: nao_lidas|lidas|todas}
 *   todos: bool
 *   selecao: list<int> (ids de alertas)
 *
 * Sempre limitado a djen.exportacao.max_itens.
 */
final class ColetorExportacao
{
    public function __construct(
        private readonly ComunicacaoGateway $gateway,
    ) {}

    /**
     * @return list<LinhaExportacao>
     *
     * @throws DjenException
     */
    public function linhas(Exportacao $exportacao): array
    {
        $max = (int) config('djen.exportacao.max_itens');

        $linhas = match ($exportacao->origem) {
            OrigemExportacao::Consulta => $this->daConsulta($exportacao->parametros, $max),
            OrigemExportacao::Monitoramento => $this->doMonitoramento($exportacao, $max),
        };

        return array_slice($linhas, 0, $max);
    }

    /**
     * @param  array<string, mixed>  $p
     * @return list<LinhaExportacao>
     */
    private function daConsulta(array $p, int $max): array
    {
        $filtros = (array) ($p['filtros'] ?? []);
        $linhas = [];

        if ($p['todos'] ?? false) {
            $porPagina = 100;

            for ($pagina = 1; count($linhas) < $max; $pagina++) {
                $r = $this->gateway->buscar(FiltroConsulta::fromQuery($filtros, $pagina, $porPagina));

                foreach ($r->comunicacoes as $c) {
                    $linhas[] = new LinhaExportacao($c);
                }

                if ($pagina >= $r->ultimaPagina()) {
                    break;
                }
            }

            return $linhas;
        }

        // Seleção: busca de novo cada página (o DjenClient usa cache) e mantém
        // a ordem dos resultados. Hashes que sumiram da fonte são ignorados.
        $porPagina = (int) config('djen.itens_por_pagina');

        foreach ((array) ($p['selecao'] ?? []) as $grupo) {
            $hashes = array_flip((array) $grupo['hashes']);
            $r = $this->gateway->buscar(FiltroConsulta::fromQuery($filtros, (int) $grupo['pagina'], $porPagina));

            foreach ($r->comunicacoes as $c) {
                if (isset($hashes[$c->hash])) {
                    $linhas[] = new LinhaExportacao($c);
                }
            }
        }

        return $linhas;
    }

    /**
     * @return list<LinhaExportacao>
     */
    private function doMonitoramento(Exportacao $e, int $max): array
    {
        $p = $e->parametros;
        $filtros = (array) ($p['filtros'] ?? []);

        $query = Alerta::query()
            ->doUsuario($e->user) // revalida a posse no momento de gerar
            ->join('communications', 'communications.id', '=', 'communication_target.communication_id')
            ->select('communication_target.*')
            ->with(['communication', 'target'])
            ->orderByDesc('communications.data_disponibilizacao')
            ->orderByDesc('communication_target.id')
            ->limit($max);

        if ($p['todos'] ?? false) {
            $status = $filtros['status'] ?? 'todas';
            $query
                ->when($filtros['alvo'] ?? null, fn ($q, $alvo) => $q->where('monitoring_target_id', $alvo))
                ->when($status === 'nao_lidas', fn ($q) => $q->whereNull('lida_em'))
                ->when($status === 'lidas', fn ($q) => $q->whereNotNull('lida_em'));
        } else {
            $query->whereIn('communication_target.id', array_map('intval', (array) ($p['selecao'] ?? [])));
        }

        return $query->get()
            ->map(fn (Alerta $a) => new LinhaExportacao($a->communication->toData(), $a->target->descricao(), $a->lida_em !== null))
            ->all();
    }
}
