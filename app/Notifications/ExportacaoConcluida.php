<?php

namespace App\Notifications;

use App\Models\Exportacao;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ExportacaoConcluida extends Notification
{
    public function __construct(
        public readonly Exportacao $exportacao,
    ) {}

    /** @return list<string> */
    public function via(User $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $e = $this->exportacao;
        $total = (int) $e->total_itens;

        return (new MailMessage)
            ->subject("Sua exportação está pronta ({$e->formato->rotulo()})")
            ->greeting("Olá, {$notifiable->name}!")
            ->line(sprintf(
                'O arquivo %s com %d %s (%s) está pronto para download.',
                $e->formato->rotulo(),
                $total,
                $total === 1 ? 'comunicação' : 'comunicações',
                mb_strtolower($e->origem->rotulo()),
            ))
            ->action('Baixar arquivo', route('exportacoes.download', $e))
            ->line('O arquivo fica disponível até '.$e->expiraEm()->timezone((string) config('djen.monitoring.timezone'))->format('d/m/Y').'.');
    }
}
