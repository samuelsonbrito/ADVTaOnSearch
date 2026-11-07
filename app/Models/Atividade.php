<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Um evento da trilha de atividade (tabela activity_logs).
 * Tipos e rótulos: App\Support\Rastreio\TipoEvento.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string $tipo
 * @property array<string, mixed>|null $dados
 */
class Atividade extends Model
{
    use Prunable;

    public const UPDATED_AT = null;

    protected $table = 'activity_logs';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'dados' => 'array',
            'bot' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function prunable(): Builder
    {
        return static::query()->where('created_at', '<', now()->subDays((int) config('djen.rastreio.dias_retencao')));
    }
}
