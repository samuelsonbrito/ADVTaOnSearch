<?php

namespace App\Support\Admin;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/**
 * Período dos relatórios da área administrativa, em dias de calendário no
 * fuso de exibição (djen.monitoring.timezone). As consultas usam inicioUtc()
 * e fimUtc(), porque os timestamps ficam gravados em UTC.
 */
final readonly class Periodo
{
    public const PRESETS = ['hoje' => 'Hoje', '7d' => 'Últimos 7 dias', '30d' => 'Últimos 30 dias', '90d' => 'Últimos 90 dias'];

    public const MAX_DIAS = 366;

    private function __construct(
        public string $chave,
        public CarbonImmutable $inicio,
        public CarbonImmutable $fim,
    ) {}

    public static function fuso(): string
    {
        return (string) config('djen.monitoring.timezone');
    }

    public static function deRequest(Request $request): self
    {
        return self::de(
            (string) $request->query('periodo', '7d'),
            $request->query('inicio'),
            $request->query('fim'),
        );
    }

    public static function de(string $chave, mixed $inicio = null, mixed $fim = null, ?CarbonImmutable $agora = null): self
    {
        $hoje = ($agora ?? CarbonImmutable::now(self::fuso()))->setTimezone(self::fuso())->startOfDay();

        if ($chave === 'personalizado') {
            $i = self::data($inicio);
            $f = self::data($fim);

            if ($i && $f) {
                [$i, $f] = $i->greaterThan($f) ? [$f, $i] : [$i, $f];

                if ($i->diffInDays($f) >= self::MAX_DIAS) {
                    $i = $f->subDays(self::MAX_DIAS - 1);
                }

                return new self('personalizado', $i, $f->endOfDay());
            }

            $chave = '7d';
        }

        $dias = match ($chave) {
            'hoje' => 1,
            '30d' => 30,
            '90d' => 90,
            default => 7,
        };

        return new self(array_key_exists($chave, self::PRESETS) ? $chave : '7d', $hoje->subDays($dias - 1), $hoje->endOfDay());
    }

    private static function data(mixed $valor): ?CarbonImmutable
    {
        if (! is_string($valor) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor)) {
            return null;
        }

        $data = CarbonImmutable::createFromFormat('!Y-m-d', $valor, self::fuso());

        return $data && $data->format('Y-m-d') === $valor ? $data : null;
    }

    public function inicioUtc(): CarbonImmutable
    {
        return $this->inicio->utc();
    }

    public function fimUtc(): CarbonImmutable
    {
        return $this->fim->utc();
    }

    public function dias(): int
    {
        return (int) $this->inicio->diffInDays($this->fim->startOfDay()) + 1;
    }

    /** O período de mesmo tamanho imediatamente anterior (para comparar). */
    public function anterior(): self
    {
        $dias = $this->dias();

        return new self($this->chave, $this->inicio->subDays($dias), $this->inicio->subDay()->endOfDay());
    }

    /** @return list<string> Datas Y-m-d do período, em ordem. */
    public function datas(): array
    {
        $datas = [];

        for ($d = $this->inicio; $d->lessThanOrEqualTo($this->fim); $d = $d->addDay()) {
            $datas[] = $d->toDateString();
        }

        return $datas;
    }

    /** @return array{chave: string, inicio: string, fim: string, dias: int} */
    public function toArray(): array
    {
        return [
            'chave' => $this->chave,
            'inicio' => $this->inicio->toDateString(),
            'fim' => $this->fim->toDateString(),
            'dias' => $this->dias(),
        ];
    }

    /** @return array<string, string> Query string que reproduz este período. */
    public function toQuery(): array
    {
        return $this->chave === 'personalizado'
            ? ['periodo' => 'personalizado', 'inicio' => $this->inicio->toDateString(), 'fim' => $this->fim->toDateString()]
            : ['periodo' => $this->chave];
    }
}
