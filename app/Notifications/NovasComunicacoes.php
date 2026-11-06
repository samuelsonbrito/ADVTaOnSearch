<?php

namespace App\Notifications;

use App\Models\Alerta;
use App\Models\MonitoringTarget;
use App\Models\User;
use App\Notifications\Channels\WhatsAppChannel;
use App\Notifications\WhatsApp\ParaWhatsApp;
use App\Notifications\WhatsApp\WhatsAppMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

/** Avisa o usuário sobre comunicações novas encontradas para um alvo. */
class NovasComunicacoes extends Notification implements ParaWhatsApp
{
    /** Máximo de comunicações listadas no e-mail. */
    public const LIMITE_EMAIL = 15;

    /**
     * @param  Collection<int, Alerta>  $alertas  Com a relação communication carregada.
     */
    public function __construct(
        public readonly MonitoringTarget $alvo,
        public readonly Collection $alertas,
    ) {}

    /**
     * @return list<string|class-string>
     */
    public function via(User $notifiable): array
    {
        return $notifiable->whatsapp ? ['mail', WhatsAppChannel::class] : ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $total = $this->alertas->count();
        $alvo = $this->alvo->descricao();

        $mail = (new MailMessage)
            ->subject($total === 1 ? "1 nova comunicação — {$alvo}" : "{$total} novas comunicações — {$alvo}")
            ->greeting("Olá, {$notifiable->name}!")
            ->line($total === 1
                ? "Encontramos 1 nova comunicação no DJEN para **{$alvo}**:"
                : "Encontramos {$total} novas comunicações no DJEN para **{$alvo}**:");

        foreach ($this->alertas->take(self::LIMITE_EMAIL) as $alerta) {
            $c = $alerta->communication;
            $mail->line(sprintf(
                '• %s — %s · %s · %s',
                $c->data_disponibilizacao->format('d/m/Y'),
                $c->tribunal,
                MonitoringTarget::formatarCnj($c->numero_processo),
                $c->tipo_comunicacao ?? 'Comunicação',
            ));
        }

        if ($total > self::LIMITE_EMAIL) {
            $mail->line('… e mais '.($total - self::LIMITE_EMAIL).'.');
        }

        return $mail
            ->action('Ver no painel', route('monitoramento.index', ['alvo' => $this->alvo->id]))
            ->line('Você recebe este aviso porque cadastrou este alvo no monitoramento. Para parar, desative ou exclua o alvo no painel.');
    }

    public function toWhatsApp(object $notifiable): WhatsAppMessage
    {
        $total = $this->alertas->count();
        $plural = $total === 1 ? 'nova comunicação' : 'novas comunicações';

        return new WhatsAppMessage(
            config('app.name').": {$total} {$plural} no DJEN para {$this->alvo->descricao()}. "
            .'Veja no painel: '.route('monitoramento.index', ['alvo' => $this->alvo->id]),
        );
    }
}
