<?php

namespace App\Notifications\WhatsApp;

final readonly class WhatsAppMessage
{
    public function __construct(
        public string $texto,
    ) {}
}
