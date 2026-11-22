<?php

namespace App\Models\Notifications;

use Illuminate\Database\Eloquent\Model;

class NotificationPreference extends Model
{
    protected $fillable = [
        'user_id',
        'desktop',
        'email',
        'push',
    ];

    protected function casts(): array
    {
        return [
            'desktop' => 'boolean',
            'email' => 'boolean',
            'push' => 'boolean',
        ];
    }

    /** @return list<string> */
    public function enabledChannels(): array
    {
        return array_values(array_filter([
            $this->desktop ? SystemNotification::CHANNEL_DESKTOP : null,
            $this->email ? SystemNotification::CHANNEL_EMAIL : null,
            $this->push ? SystemNotification::CHANNEL_PUSH : null,
        ]));
    }
}
