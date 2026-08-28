<?php

namespace App\Enums;

enum UserStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Suspended = 'suspended';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Aktif',
            self::Inactive => 'Tidak Aktif',
            self::Suspended => 'Ditangguhkan',
        };
    }

    /**
     * Hanya akun aktif yang boleh menyelesaikan login.
     */
    public function canLogin(): bool
    {
        return $this === self::Active;
    }
}
