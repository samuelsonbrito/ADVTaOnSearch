<?php

namespace App\Services\Djen\Exceptions;

/** Resposta 2xx que não é um objeto/lista JSON (ex.: HTML). */
final class DjenUnexpectedResponseException extends DjenException {}
