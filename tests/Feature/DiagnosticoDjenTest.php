<?php

use App\Services\Djen\DjenClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

beforeEach(function () {
    config(['djen.base_url' => 'https://djen.test/api/v1', 'djen.retry.times' => 0, 'djen.gateway' => 'api']);
    app()->forgetInstance(DjenClient::class);
    Http::preventStrayRequests();
    Sleep::fake();
});

it('passa quando banco, cache e API respondem', function () {
    Http::fake(['djen.test/*' => Http::response(['ok' => true], 200, ['x-ratelimit-remaining' => '19'])]);

    $this->artisan('djen:diagnostico')
        ->expectsOutputToContain('HTTP 200')
        ->expectsOutputToContain('Rate limit restante')
        ->expectsOutputToContain('Ambiente pronto.')
        ->assertSuccessful();

    Http::assertSent(fn ($r) => str_contains($r->url(), 'comunicacao?pagina=1&itensPorPagina=5'));
});

it('falha e explica o geobloqueio quando a API responde 403', function () {
    Http::fake(['djen.test/*' => Http::response('<html>blocked</html>', 403)]);

    $this->artisan('djen:diagnostico')
        ->expectsOutputToContain('servidor com IP no Brasil')
        ->assertFailed();
});

it('avisa sobre configurações de teste em uso', function () {
    config(['queue.default' => 'sync', 'mail.default' => 'log', 'djen.gateway' => 'fake']);

    $this->artisan('djen:diagnostico', ['--sem-api' => true])
        ->expectsOutputToContain('e-mails não serão entregues')
        ->expectsOutputToContain('dados fictícios')
        ->assertSuccessful();

    Http::assertNothingSent();
});
