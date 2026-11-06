<?php

namespace App\Notifications\Channels;

use App\Notifications\WhatsApp\ParaWhatsApp;
use App\Notifications\WhatsApp\WhatsAppDriver;
use Illuminate\Notifications\Notification;

/**
 * Canal de notificação desacoplado do provedor: delega ao WhatsAppDriver
 * configurado (config djen.whatsapp.driver).
 */
final class WhatsAppChannel
{
    public function __construct(
        private readonly WhatsAppDriver $driver,
    ) {}

    public function send(object $notifiable, Notification $notification): void
    {
        if (! $notification instanceof ParaWhatsApp || ! method_exists($notifiable, 'routeNotificationFor')) {
            return;
        }

        $numero = $notifiable->routeNotificationFor('whatsapp', $notification);

        if (! is_string($numero) || $numero === '') {
            return;
        }

        $this->driver->enviar($numero, $notification->toWhatsApp($notifiable));
    }
}
