<?php

namespace App\Http\Controllers\Events;

use App\Http\Controllers\Concerns\AuthorizesPermissions;
use App\Http\Controllers\Controller;
use App\Models\Events\Event;
use App\Models\Events\EventDignitary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventDignitaryController extends Controller
{
    use AuthorizesPermissions;

    public function store(Request $request, Event $event): JsonResponse
    {
        $this->authorizePermission('events.events.manage');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:255'],
            'organization' => ['nullable', 'string', 'max:255'],
        ]);

        $data['event_id'] = $event->id;
        $data['created_by'] = auth()->id();
        $data['updated_by'] = auth()->id();

        $dignitary = EventDignitary::create($data);

        return response()->json(['data' => $dignitary], 201);
    }

    public function update(Request $request, Event $event, EventDignitary $dignitary): JsonResponse
    {
        $this->authorizePermission('events.events.manage');
        $this->ensureBelongs($event, $dignitary);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:255'],
            'organization' => ['nullable', 'string', 'max:255'],
        ]);
        $data['updated_by'] = auth()->id();
        $dignitary->update($data);

        return response()->json(['data' => $dignitary->fresh()]);
    }

    public function destroy(Event $event, EventDignitary $dignitary): JsonResponse
    {
        $this->authorizePermission('events.events.manage');
        $this->ensureBelongs($event, $dignitary);
        $dignitary->delete();

        return response()->json(['message' => 'Dignitary deleted']);
    }

    protected function ensureBelongs(Event $event, EventDignitary $dignitary): void
    {
        if ((int) $dignitary->event_id !== (int) $event->id) {
            abort(404);
        }
    }
}
