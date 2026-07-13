<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffEarning extends Model
{
    protected $fillable = [
        'appointment_id',
        'studio_id',
        'user_id',
        'role',
        'commission_rate',
        'gross_amount',
        'earning_amount',
        'status',
        'paid_at',
        'paid_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'commission_rate' => 'decimal:2',
            'gross_amount' => 'decimal:2',
            'earning_amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function studio(): BelongsTo
    {
        return $this->belongsTo(Studio::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function paidBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by_user_id');
    }
}
