<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Yonetici = 'yonetici';
    case Supervisor = 'supervisor';
    case Sofor = 'sofor';
    case Calisan = 'calisan';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::Yonetici => 'Yonetici',
            self::Supervisor => 'Supervisor',
            self::Sofor => 'Sofor',
            self::Calisan => 'Calisan',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $role): string => $role->value,
            self::cases(),
        );
    }

    public static function fromValue(self|string $role): self
    {
        if ($role instanceof self) {
            return $role;
        }

        return self::from(match (mb_strtolower($role)) {
            'yönetici' => 'yonetici',
            'şoför' => 'sofor',
            'çalışan' => 'calisan',
            default => mb_strtolower($role),
        });
    }
}
