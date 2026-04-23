<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TelegramUpdate extends Model
{
    protected $fillable = [
        'telegram_update_id',
        'direction',
        'kind',
        'chat_id',
        'telegram_user_id',
        'telegram_message_id',
        'text',
        'payload',
        'success',
        'error',
        'handled_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'success' => 'boolean',
        'handled_at' => 'datetime',
    ];
}