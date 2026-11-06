<?php

namespace App\Policies;

use App\Models\Alerta;
use App\Models\User;

class AlertaPolicy
{
    public function update(User $user, Alerta $alerta): bool
    {
        return $alerta->target->user_id === $user->id;
    }
}
