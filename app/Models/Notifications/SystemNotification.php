<?php

namespace App\Models\Notifications;

use Illuminate\Database\Eloquent\Model;

class SystemNotification extends Model
{
    public const CHANNEL_DESKTOP = 'desktop';
    public const CHANNEL_EMAIL = 'email';
    public const CHANNEL_PUSH = 'push';

    public const CHANNELS = [
        self::CHANNEL_DESKTOP,
        self::CHANNEL_EMAIL,
        self::CHANNEL_PUSH,
    ];

    protected $table = 'system_notifications';

    protected $fillable = [
        'user_id',
        'type',
        'title',
        'body',
        'action_url',
        'data',
        'channels',
        'read_at',
        'emailed_at',
        'pushed_at',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'channels' => 'array',
            'read_at' => 'datetime',
            'emailed_at' => 'datetime',
            'pushed_at' => 'datetime',
        ];
    }

    public function isRead(): bool
    {
        return $this->read_at !== null;
    }
}
