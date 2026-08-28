<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BreakGlassCredentialNotification extends Notification
{
    public function __construct(
        private readonly string $temporaryPassword,
        private readonly int $relockHours,
    ) {}

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
            ->subject('[STTC] Akun Super Admin Anda direset (prosedur darurat)')
            ->line('Prosedur break-glass telah dijalankan untuk akun Super Admin Anda.')
            ->line('Kata sandi sementara: **'.$this->temporaryPassword.'**')
            ->line('2FA pada akun ini telah dinonaktifkan.')
            ->line("Segera masuk, ganti kata sandi, dan aktifkan kembali 2FA dalam {$this->relockHours} jam.")
            ->line('Bila tidak, akun akan dikunci (suspended) otomatis.');
    }
}
