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
        'assigned_artist_user_id',
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
        'price',
        'appointment_at',
        'status',
        'driver_status',
        'artist_status',
        'is_old_customer',
        'notes',
        'source_image_path',
        'tattoo_image_paths',
        'completed_tattoo_image_path',
        'pickup_required',
    ];

    protected function casts(): array
    {
        return [
            'appointment_at' => 'datetime',
            'is_old_customer' => 'boolean',
            'pax' => 'integer',
            'price' => 'decimal:2',
            'tattoo_image_paths' => 'array',
            'pickup_required' => 'boolean',
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

    public function assignedArtist(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_artist_user_id');
    }
}
