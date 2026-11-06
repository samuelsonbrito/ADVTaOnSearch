<?php

use App\Data\Djen\ComunicacaoData;
use App\Data\Djen\FiltroConsulta;
use App\Data\Djen\ResultadoConsulta;
use Carbon\CarbonImmutable;

function comunicacao(array $sobrescrever = []): ComunicacaoData
{
    return new ComunicacaoData(...array_merge([
        'hash' => 'h1',
        'tribunal' => 'TJAM',
        'orgaoJulgador' => null,
        'tipoComunicacao' => null,
        'tipoDocumento' => null,
        'dataDisponibilizacao' => CarbonImmutable::parse('2026-11-06'),
        'numeroProcesso' => '00012345620248040001',
        'destinatarios' => [],
        'advogados' => [],
        'textoHtml' => '<p>Primeira&nbsp;linha</p><p>Segunda   linha<br>terceira</p>',
    ], $sobrescrever));
}

it('formata o número do processo no padrão CNJ', function () {
    expect(comunicacao()->numeroProcessoFormatado())->toBe('0001234-56.2024.8.04.0001')
        ->and(comunicacao(['numeroProcesso' => '123'])->numeroProcessoFormatado())->toBe('123');
});

it('gera texto puro e trecho a partir do HTML', function () {
    $c = comunicacao();

    expect($c->textoPuro())->toBe('Primeira linha Segunda linha terceira')
        ->and($c->trecho(15))->toBe('Primeira linha…')
        ->and(mb_strlen(comunicacao(['textoHtml' => str_repeat('a ', 400)])->trecho()))->toBe(280);
});

it('calcula a última página', function () {
    expect((new ResultadoConsulta([], 0, 1, 20))->ultimaPagina())->toBe(1)
        ->and((new ResultadoConsulta([], 41, 1, 20))->ultimaPagina())->toBe(3);
});

it('sabe se o filtro tem critério de busca', function () {
    expect((new FiltroConsulta)->temCriterio())->toBeFalse()
        ->and((new FiltroConsulta(dataInicio: CarbonImmutable::today()))->temCriterio())->toBeFalse()
        ->and((new FiltroConsulta(tribunal: 'TJAM'))->temCriterio())->toBeTrue();
});

it('descarta o conteúdo de script e style no texto puro', function () {
    $c = comunicacao(['textoHtml' => '<style>p{color:red}</style><p>Intimação</p><script>alert(1)</script><div>fim</div>']);

    expect($c->textoPuro())->toBe('Intimação fim');
});
