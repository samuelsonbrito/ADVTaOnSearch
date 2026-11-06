<?php

namespace App\Services\Djen;

/**
 * Resposta HTTP bem-sucedida da Comunica API, já decodificada.
 *
 * Propositalmente genérica: o mapeamento dos campos para DTOs de domínio
 * (comunicação, destinatário, advogado) só será feito a partir de uma
 * resposta real salva em tests/Fixtures/djen/.
 */
final readonly class DjenResponse
{
    /**
     * @param  array<array-key, mixed>  $json
     * @param  array<string, list<string>>  $headers
     */
    public function __construct(
        public int $status,
        public array $json,
        public array $headers = [],
        public bool $fromCache = false,
    ) {}

    public function header(string $name): ?string
    {
        foreach ($this->headers as $key => $values) {
            if (strcasecmp($key, $name) === 0) {
                return $values[0] ?? null;
            }
        }

        return null;
    }

    public function withFromCache(bool $fromCache = true): self
    {
        return new self($this->status, $this->json, $this->headers, $fromCache);
    }
}
