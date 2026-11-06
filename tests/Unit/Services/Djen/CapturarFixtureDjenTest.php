<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

beforeEach(function () {
    config(['djen.base_url' => 'https://djen.test/api/v1', 'djen.retry.times' => 0]);
    Http::preventStrayRequests();
    Sleep::fake();

    $this->dir = storage_path('framework/testing/djen-'.uniqid());
});

afterEach(fn () => File::deleteDirectory($this->dir));

it('salva a resposta e os cabeçalhos da API', function () {
    Http::fake(['djen.test/*' => Http::response(['ok' => true, 'acentuação' => 'sim'], 200, ['X-Teste' => '1'])]);

    $this->artisan('djen:capturar-fixture', [
        '--oab' => '12345',
        '--uf' => 'AM',
        '--param' => ['dataDisponibilizacaoInicio=2026-11-01'],
        '--saida' => str_replace(base_path().'/', '', $this->dir).'/comunicacao.json',
    ])->assertSuccessful();

    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'numeroOab=12345')
        && str_contains($request->url(), 'ufOab=AM')
        && str_contains($request->url(), 'itensPorPagina=5')
        && str_contains($request->url(), 'dataDisponibilizacaoInicio=2026-11-01'));

    expect(File::json($this->dir.'/comunicacao.json'))->toBe(['ok' => true, 'acentuação' => 'sim'])
        ->and(File::get($this->dir.'/comunicacao.json'))->toContain('"acentuação": "sim"')
        ->and(File::json($this->dir.'/comunicacao.headers.json')['headers']['X-Teste'])->toBe(['1']);
});

it('falha sem gravar arquivo quando a API bloqueia', function () {
    Http::fake(['djen.test/*' => Http::response('<HTML>blocked</HTML>', 403)]);

    $this->artisan('djen:capturar-fixture', [
        '--saida' => str_replace(base_path().'/', '', $this->dir).'/comunicacao.json',
    ])->expectsOutputToContain('fora do Brasil')->assertFailed();

    expect(File::exists($this->dir.'/comunicacao.json'))->toBeFalse();
});

it('rejeita parâmetro extra mal formado', function () {
    $this->artisan('djen:capturar-fixture', ['--param' => ['=x']])->assertExitCode(2);

    Http::assertNothingSent();
});
