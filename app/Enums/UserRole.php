<?php

namespace App\Enums;

enum UserRole: string
{
    // Platform seviyesi
    case Admin = 'admin';
    case Yonetici = 'yonetici';

    // Stüdyo yönetimi
    case Supervisor = 'supervisor';

    // Stüdyo çalışanları
    case Designer = 'designer';
    case Artist = 'artist';
    case Info = 'info';
    case Sofor = 'sofor';
    case Calisan = 'calisan'; // Geriye dönük uyumluluk

    // Bağımsız kullanıcılar
    case KullaniciRol = 'kullanici_rol';
    case Kullanici = 'kullanici';

    public function label(): string
    {
        return match ($this) {
            self::Admin        => 'Admin',
            self::Yonetici     => 'Yönetici',
            self::Supervisor   => 'Süpervizör',
            self::Designer     => 'Tasarımcı',
            self::Artist       => 'Artist',
            self::Info         => 'Info',
            self::Sofor        => 'Şoför',
            self::Calisan      => 'Çalışan',
            self::KullaniciRol => 'Kullanıcı (Rol)',
            self::Kullanici    => 'Kullanıcı',
        };
    }

    /** Stüdyoya atanabilen roller */
    public static function studioRoles(): array
    {
        return [
            self::Supervisor,
            self::Designer,
            self::Artist,
            self::Info,
            self::Sofor,
            self::Calisan,
        ];
    }

    /** Randevu oluşturma/düzenleme yetkisi olan stüdyo rolleri */
    public static function appointmentManagerRoles(): array
    {
        return [
            self::Supervisor,
            self::Designer,
            self::Info,
            self::Sofor,
            self::Calisan,
        ];
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
            'yönetici'   => 'yonetici',
            'süpervizör' => 'supervisor',
            'tasarımcı'                         => 'designer',
            'sanatçı'                           => 'artist',
            'şoför'                             => 'sofor',
            'çalışan'                           => 'calisan',
            'kullanıcı'                         => 'kullanici',
            default                             => mb_strtolower($role),
        });
    }
}
