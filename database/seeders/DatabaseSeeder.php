<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Dados de demonstração para desenvolvimento local.
 * Login: demo@advtaon.test / password (e-mail já confirmado).
 * Administrador: admin@advtaon.test / password (acesso a /admin).
 * Com DJEN_GATEWAY=fake, rode `php artisan djen:monitorar --sync` para gerar alertas.
 */
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->warn('Seeder de demonstração ignorado em produção.');

            return;
        }

        $user = User::factory()->create([
            'name' => 'Usuária Demo',
            'email' => 'demo@advtaon.test',
        ]);

        User::factory()->create([
            'name' => 'Administração',
            'email' => 'admin@advtaon.test',
        ])->forceFill(['is_admin' => true])->save();

        // OAB presente nos dados fictícios do gateway "fake".
        $user->monitoringTargets()->create(['tipo' => 'oab', 'valor' => '12345', 'uf' => 'AM', 'apelido' => 'Escritório (demo)']);
        $user->monitoringTargets()->create(['tipo' => 'parte', 'valor' => 'Joana Ferreira Alves']);
    }
}
