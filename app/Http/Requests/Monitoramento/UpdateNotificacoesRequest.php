<?php

namespace App\Http\Requests\Monitoramento;

use Illuminate\Foundation\Http\FormRequest;

class UpdateNotificacoesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $digitos = preg_replace('/\D/', '', (string) $this->input('whatsapp'));

        // Número nacional sem DDI (DDD + número) ganha o 55.
        if ($digitos !== '' && strlen($digitos) <= 11) {
            $digitos = '55'.$digitos;
        }

        $this->merge(['whatsapp' => $digitos !== '' ? $digitos : null]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'whatsapp' => ['nullable', 'regex:/^55\d{10,11}$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'whatsapp.regex' => 'Informe um celular brasileiro com DDD, ex.: (92) 99999-9999.',
        ];
    }
}
