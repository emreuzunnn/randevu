<?php

namespace App\Models;

use Database\Factories\StudioFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Studio extends Model
{
    /** @use HasFactory<StudioFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'location',
        'slug',
        'logo_path',
        'about',
        'opening_time',
        'closing_time',
        'gallery_images',
        'owner_user_id',
        'shop_id',
    ];

    protected function casts(): array
    {
        return [
            'gallery_images' => 'array',
        ];
    }

public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot([
                'role',
                'work_status',
                'is_active',
                'joined_at',
                'left_at',
            ])
            ->withTimestamps();
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }
}
