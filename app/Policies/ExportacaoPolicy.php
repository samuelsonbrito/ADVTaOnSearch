<?php

namespace App\Policies;

use App\Models\Exportacao;
use App\Models\User;

class ExportacaoPolicy
{
    public function view(User $user, Exportacao $exportacao): bool
    {
        return $exportacao->user_id === $user->id;
    }

    public function delete(User $user, Exportacao $exportacao): bool
    {
        return $exportacao->user_id === $user->id;
    }
}
