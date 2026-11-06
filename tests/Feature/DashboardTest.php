<?php

use App\Models\User;

it('redireciona visitantes para o login', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

it('leva usuários logados ao monitoramento', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertRedirect('/monitoramento');
});
