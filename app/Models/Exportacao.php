<?php

namespace App\Models;

use App\Enums\FormatoExportacao;
use App\Enums\OrigemExportacao;
use App\Enums\StatusExportacao;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property int $user_id
 * @property OrigemExportacao $origem
 * @property FormatoExportacao $formato
 * @property StatusExportacao $status
 * @property array<string, mixed> $parametros
 * @property int|null $total_itens
 * @property string|null $arquivo
 * @property string|null $erro
 * @property CarbonInterface|null $concluida_em
 * @property CarbonInterface $created_at
 * @property-read User $user
 */
class Exportacao extends Model
{
    use Prunable;

    protected $table = 'exports';

    protected $fillable = ['origem', 'formato', 'status', 'parametros', 'total_itens', 'arquivo', 'erro', 'concluida_em'];

    protected $attributes = ['status' => 'pendente'];

    protected function casts(): array
    {
        return [
            'origem' => OrigemExportacao::class,
            'formato' => FormatoExportacao::class,
            'status' => StatusExportacao::class,
            'parametros' => 'array',
            'concluida_em' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Apagar o registro apaga o arquivo (exclusão manual, prune e cascade via Eloquent).
        static::deleting(fn (self $e) => $e->apagarArquivo());
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function disco(): string
    {
        return (string) config('djen.exportacao.disk');
    }

    public function expiraEm(): CarbonInterface
    {
        return $this->created_at->copy()->addDays((int) config('djen.exportacao.dias_retencao'));
    }

    /** Nome sugerido no download, ex.: comunicacoes-consulta-2026-11-06-1430.csv */
    public function nomeDownload(): string
    {
        return sprintf(
            'comunicacoes-%s-%s.%s',
            $this->origem->value,
            $this->created_at->timezone((string) config('djen.monitoring.timezone'))->format('Y-m-d-Hi'),
            $this->formato->value,
        );
    }

    public function disponivel(): bool
    {
        return $this->status === StatusExportacao::Concluida
            && $this->arquivo !== null
            && Storage::disk(self::disco())->exists($this->arquivo);
    }

    public function apagarArquivo(): void
    {
        if ($this->arquivo !== null) {
            Storage::disk(self::disco())->delete($this->arquivo);
        }
    }

    /**
     * LGPD: exportações mais antigas que a retenção.
     *
     * @return Builder<self>
     */
    public function prunable(): Builder
    {
        return static::query()->where('created_at', '<', now()->subDays((int) config('djen.exportacao.dias_retencao')));
    }

    protected function pruning(): void
    {
        $this->apagarArquivo();
    }
}
