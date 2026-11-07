<?php

use App\Support\Admin\Periodo;
use Carbon\CarbonImmutable;

beforeEach(function () {
    config(['djen.monitoring.timezone' => 'America/Manaus']);
    $this->agora = CarbonImmutable::parse('2026-11-07 02:30:00', 'UTC'); // 06/11 22:30 em Manaus
});

it('usa dias de calendário no fuso de exibição', function () {
    $p = Periodo::de('7d', agora: $this->agora);

    expect($p->toArray())->toBe(['chave' => '7d', 'inicio' => '2026-10-31', 'fim' => '2026-11-06', 'dias' => 7])
        ->and($p->inicioUtc()->toDateTimeString())->toBe('2026-10-31 04:00:00')
        ->and($p->fimUtc()->toDateTimeString())->toBe('2026-11-07 03:59:59')
        ->and($p->datas())->toHaveCount(7);
});

it('compara com o período anterior de mesmo tamanho', function () {
    $a = Periodo::de('30d', agora: $this->agora)->anterior();

    expect($a->toArray())->toMatchArray(['inicio' => '2026-09-08', 'fim' => '2026-10-07', 'dias' => 30]);
});

it('aceita intervalo personalizado, inverte datas trocadas e limita o tamanho', function () {
    expect(Periodo::de('personalizado', '2026-11-05', '2026-11-01', $this->agora)->toArray())
        ->toMatchArray(['chave' => 'personalizado', 'inicio' => '2026-11-01', 'fim' => '2026-11-05', 'dias' => 5]);

    expect(Periodo::de('personalizado', '2020-01-01', '2026-11-06', $this->agora)->dias())->toBe(Periodo::MAX_DIAS);
});

it('cai em 7 dias com valores inválidos', function () {
    expect(Periodo::de('xyz', agora: $this->agora)->chave)->toBe('7d')
        ->and(Periodo::de('personalizado', '2026-13-40', 'x', $this->agora)->chave)->toBe('7d')
        ->and(Periodo::de('hoje', agora: $this->agora)->toArray())->toMatchArray(['inicio' => '2026-11-06', 'fim' => '2026-11-06']);
});
