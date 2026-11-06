<?php

namespace App\Notifications\WhatsApp;

/**
 * Provedor de envio de WhatsApp. Implementado: "log".
 * Para plugar Evolution API, Z-API ou Meta Cloud API, crie uma classe que
 * implemente esta interface (chamando o provedor pelo Http client) e
 * registre o driver em AppServiceProvider::registerWhatsApp().
 */
interface WhatsAppDriver
{
    /**
     * @param  string  $numero  Só dígitos, com DDI (ex.: 5592999999999).
     */
    public function enviar(string $numero, WhatsAppMessage $mensagem): void;
}
