<?php

use App\Data\Djen\ComunicacaoData;
use App\Data\Djen\FiltroConsulta;
use App\Services\Djen\Gateways\FakeComunicacaoGateway;
use Carbon\CarbonImmutable;

beforeEach(fn () => $this->gateway = new FakeComunicacaoGateway(CarbonImmutable::parse('2026-11-06')));

it('é determinístico e tem hashes únicos', function () {
    $todas = $this->gateway->todas();
    $hashes = array_map(fn (ComunicacaoData $c) => $c->hash, $todas);

    expect($todas)->toHaveCount(45)
        ->and(array_unique($hashes))->toHaveCount(45)
        ->and((new FakeComunicacaoGateway(CarbonImmutable::parse('2026-11-06')))->todas()[0]->hash)->toBe($hashes[0]);
});

it('filtra por OAB e UF', function () {
    $r = $this->gateway->buscar(new FiltroConsulta(numeroOab: '12345', ufOab: 'AM', porPagina: 100));

    expect($r->total)->toBeGreaterThan(0)
        ->and(collect($r->comunicacoes)->every(fn (ComunicacaoData $c) => collect($c->advogados)->contains('numeroOab', '12345')))->toBeTrue()
        ->and($this->gateway->buscar(new FiltroConsulta(numeroOab: '12345', ufOab: 'SP'))->total)->toBe(0);
});

it('filtra por nome da parte sem diferenciar acentos e maiúsculas', function () {
    $r = $this->gateway->buscar(new FiltroConsulta(nomeParte: 'lucia bentes'));

    expect($r->total)->toBeGreaterThan(0);
});

it('filtra por tribunal, processo e período', function () {
    $primeira = $this->gateway->todas()[0];

    expect($this->gateway->buscar(new FiltroConsulta(numeroProcesso: $primeira->numeroProcesso))->total)->toBe(1)
        ->and(collect($this->gateway->buscar(new FiltroConsulta(tribunal: 'STJ', porPagina: 100))->comunicacoes)->pluck('tribunal')->unique()->all())->toBe(['STJ'])
        ->and($this->gateway->buscar(new FiltroConsulta(
            tribunal: 'TJAM',
            dataInicio: CarbonImmutable::parse('2026-11-06'),
            dataFim: CarbonImmutable::parse('2026-11-06'),
        ))->total)->toBe(2) // itens 0 e 1: TJAM, disponibilizados "hoje"
        ->and($this->gateway->buscar(new FiltroConsulta(
            tribunal: 'TJAM',
            dataInicio: CarbonImmutable::parse('2026-11-07'),
        ))->total)->toBe(0);
});

it('pagina e ordena da mais recente para a mais antiga', function () {
    $p1 = $this->gateway->buscar(new FiltroConsulta(nomeParte: 'a', pagina: 1, porPagina: 10));
    $p2 = $this->gateway->buscar(new FiltroConsulta(nomeParte: 'a', pagina: 2, porPagina: 10));

    expect($p1->comunicacoes)->toHaveCount(10)
        ->and($p1->comunicacoes[0]->dataDisponibilizacao->gte($p1->comunicacoes[9]->dataDisponibilizacao))->toBeTrue()
        ->and(array_intersect(
            array_map(fn ($c) => $c->hash, $p1->comunicacoes),
            array_map(fn ($c) => $c->hash, $p2->comunicacoes),
        ))->toBeEmpty();
});

it('compara o período por data de calendário, independente do fuso', function () {
    // Início às 00:00 em Manaus (04:00 UTC) não pode excluir o próprio dia.
    $filtro = new FiltroConsulta(
        tribunal: 'TJAM',
        dataInicio: CarbonImmutable::parse('2026-11-06 00:00', 'America/Manaus'),
        dataFim: CarbonImmutable::parse('2026-11-06 00:00', 'America/Manaus'),
    );

    expect($this->gateway->buscar($filtro)->total)->toBe(2);
});
