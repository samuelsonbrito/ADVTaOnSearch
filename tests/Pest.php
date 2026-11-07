<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Todos os testes de Feature usam o TestCase da aplicação e banco limpo.
| Os testes Unit continuam isolados do framework, exceto quando pedirem.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

// Os testes Unit não limpam o banco: o rastreio (que grava no banco) fica
// desligado neles. Ele é testado em tests/Feature/Rastreio e tests/Feature/Admin.
pest()->extend(TestCase::class)
    ->beforeEach(fn () => config(['djen.rastreio.ativo' => false]))
    ->in('Unit');
