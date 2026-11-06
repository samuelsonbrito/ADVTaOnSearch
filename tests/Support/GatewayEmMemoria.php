<?php

namespace Tests\Support;

use App\Data\Djen\AdvogadoData;
use App\Data\Djen\ComunicacaoData;
use App\Data\Djen\DestinatarioData;
use App\Data\Djen\FiltroConsulta;
use App\Data\Djen\ResultadoConsulta;
use App\Services\Djen\Contracts\ComunicacaoGateway;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * Gateway de teste: devolve as comunicações cadastradas (ignorando o
 * filtro, exceto a paginação) e registra os filtros recebidos.
 */
final class GatewayEmMemoria implements ComunicacaoGateway
{
    /** @var list<ComunicacaoData> */
    public array $comunicacoes = [];

    /** @var list<FiltroConsulta> */
    public array $filtros = [];

    public ?Throwable $erro = null;

    public function adicionar(string $hash, string $data = '2026-11-06', string $processo = '00012345620248040001'): ComunicacaoData
    {
        return $this->comunicacoes[] = new ComunicacaoData(
            hash: $hash,
            tribunal: 'TJAM',
            orgaoJulgador: '1ª Vara Cível',
            tipoComunicacao: 'Intimação',
            tipoDocumento: 'Despacho',
            dataDisponibilizacao: CarbonImmutable::parse($data),
            numeroProcesso: $processo,
            destinatarios: [new DestinatarioData('Parte Fictícia')],
            advogados: [new AdvogadoData('Advogada Fictícia', '12345', 'AM')],
            textoHtml: "<p>Comunicação {$hash}</p>",
            linkDocumento: null,
        );
    }

    public function buscar(FiltroConsulta $filtro): ResultadoConsulta
    {
        $this->filtros[] = $filtro;

        if ($this->erro) {
            throw $this->erro;
        }

        return new ResultadoConsulta(
            array_slice($this->comunicacoes, ($filtro->pagina - 1) * $filtro->porPagina, $filtro->porPagina),
            count($this->comunicacoes),
            $filtro->pagina,
            $filtro->porPagina,
        );
    }

    public function tribunais(): array
    {
        return [];
    }
}
