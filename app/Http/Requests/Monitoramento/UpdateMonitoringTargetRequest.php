<?php

namespace App\Http\Requests\Monitoramento;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMonitoringTargetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('alvo')) ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('apelido')) {
            $this->merge(['apelido' => trim((string) $this->input('apelido')) ?: null]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'ativo' => ['sometimes', 'boolean'],
            'apelido' => ['sometimes', 'nullable', 'string', 'max:80'],
        ];
    }
}
