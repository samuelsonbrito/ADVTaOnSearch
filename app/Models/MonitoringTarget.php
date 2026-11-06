<?php

namespace App\Models;

use App\Enums\TipoAlvo;
use Database\Factories\MonitoringTargetFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property TipoAlvo $tipo
 * @property string $valor
 * @property string|null $uf
 * @property string|null $apelido
 * @property bool $ativo
 * @property Carbon|null $ultima_verificacao_em
 * @property-read User $user
 */
class MonitoringTarget extends Model
{
    /** @use HasFactory<MonitoringTargetFactory> */
    use HasFactory;

    protected $fillable = ['tipo', 'valor', 'uf', 'apelido', 'ativo'];

    protected function casts(): array
    {
        return [
            'tipo' => TipoAlvo::class,
            'ativo' => 'boolean',
            'ultima_verificacao_em' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsToMany<Communication, $this, Alerta> */
    public function communications(): BelongsToMany
    {
        return $this->belongsToMany(Communication::class, 'communication_target')
            ->using(Alerta::class)
            ->withPivot(['id', 'lida_em', 'notificada_em'])
            ->withTimestamps();
    }

    /** @return HasMany<Alerta, $this> */
    public function alertas(): HasMany
    {
        return $this->hasMany(Alerta::class);
    }

    /** @param Builder<self> $query */
    public function scopeAtivos(Builder $query): void
    {
        $query->where('ativo', true);
    }

    /** Ex.: "OAB 12345/AM", "Parte: Joana", "Processo 0001234-56.2024.8.04.0001". */
    public function descricao(): string
    {
        if ($this->apelido) {
            return $this->apelido;
        }

        return match ($this->tipo) {
            TipoAlvo::Oab => "OAB {$this->valor}/{$this->uf}",
            TipoAlvo::Parte => "Parte: {$this->valor}",
            TipoAlvo::Processo => 'Processo '.self::formatarCnj($this->valor),
        };
    }

    public static function formatarCnj(string $d): string
    {
        return strlen($d) === 20
            ? sprintf('%s-%s.%s.%s.%s.%s', substr($d, 0, 7), substr($d, 7, 2), substr($d, 9, 4), $d[13], substr($d, 14, 2), substr($d, 16, 4))
            : $d;
    }
}
