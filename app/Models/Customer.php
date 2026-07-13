<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    protected $fillable = [
        'studio_id',
        'first_name',
        'last_name',
        'phone_country_code',
        'phone_number',
        'hotel_name',
        'room_number',
        'place',
        'photo_path',
        'customer_notes',
        'first_appointment_at',
        'last_appointment_at',
        'appointments_count',
    ];

    protected function casts(): array
    {
        return [
            'first_appointment_at' => 'datetime',
            'last_appointment_at' => 'datetime',
            'appointments_count' => 'integer',
        ];
    }

    public function studio(): BelongsTo
    {
        return $this->belongsTo(Studio::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }
}
