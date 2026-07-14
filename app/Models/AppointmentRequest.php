<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppointmentRequest extends Model
{
    protected $fillable = [
        'requester_user_id',
        'target_user_id',
        'studio_id',
        'appointment_id',
        'request_type',
        'requested_at',
        'image_path',
        'tattoo_image_paths',
        'pickup_required',
        'notes',
        'first_name',
        'last_name',
        'phone_country_code',
        'phone_number',
        'hotel_name',
        'room_number',
        'place',
        'pax',
        'price',
        'deposit_amount',
        'payment_method',
        'ticket_types',
        'tattoo_type',
        'status',
        'response_notes',
        'responded_at',
    ];

    protected function casts(): array
    {
        return [
            'requested_at' => 'datetime',
            'responded_at' => 'datetime',
            'pax' => 'integer',
            'price' => 'decimal:2',
            'deposit_amount' => 'decimal:2',
            'ticket_types' => 'array',
            'tattoo_image_paths' => 'array',
            'pickup_required' => 'boolean',
        ];
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_user_id');
    }

    public function target(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    public function studio(): BelongsTo
    {
        return $this->belongsTo(Studio::class);
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }
}
