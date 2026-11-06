<?php

namespace App\Services\Djen\Exceptions;

/**
 * HTTP 403. Na prática acontece quando a requisição sai de um IP fora do
 * Brasil (bloqueio geográfico da CloudFront na frente da API).
 */
final class DjenBlockedException extends DjenException {}
