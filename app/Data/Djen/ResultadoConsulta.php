<?php

namespace App\Data\Djen;

final readonly class ResultadoConsulta
{
    /**
     * @param  list<ComunicacaoData>  $comunicacoes
     */
    public function __construct(
        public array $comunicacoes,
        public int $total,
        public int $pagina,
        public int $porPagina,
    ) {}

    public function ultimaPagina(): int
    {
        return max(1, (int) ceil($this->total / max(1, $this->porPagina)));
    }
}
