<?php

namespace App\Services\Djen\Exceptions;

/** Timeout, falha de conexão ou erro 5xx depois de esgotadas as tentativas. */
final class DjenUnavailableException extends DjenException {}
