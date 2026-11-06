<?php

namespace App\Notifications\WhatsApp;

/** Notificações que sabem se representar como mensagem de WhatsApp. */
interface ParaWhatsApp
{
    public function toWhatsApp(object $notifiable): WhatsAppMessage;
}
