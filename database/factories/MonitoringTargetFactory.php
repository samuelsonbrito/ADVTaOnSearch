<?php

namespace Database\Factories;

use App\Enums\TipoAlvo;
use App\Models\MonitoringTarget;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MonitoringTarget>
 */
class MonitoringTargetFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'tipo' => TipoAlvo::Oab,
            'valor' => (string) fake()->unique()->numberBetween(1000, 99999),
            'uf' => 'AM',
            'apelido' => null,
            'ativo' => true,
        ];
    }

    public function oab(string $numero, string $uf = 'AM'): static
    {
        return $this->state(['tipo' => TipoAlvo::Oab, 'valor' => $numero, 'uf' => $uf]);
    }

    public function parte(string $nome): static
    {
        return $this->state(['tipo' => TipoAlvo::Parte, 'valor' => $nome, 'uf' => null]);
    }

    public function processo(string $digitos): static
    {
        return $this->state(['tipo' => TipoAlvo::Processo, 'valor' => $digitos, 'uf' => null]);
    }

    public function inativo(): static
    {
        return $this->state(['ativo' => false]);
    }
}
