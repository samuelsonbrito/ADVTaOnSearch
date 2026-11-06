<?php

namespace App\Http\Requests;

use App\Data\Djen\FiltroConsulta;
use App\Support\Oab;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Filtros da consulta pública. Os nomes dos campos são os da query string
 * do app (oab, uf, parte, ...), para que a URL possa ser compartilhada.
 */
class ConsultaRequest extends FormRequest
{
    public const UFS = [
        'AC', 'AL', 'AM', 'AP', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MG', 'MS', 'MT', 'PA',
        'PB', 'PE', 'PI', 'PR', 'RJ', 'RN', 'RO', 'RR', 'RS', 'SC', 'SE', 'SP', 'TO',
    ];

    private bool $invalido = false;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * Não redireciona: é um GET (link compartilhável). A página é renderizada
     * com os erros e sem executar a busca, evitando loop de redirecionamento.
     */
    protected function failedValidation(Validator $validator): void
    {
        $this->invalido = true;
    }

    public function invalido(): bool
    {
        return $this->invalido;
    }

    /**
     * @return array<string, string>
     */
    public function erros(): array
    {
        return array_map(fn (array $m): string => $m[0], $this->getValidatorInstance()->errors()->toArray());
    }

    protected function prepareForValidation(): void
    {
        $limpo = [];

        foreach (['oab', 'uf', 'parte', 'advogado', 'processo', 'tribunal', 'inicio', 'fim', 'pagina'] as $campo) {
            $valor = $this->query($campo);
            $limpo[$campo] = is_string($valor) && trim($valor) !== '' ? trim($valor) : null;
        }

        if ($limpo['oab'] !== null) {
            $limpo['oab'] = Oab::normalizar($limpo['oab']);
        }

        if ($limpo['processo'] !== null) {
            $limpo['processo'] = preg_replace('/\D/', '', $limpo['processo']);
        }

        foreach (['uf', 'tribunal'] as $campo) {
            if ($limpo[$campo] !== null) {
                $limpo[$campo] = mb_strtoupper($limpo[$campo]);
            }
        }

        $this->merge($limpo);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'oab' => ['nullable', 'regex:'.Oab::REGEX, 'required_with:uf'],
            'uf' => ['nullable', Rule::in(self::UFS), 'required_with:oab'],
            'parte' => ['nullable', 'string', 'min:3', 'max:120'],
            'advogado' => ['nullable', 'string', 'min:3', 'max:120'],
            'processo' => ['nullable', 'digits:20'],
            'tribunal' => ['nullable', 'string', 'max:20', 'regex:/^[A-Z0-9]+$/'],
            'inicio' => ['nullable', 'date_format:Y-m-d'],
            'fim' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:inicio'],
            'pagina' => ['nullable', 'integer', 'min:1', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'oab.regex' => 'Número da OAB inválido. Ex.: 12345 ou 12345-A.',
            'oab.required_with' => 'Informe o número da OAB junto com a UF.',
            'uf.required_with' => 'Informe a UF da OAB.',
            'uf.in' => 'UF inválida.',
            'parte.min' => 'Digite ao menos 3 letras do nome da parte.',
            'advogado.min' => 'Digite ao menos 3 letras do nome do advogado.',
            'processo.digits' => 'O número do processo deve ter 20 dígitos (padrão CNJ).',
            'tribunal.*' => 'Tribunal inválido.',
            'inicio.date_format' => 'Data inicial inválida.',
            'fim.date_format' => 'Data final inválida.',
            'fim.after_or_equal' => 'A data final deve ser igual ou posterior à inicial.',
        ];
    }

    public function filtro(): FiltroConsulta
    {
        // Só os campos válidos: com erro, os inválidos ficam de fora.
        $v = $this->getValidatorInstance()->valid();

        return new FiltroConsulta(
            numeroOab: $v['oab'] ?? null,
            ufOab: $v['uf'] ?? null,
            nomeParte: $v['parte'] ?? null,
            nomeAdvogado: $v['advogado'] ?? null,
            numeroProcesso: $v['processo'] ?? null,
            tribunal: $v['tribunal'] ?? null,
            dataInicio: isset($v['inicio']) ? CarbonImmutable::createFromFormat('!Y-m-d', $v['inicio']) : null,
            dataFim: isset($v['fim']) ? CarbonImmutable::createFromFormat('!Y-m-d', $v['fim']) : null,
            pagina: (int) ($v['pagina'] ?? 1),
            porPagina: (int) config('djen.itens_por_pagina'),
        );
    }
}
