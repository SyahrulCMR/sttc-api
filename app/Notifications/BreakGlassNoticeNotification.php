<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BreakGlassNoticeNotification extends Notification
{
    public function __construct(private readonly User $target) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('[STTC] Prosedur break-glass dijalankan')
            ->line("Prosedur break-glass dijalankan untuk akun: {$this->target->name} ({$this->target->identifier}).")
            ->line('Kata sandi akun tersebut telah direset & 2FA dinonaktifkan sementara.')
            ->line('Bila ini tidak sesuai harapan, segera investigasi.');
    }
}
