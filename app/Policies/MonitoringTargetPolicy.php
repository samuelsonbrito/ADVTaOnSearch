<?php

namespace App\Policies;

use App\Models\MonitoringTarget;
use App\Models\User;

class MonitoringTargetPolicy
{
    public function update(User $user, MonitoringTarget $alvo): bool
    {
        return $alvo->user_id === $user->id;
    }

    public function delete(User $user, MonitoringTarget $alvo): bool
    {
        return $alvo->user_id === $user->id;
    }
}
