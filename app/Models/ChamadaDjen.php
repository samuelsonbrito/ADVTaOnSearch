<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;

/**
 * Uma requisição HTTP real à Comunica API (tabela djen_api_calls).
 *
 * @property int $id
 * @property string $origem
 * @property string $endpoint
 * @property array<string, mixed> $parametros
 * @property int|null $status
 * @property string $resultado
 * @property int $duracao_ms
 */
class ChamadaDjen extends Model
{
    use Prunable;

    public const UPDATED_AT = null;

    protected $table = 'djen_api_calls';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'parametros' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function prunable(): Builder
    {
        return static::query()->where('created_at', '<', now()->subDays((int) config('djen.rastreio.dias_retencao')));
    }
}
