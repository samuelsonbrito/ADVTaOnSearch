<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Uma busca feita na aba Consulta (tabela search_logs).
 *
 * @property int $id
 * @property int|null $user_id
 * @property string $visitante
 * @property string|null $criterio
 * @property string $status
 * @property int|null $total_resultados
 * @property int|null $duracao_ms
 */
class RegistroBusca extends Model
{
    use Prunable;

    public const UPDATED_AT = null;

    protected $table = 'search_logs';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'bot' => 'boolean',
            'do_cache' => 'boolean',
            'data_inicio' => 'date',
            'data_fim' => 'date',
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** LGPD: some depois de djen.rastreio.dias_retencao dias. */
    public function prunable(): Builder
    {
        return static::query()->where('created_at', '<', now()->subDays((int) config('djen.rastreio.dias_retencao')));
    }
}
