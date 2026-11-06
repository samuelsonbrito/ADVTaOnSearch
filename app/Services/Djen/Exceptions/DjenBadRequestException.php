<?php

namespace App\Services\Djen\Exceptions;

/** Demais erros 4xx (ex.: parâmetros inválidos). Não são retentados. */
final class DjenBadRequestException extends DjenException {}
