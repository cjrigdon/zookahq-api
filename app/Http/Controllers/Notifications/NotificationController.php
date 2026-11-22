<?php

namespace App\Http\Controllers\Notifications;

use App\Http\Controllers\Controller;
use App\Models\Notifications\SystemNotification;
use App\Services\Notifications\NotificationDispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(private NotificationDispatcher $dispatcher) {}

    public function index(Request $request): JsonResponse
    {
        $items = SystemNotification::query()
            ->where('user_id', $request->user()->id)
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn (SystemNotification $notification) => $this->serialize($notification));

        return response()->json(['data' => $items]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        $count = SystemNotification::query()
            ->where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->count();

        return response()->json(['count' => $count]);
    }

    public function markRead(Request $request, SystemNotification $notification): JsonResponse
    {
        abort_unless((int) $notification->user_id === (int) $request->user()->id, 404);

        if ($notification->read_at === null) {
            $notification->forceFill(['read_at' => now()])->save();
        }

        return response()->json(['data' => $this->serialize($notification->fresh())]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        SystemNotification::query()
            ->where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['message' => 'All notifications marked as read']);
    }

    public function preferences(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->serializePreferences($this->dispatcher->preferencesFor($request->user())),
        ]);
    }

    public function updatePreferences(Request $request): JsonResponse
    {
        $data = $request->validate([
            'desktop' => ['required', 'boolean'],
            'email' => ['required', 'boolean'],
            'push' => ['required', 'boolean'],
        ]);

        $preferences = $this->dispatcher->preferencesFor($request->user());
        $preferences->update($data);

        return response()->json(['data' => $this->serializePreferences($preferences->fresh())]);
    }

    private function serialize(SystemNotification $notification): array
    {
        return [
            'id' => $notification->id,
            'type' => $notification->type,
            'title' => $notification->title,
            'body' => $notification->body,
            'action_url' => $notification->action_url,
            'channels' => $notification->channels,
            'read_at' => $notification->read_at?->toIso8601String(),
            'created_at' => $notification->created_at?->toIso8601String(),
        ];
    }

    private function serializePreferences($preferences): array
    {
        return [
            'desktop' => (bool) $preferences->desktop,
            'email' => (bool) $preferences->email,
            'push' => (bool) $preferences->push,
        ];
    }
}
