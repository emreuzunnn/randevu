<?php

namespace App\Models;

use Database\Factories\AppointmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Appointment extends Model
{
    /** @use HasFactory<AppointmentFactory> */
    use HasFactory;

    protected $fillable = [
        'studio_id',
        'created_by_user_id',
        'assigned_driver_user_id',
        'appointment_type',
        'first_name',
        'last_name',
        'phone_country_code',
        'phone_number',
        'hotel_name',
        'room_number',
        'place',
        'photo_path',
        'customer_notes',
        'pax',
        'appointment_at',
        'status',
        'is_old_customer',
        'notes',
        'source_image_path',
    ];

    protected function casts(): array
    {
        return [
            'appointment_at' => 'datetime',
            'is_old_customer' => 'boolean',
            'pax' => 'integer',
        ];
    }

    public function studio(): BelongsTo
    {
        return $this->belongsTo(Studio::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function assignedDriver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_driver_user_id');
    }
}
