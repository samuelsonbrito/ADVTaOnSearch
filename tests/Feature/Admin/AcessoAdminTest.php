<?php

use App\Models\Atividade;
use App\Models\User;
use App\Support\Rastreio\TipoEvento;
use Inertia\Testing\AssertableInertia as Assert;

dataset('telas admin', [
    'visão geral' => ['admin.dashboard', 'Admin/Dashboard'],
    'buscas' => ['admin.buscas', 'Admin/Buscas'],
    'atividade' => ['admin.atividade', 'Admin/Atividade'],
    'usuários' => ['admin.usuarios', 'Admin/Usuarios'],
    'api do cnj' => ['admin.cnj', 'Admin/ChamadasCnj'],
]);

it('pede login a visitantes', function (string $rota) {
    $this->get(route($rota))->assertRedirect(route('login'));
})->with('telas admin');

it('nega acesso a quem não é administrador', function (string $rota) {
    $this->actingAs(User::factory()->create())->get(route($rota))->assertForbidden();
})->with('telas admin');

it('abre para administradores, sem registrar as próprias páginas vistas', function (string $rota, string $componente) {
    $this->actingAs(User::factory()->admin()->create())
        ->get(route($rota))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component($componente));

    expect(Atividade::where('tipo', TipoEvento::PAGINA_VISTA)->count())->toBe(0);
})->with('telas admin');

it('informa ao front se o usuário é administrador (menu lateral)', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(route('monitoramento.index'))
        ->assertInertia(fn (Assert $page) => $page->where('auth.user.is_admin', true));

    $this->actingAs(User::factory()->create())
        ->get(route('monitoramento.index'))
        ->assertInertia(fn (Assert $page) => $page->where('auth.user.is_admin', false));
});

it('concede e remove o acesso pelo comando', function () {
    $user = User::factory()->create(['email' => 'ana@exemplo.com']);

    $this->artisan('admin:promover', ['email' => 'ANA@exemplo.com'])->assertSuccessful();
    expect($user->fresh()->is_admin)->toBeTrue();

    $this->artisan('admin:promover', ['email' => 'ana@exemplo.com', '--remover' => true])->assertSuccessful();
    expect($user->fresh()->is_admin)->toBeFalse();

    expect(Atividade::where('user_id', $user->id)->pluck('tipo')->all())
        ->toBe([TipoEvento::ADMIN_PROMOVIDO, TipoEvento::ADMIN_REMOVIDO]);

    $this->artisan('admin:promover', ['email' => 'ninguem@exemplo.com'])->assertFailed();
});

it('não deixa promover pelo formulário de perfil', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->patch(route('profile.update'), ['name' => 'X', 'email' => $user->email, 'is_admin' => true]);

    expect($user->fresh()->is_admin)->toBeFalse();
});
