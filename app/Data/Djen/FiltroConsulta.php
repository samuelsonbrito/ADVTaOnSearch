<?php

namespace App\Data\Djen;

use Carbon\CarbonImmutable;

/**
 * Filtros da aba Consulta, já validados e normalizados.
 *
 * Os nomes são do app (e da query string da nossa URL). A conversão para os
 * parâmetros da Comunica API fica no gateway.
 */
final readonly class FiltroConsulta
{
    public function __construct(
        public ?string $numeroOab = null,
        public ?string $ufOab = null,
        public ?string $nomeParte = null,
        public ?string $nomeAdvogado = null,
        public ?string $numeroProcesso = null,
        public ?string $tribunal = null,
        public ?CarbonImmutable $dataInicio = null,
        public ?CarbonImmutable $dataFim = null,
        public int $pagina = 1,
        public int $porPagina = 20,
    ) {}

    /** Há pelo menos um critério de busca (datas e página não contam). */
    public function temCriterio(): bool
    {
        return $this->numeroOab !== null
            || $this->nomeParte !== null
            || $this->nomeAdvogado !== null
            || $this->numeroProcesso !== null
            || $this->tribunal !== null;
    }

    /**
     * Representação para a query string da nossa URL.
     *
     * @return array<string, string|int|null>
     */
    public function toQuery(): array
    {
        return [
            'oab' => $this->numeroOab,
            'uf' => $this->ufOab,
            'parte' => $this->nomeParte,
            'advogado' => $this->nomeAdvogado,
            'processo' => $this->numeroProcesso,
            'tribunal' => $this->tribunal,
            'inicio' => $this->dataInicio?->toDateString(),
            'fim' => $this->dataFim?->toDateString(),
            'pagina' => $this->pagina,
        ];
    }
}
