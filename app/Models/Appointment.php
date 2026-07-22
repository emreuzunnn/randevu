<?php

namespace App\Models;

use Database\Factories\AppointmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Appointment extends Model
{
    /** @use HasFactory<AppointmentFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::saved(function (Appointment $appointment): void {
            app(\App\Services\CustomerService::class)->syncForAppointment($appointment);
            app(\App\Services\StaffEarningService::class)->syncForAppointment($appointment);
        });
    }

    protected $fillable = [
        'studio_id',
        'customer_id',
        'created_by_user_id',
        'assigned_info_user_id',
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
        'deposit_amount',
        'payment_method',
        'ticket_types',
        'tattoo_type',
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
        'public_token',
    ];

    protected function casts(): array
    {
        return [
            'appointment_at' => 'datetime',
            'is_old_customer' => 'boolean',
            'pax' => 'integer',
            'price' => 'decimal:2',
            'deposit_amount' => 'decimal:2',
            'ticket_types' => 'array',
            'tattoo_image_paths' => 'array',
            'pickup_required' => 'boolean',
        ];
    }

    public function studio(): BelongsTo
    {
        return $this->belongsTo(Studio::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function assignedInfo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_info_user_id');
    }

    public function assignedArtist(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_artist_user_id');
    }

    public function earning(): HasOne
    {
        return $this->hasOne(StaffEarning::class);
    }
}
