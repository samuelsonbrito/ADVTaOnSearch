<?php

namespace App\Services\Djen\Exceptions;

/**
 * A integração real ainda não traduz a resposta da Comunica API: o
 * mapeamento depende de uma resposta real salva em tests/Fixtures/djen/.
 */
final class DjenMappingPendingException extends DjenException
{
    public function __construct()
    {
        parent::__construct(
            'A integração com a API do CNJ está em implantação: o mapeamento da resposta aguarda uma amostra real da API.',
        );
    }
}
