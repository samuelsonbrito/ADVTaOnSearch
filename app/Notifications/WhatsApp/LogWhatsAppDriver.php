<?php

namespace App\Notifications\WhatsApp;

use Psr\Log\LoggerInterface;

/** Não envia nada: registra a mensagem no log (desenvolvimento/testes). */
final class LogWhatsAppDriver implements WhatsAppDriver
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {}

    public function enviar(string $numero, WhatsAppMessage $mensagem): void
    {
        $this->logger->info('[whatsapp:log] mensagem não enviada (driver log)', [
            // LGPD: não registra o número completo.
            'para' => self::mascarar($numero),
            'texto' => $mensagem->texto,
        ]);
    }

    public static function mascarar(string $numero): string
    {
        $len = strlen($numero);

        return $len <= 4 ? str_repeat('*', $len) : str_repeat('*', $len - 4).substr($numero, -4);
    }
}
