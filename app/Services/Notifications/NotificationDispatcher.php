<?php

namespace App\Services\Notifications;

use App\Mail\SystemNotificationMail;
use App\Models\Notifications\NotificationPreference;
use App\Models\Notifications\NotificationPushToken;
use App\Models\Notifications\SystemNotification;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class NotificationDispatcher
{
    /**
     * @param  list<string>|null  $channels
     * @param  array<string, mixed>  $data
     */
    public function send(
        Authenticatable $user,
        string $title,
        string $body,
        ?array $channels = null,
        ?string $actionUrl = null,
        string $type = 'system',
        array $data = [],
    ): ?SystemNotification {
        $requested = $this->normalizeChannels($channels ?? SystemNotification::CHANNELS);
        $effective = array_values(array_intersect($requested, $this->preferencesFor($user)->enabledChannels()));

        if ($effective === []) {
            return null;
        }

        $desktop = in_array(SystemNotification::CHANNEL_DESKTOP, $effective, true);

        $notification = SystemNotification::create([
            'user_id' => $user->getAuthIdentifier(),
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'action_url' => $actionUrl,
            'data' => $data ?: null,
            'channels' => $effective,
            'read_at' => $desktop ? null : now(),
        ]);

        if (in_array(SystemNotification::CHANNEL_EMAIL, $effective, true)) {
            $this->sendEmail($user, $notification);
        }

        if (in_array(SystemNotification::CHANNEL_PUSH, $effective, true)) {
            $this->queuePush($user, $notification);
        }

        return $notification;
    }

    public function preferencesFor(Authenticatable $user): NotificationPreference
    {
        $defaults = config('notifications.defaults');

        return NotificationPreference::query()->firstOrCreate(
            ['user_id' => $user->getAuthIdentifier()],
            [
                'desktop' => (bool) $defaults['desktop'],
                'email' => (bool) $defaults['email'],
                'push' => (bool) $defaults['push'],
            ],
        );
    }

    /** @param  list<string>  $channels */
    private function normalizeChannels(array $channels): array
    {
        return array_values(array_unique(array_filter(
            $channels,
            fn ($channel) => in_array($channel, SystemNotification::CHANNELS, true),
        )));
    }

    private function sendEmail(Authenticatable $user, SystemNotification $notification): void
    {
        $email = $user->email ?? null;
        if (! is_string($email) || $email === '') {
            return;
        }

        try {
            Mail::to($email)->send(new SystemNotificationMail(
                $notification->title,
                $notification->body,
                $notification->action_url,
            ));
            $notification->forceFill(['emailed_at' => now()])->save();
        } catch (\Throwable $e) {
            Log::warning('Notification email failed', [
                'notification_id' => $notification->id,
                'user_id' => $user->getAuthIdentifier(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Push delivery is reserved for mobile apps. Tokens are stored now so
     * a later worker can send to devices without changing the data model.
     */
    private function queuePush(Authenticatable $user, SystemNotification $notification): void
    {
        $hasTokens = NotificationPushToken::query()
            ->where('user_id', $user->getAuthIdentifier())
            ->exists();

        Log::info('Push notification queued for later mobile delivery', [
            'notification_id' => $notification->id,
            'user_id' => $user->getAuthIdentifier(),
            'has_device_tokens' => $hasTokens,
        ]);
    }
}
