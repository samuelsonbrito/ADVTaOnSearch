<?php

use App\Models\User;
use Database\Seeders\DatabaseSeeder;

it('cria a usuária de demonstração com alvos', function () {
    $this->seed(DatabaseSeeder::class);

    $user = User::firstWhere('email', 'demo@advtaon.test');
    expect($user)->not->toBeNull()
        ->and($user->hasVerifiedEmail())->toBeTrue()
        ->and($user->monitoringTargets()->count())->toBe(2);
});

it('não roda em produção', function () {
    app()->detectEnvironment(fn () => 'production');

    $this->seed(DatabaseSeeder::class);

    expect(User::count())->toBe(0);
});
