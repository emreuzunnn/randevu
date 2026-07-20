<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Company extends Model
{
    protected $fillable = [
        'name',
        'manager_user_id',
        'logo_path',
        'address',
        'phone',
        'email',
        'about',
        'website',
        'gallery_images',
        'ticket_pdf_template',
        'is_active',
        'max_studio_count',
    ];

    protected function casts(): array
    {
        return [
            'is_active'        => 'boolean',
            'max_studio_count' => 'integer',
            'gallery_images'   => 'array',
            'ticket_pdf_template' => 'array',
        ];
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_user_id');
    }

    public function studios(): HasMany
    {
        return $this->hasMany(Studio::class);
    }

    public function appointments(): HasManyThrough
    {
        return $this->hasManyThrough(
            Appointment::class,
            Studio::class,
            'company_id',
            'studio_id', // Appointment → Studio FK
            'id',
            'id',
        );
    }

    // ── Sınır kontrolleri ──────────────────────────────────

    /** Stüdyo oluşturulabilir mi? */
    public function canAddStudio(): bool
    {
        if ($this->max_studio_count === 0) {
            return true;
        }

        return $this->studios()->count() < $this->max_studio_count;
    }

    /** Mevcut stüdyo sayısı */
    public function currentStudioCount(): int
    {
        return $this->studios()->count();
    }
}
