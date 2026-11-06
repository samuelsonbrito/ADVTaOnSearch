<?php

use App\Models\MonitoringTarget;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Testing\AssertableInertia as Assert;

it('mostra a página de erro própria no 404', function () {
    $this->get('/nao-existe')
        ->assertNotFound()
        ->assertInertia(fn (Assert $p) => $p->component('Erro')->where('status', 404));
});

it('mostra a página de erro com tempo de espera no 429 da consulta', function () {
    config(['djen.rate_limit.public_per_minute' => 1, 'djen.gateway' => 'fake']);
    RateLimiter::clear('djen-consulta');

    $this->get(route('consulta.index'))->assertOk();
    $this->get(route('consulta.index'))
        ->assertTooManyRequests()
        ->assertInertia(fn (Assert $p) => $p
            ->component('Erro')
            ->where('status', 429)
            ->where('tenteEmSegundos', fn ($s) => $s > 0 && $s <= 60));
});

it('mostra a página de erro no 403', function () {
    $alheio = MonitoringTarget::factory()->create();

    $this->actingAs(User::factory()->create())
        ->patch(route('monitoramento.alvos.update', $alheio), ['ativo' => false])
        ->assertForbidden()
        ->assertInertia(fn (Assert $p) => $p->component('Erro')->where('status', 403));
});

it('mantém JSON para quem pede JSON', function () {
    $this->getJson('/nao-existe')->assertNotFound()->assertJsonStructure(['message']);
});

it('esconde detalhes do 500 em produção', function () {
    config(['app.debug' => false]);
    Route::get('/teste-500', fn () => throw new RuntimeException('segredo interno'));

    $this->get('/teste-500')
        ->assertStatus(500)
        ->assertInertia(fn (Assert $p) => $p->component('Erro')->where('status', 500))
        ->assertDontSee('segredo interno');
});
