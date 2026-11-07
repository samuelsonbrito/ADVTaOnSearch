<?php

namespace App\Listeners;

use App\Models\User;
use App\Services\Rastreio\Rastreador;
use App\Support\Rastreio\TipoEvento;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Events\Verified;
use Illuminate\Contracts\Auth\Authenticatable;

/** Cadastro, login (e falhas), logout, e-mail confirmado e senha redefinida. */
class RegistrarEventosDeAutenticacao
{
    public function __construct(
        private readonly Rastreador $rastreador,
    ) {}

    public function handleRegistered(Registered $e): void
    {
        $this->registrar(TipoEvento::USUARIO_CADASTRADO, $e->user);
    }

    public function handleLogin(Login $e): void
    {
        $this->registrar(TipoEvento::USUARIO_LOGIN, $e->user, ['lembrar' => $e->remember]);
    }

    public function handleFailed(Failed $e): void
    {
        // Sem guardar o e-mail digitado: só se ele pertence a uma conta.
        $this->registrar(TipoEvento::USUARIO_LOGIN_FALHOU, $e->user, ['conta_existe' => $e->user !== null]);
    }

    public function handleLogout(Logout $e): void
    {
        $this->registrar(TipoEvento::USUARIO_LOGOUT, $e->user);
    }

    public function handleVerified(Verified $e): void
    {
        $this->registrar(TipoEvento::USUARIO_EMAIL_VERIFICADO, $e->user);
    }

    public function handlePasswordReset(PasswordReset $e): void
    {
        $this->registrar(TipoEvento::USUARIO_SENHA_REDEFINIDA, $e->user);
    }

    /**
     * @param  array<string, mixed>  $dados
     */
    private function registrar(string $tipo, ?Authenticatable $user, array $dados = []): void
    {
        $this->rastreador->evento($tipo, $dados, $user instanceof User ? $user : null, request());
    }
}
