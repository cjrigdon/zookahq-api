<?php

namespace Database\Seeders;

use App\Models\Notifications\NotificationPreference;
use App\Models\Notifications\SystemNotification;
use App\Models\PlatformUser;
use App\Models\User;
use Illuminate\Database\Seeder;

class NotificationSeeder extends Seeder
{
    public function run(): void
    {
        $user = tenancy()->initialized
            ? User::query()->where('email', 'admin@demo.test')->first()
            : PlatformUser::query()->where('email', 'admin@zookahq.test')->first();

        if (! $user) {
            return;
        }

        NotificationPreference::query()->firstOrCreate(
            ['user_id' => $user->id],
            [
                'desktop' => true,
                'email' => true,
                'push' => false,
            ],
        );

        if (SystemNotification::query()->where('user_id', $user->id)->exists()) {
            return;
        }

        $samples = tenancy()->initialized
            ? [
                [
                    'type' => 'system.welcome',
                    'title' => 'Welcome to ZookaHQ',
                    'body' => 'Desktop notifications appear under the bell. You can also enable email and later push for mobile apps.',
                    'action_url' => '/profile',
                ],
                [
                    'type' => 'event.status',
                    'title' => 'Harbor Lights Mixer is an opportunity',
                    'body' => 'Follow up on this opportunity before it goes tentative.',
                    'action_url' => '/booking/opportunities',
                ],
                [
                    'type' => 'operations.assigned',
                    'title' => 'Work order assigned',
                    'body' => 'Ballroom AV check is due the day before the Acme Annual Gala.',
                    'action_url' => '/operations/work-orders',
                ],
            ]
            : [
                [
                    'type' => 'system.welcome',
                    'title' => 'Welcome to the platform console',
                    'body' => 'Notifications for tenants and modules will appear here.',
                    'action_url' => '/tenants',
                ],
            ];

        foreach ($samples as $index => $sample) {
            SystemNotification::create([
                'user_id' => $user->id,
                'type' => $sample['type'],
                'title' => $sample['title'],
                'body' => $sample['body'],
                'action_url' => $sample['action_url'],
                'channels' => ['desktop'],
                'read_at' => $index === 2 ? now()->subHour() : null,
                'created_at' => now()->subMinutes(30 - ($index * 8)),
                'updated_at' => now()->subMinutes(30 - ($index * 8)),
            ]);
        }
    }
}
