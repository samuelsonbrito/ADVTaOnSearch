<?php

namespace App\Events;

/**
 * Disparado pelo DjenClient a cada requisição HTTP real à Comunica API
 * (respostas vindas do cache não disparam).
 */
final readonly class ChamadaDjenConcluida
{
    /**
     * @param  array<string, scalar>  $parametros
     * @param  'sucesso'|'limitado'|'bloqueado'|'indisponivel'|'recusado'|'invalido'  $resultado
     */
    public function __construct(
        public string $endpoint,
        public array $parametros,
        public ?int $status,
        public string $resultado,
        public int $duracaoMs,
        public ?int $itens = null,
        public ?int $ratelimitRestante = null,
        public ?string $erro = null,
    ) {}
}
