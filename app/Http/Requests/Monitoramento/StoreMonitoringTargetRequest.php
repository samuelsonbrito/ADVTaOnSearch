<?php

namespace App\Http\Requests\Monitoramento;

use App\Enums\TipoAlvo;
use App\Http\Requests\ConsultaRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreMonitoringTargetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $tipo = TipoAlvo::tryFrom((string) $this->input('tipo'));
        $valor = trim((string) $this->input('valor'));

        $this->merge([
            'valor' => match ($tipo) {
                TipoAlvo::Oab, TipoAlvo::Processo => preg_replace('/\D/', '', $valor),
                default => preg_replace('/\s+/u', ' ', $valor),
            },
            'uf' => $tipo === TipoAlvo::Oab && $this->filled('uf') ? mb_strtoupper(trim((string) $this->input('uf'))) : null,
            'apelido' => trim((string) $this->input('apelido')) ?: null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tipo = TipoAlvo::tryFrom((string) $this->input('tipo'));

        return [
            'tipo' => ['required', Rule::enum(TipoAlvo::class)],
            'valor' => array_merge(['required', 'string'], match ($tipo) {
                TipoAlvo::Oab => ['regex:/^\d{1,8}$/'],
                TipoAlvo::Processo => ['digits:20'],
                TipoAlvo::Parte => ['min:3', 'max:120'],
                default => [],
            }, [
                Rule::unique('monitoring_targets')->where(fn ($q) => $q
                    ->where('user_id', $this->user()?->id)
                    ->where('tipo', $tipo?->value)
                    ->where('uf', $this->input('uf'))),
            ]),
            'uf' => [Rule::requiredIf($tipo === TipoAlvo::Oab), 'nullable', Rule::in(ConsultaRequest::UFS)],
            'apelido' => ['nullable', 'string', 'max:80'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $limite = (int) config('djen.monitoring.max_alvos');

                if ($this->user() && $this->user()->monitoringTargets()->count() >= $limite) {
                    $validator->errors()->add('valor', "Você atingiu o limite de {$limite} alvos.");
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'tipo.*' => 'Escolha o tipo de alvo.',
            'valor.required' => 'Preencha o valor a monitorar.',
            'valor.regex' => 'Informe apenas os números da OAB.',
            'valor.digits' => 'O número do processo deve ter 20 dígitos (padrão CNJ).',
            'valor.min' => 'Digite ao menos 3 letras do nome.',
            'valor.max' => 'Nome muito longo.',
            'valor.unique' => 'Você já monitora este alvo.',
            'uf.required' => 'Informe a UF da OAB.',
            'uf.in' => 'UF inválida.',
            'apelido.max' => 'Apelido muito longo.',
        ];
    }
}
