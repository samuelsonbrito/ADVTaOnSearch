<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Carbon;

/**
 * Comunicação encontrada para um alvo (tabela communication_target).
 *
 * @property int $id
 * @property int $communication_id
 * @property int $monitoring_target_id
 * @property Carbon|null $lida_em
 * @property Carbon|null $notificada_em
 * @property Carbon $created_at
 * @property-read Communication $communication
 * @property-read MonitoringTarget $target
 */
class Alerta extends Pivot
{
    protected $table = 'communication_target';

    public $incrementing = true;

    protected $fillable = ['communication_id', 'monitoring_target_id', 'lida_em', 'notificada_em'];

    protected function casts(): array
    {
        return [
            'lida_em' => 'datetime',
            'notificada_em' => 'datetime',
        ];
    }

    /** @return BelongsTo<Communication, $this> */
    public function communication(): BelongsTo
    {
        return $this->belongsTo(Communication::class);
    }

    /** @return BelongsTo<MonitoringTarget, $this> */
    public function target(): BelongsTo
    {
        return $this->belongsTo(MonitoringTarget::class, 'monitoring_target_id');
    }

    /**
     * Alertas dos alvos de um usuário.
     *
     * @param  Builder<self>  $query
     */
    public function scopeDoUsuario(Builder $query, User $user): void
    {
        $query->whereIn('monitoring_target_id', $user->monitoringTargets()->select('id'));
    }
}
