<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Company extends Model
{
    protected $fillable = [
        'name',
        'logo_path',
        'address',
        'phone',
        'email',
        'about',
        'website',
        'gallery_images',
        'is_active',
        'max_shop_count',
        'max_studio_count',
    ];

    protected function casts(): array
    {
        return [
            'is_active'        => 'boolean',
            'max_shop_count'   => 'integer',
            'max_studio_count' => 'integer',
            'gallery_images'   => 'array',
        ];
    }

    public function shops(): HasMany
    {
        return $this->hasMany(Shop::class);
    }

    public function studios(): HasManyThrough
    {
        return $this->hasManyThrough(Studio::class, Shop::class);
    }

    public function appointments(): HasManyThrough
    {
        return $this->hasManyThrough(
            Appointment::class,
            Studio::class,
            'shop_id',   // Studio → Shop FK (aslında studio shop_id üzerinden gidiyor; HasManyThrough zinciri için ara tablo gerekiyor)
            'studio_id', // Appointment → Studio FK
            'id',
            'id',
        );
    }

    // ── Sınır kontrolleri ──────────────────────────────────

    /** Dükkan oluşturulabilir mi? */
    public function canAddShop(): bool
    {
        if ($this->max_shop_count === 0) {
            return true;
        }

        return $this->shops()->count() < $this->max_shop_count;
    }

    /** Stüdyo oluşturulabilir mi? (tüm dükkanlar genelinde) */
    public function canAddStudio(): bool
    {
        if ($this->max_studio_count === 0) {
            return true;
        }

        return $this->studios()->count() < $this->max_studio_count;
    }

    /** Mevcut dükkan sayısı */
    public function currentShopCount(): int
    {
        return $this->shops()->count();
    }

    /** Mevcut stüdyo sayısı */
    public function currentStudioCount(): int
    {
        return $this->studios()->count();
    }
}
