<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppMessageLog extends Model
{
    protected $table = 'whatsapp_message_logs';

    protected $fillable = [
        'appointment_id',
        'event_type',
        'recipient_phone',
        'template_name',
        'language_code',
        'status',
        'whatsapp_message_id',
        'error_message',
        'request_payload',
        'response_payload',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'request_payload' => 'array',
            'response_payload' => 'array',
            'sent_at' => 'datetime',
        ];
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }
}
