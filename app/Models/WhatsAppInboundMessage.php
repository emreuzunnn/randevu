<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsAppInboundMessage extends Model
{
    protected $table = 'whatsapp_inbound_messages';

    protected $fillable = [
        'message_id',
        'from_phone',
        'profile_name',
        'message_type',
        'message_body',
        'payload',
        'auto_reply_status',
        'auto_reply_message_id',
        'auto_reply_error',
        'received_at',
        'auto_replied_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'received_at' => 'datetime',
            'auto_replied_at' => 'datetime',
        ];
    }
}
