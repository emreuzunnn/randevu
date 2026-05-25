<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PushNotificationDelivery extends Model
{
    protected $fillable = [
        'push_notification_id',
        'user_id',
        'push_token_id',
        'platform',
        'token_hash',
        'status',
        'fcm_status',
        'fcm_error_code',
        'fcm_message_id',
        'error_message',
        'response',
        'attempted_at',
    ];

    protected function casts(): array
    {
        return [
            'response'     => 'array',
            'attempted_at' => 'datetime',
        ];
    }

    public function notification(): BelongsTo
    {
        return $this->belongsTo(PushNotification::class, 'push_notification_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function pushToken(): BelongsTo
    {
        return $this->belongsTo(PushToken::class);
    }
}
