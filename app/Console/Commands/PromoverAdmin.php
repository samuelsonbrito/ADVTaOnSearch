<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Rastreio\Rastreador;
use App\Support\Rastreio\TipoEvento;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/** Concede (ou remove, com --remover) o acesso à área administrativa. */
#[Signature('admin:promover
    {email : E-mail de uma conta já cadastrada}
    {--remover : Remove o acesso em vez de conceder}')]
#[Description('Dá ou tira o acesso de administrador (/admin) de um usuário')]
class PromoverAdmin extends Command
{
    public function handle(Rastreador $rastreador): int
    {
        $user = User::where('email', mb_strtolower(trim((string) $this->argument('email'))))->first();

        if (! $user) {
            $this->components->error('Nenhuma conta com esse e-mail.');

            return self::FAILURE;
        }

        $admin = ! $this->option('remover');
        $user->forceFill(['is_admin' => $admin])->save();
        $rastreador->evento($admin ? TipoEvento::ADMIN_PROMOVIDO : TipoEvento::ADMIN_REMOVIDO, ['via' => 'comando'], $user);

        $this->components->info($admin
            ? "{$user->email} agora acessa a área administrativa (/admin)."
            : "{$user->email} não acessa mais a área administrativa.");

        return self::SUCCESS;
    }
}
