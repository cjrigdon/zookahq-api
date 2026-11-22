<?php

namespace App\Http\Controllers\Events;

use App\Http\Controllers\Controller;
use App\Models\Booking\Contact;
use App\Models\Events\Event;
use App\Models\Events\EventNote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class EventNoteController extends Controller
{
    public function index(Event $event): JsonResponse
    {
        $this->authorizePermission('events.events.view');

        $notes = $event->notes()
            ->with(['contact', 'author'])
            ->latest()
            ->get();

        return response()->json(['data' => $notes]);
    }

    public function store(Request $request, Event $event): JsonResponse
    {
        $this->authorizePermission('events.events.manage');

        $data = $this->validated($request, $event);
        $data['event_id'] = $event->id;
        $data['created_by'] = auth()->id();
        $data['updated_by'] = auth()->id();

        $note = EventNote::create($data);

        return response()->json(['data' => $note->load(['contact', 'author'])], 201);
    }

    public function update(Request $request, Event $event, EventNote $note): JsonResponse
    {
        $this->authorizePermission('events.events.manage');
        $this->ensureNoteBelongsToEvent($event, $note);

        $data = $this->validated($request, $event, updating: true);
        $data['updated_by'] = auth()->id();
        $note->update($data);

        return response()->json(['data' => $note->fresh(['contact', 'author'])]);
    }

    public function destroy(Event $event, EventNote $note): JsonResponse
    {
        $this->authorizePermission('events.events.manage');
        $this->ensureNoteBelongsToEvent($event, $note);
        $note->delete();

        return response()->json(['message' => 'Note deleted']);
    }

    protected function validated(Request $request, Event $event, bool $updating = false): array
    {
        $data = $request->validate([
            'body' => [$updating ? 'sometimes' : 'required', 'string'],
            'contact_id' => ['nullable', 'integer', 'exists:contacts,id'],
        ]);

        if (array_key_exists('contact_id', $data) && $data['contact_id']) {
            if (! $event->account_id) {
                throw ValidationException::withMessages([
                    'contact_id' => 'Event has no account to relate a contact to.',
                ]);
            }

            $belongs = Contact::query()
                ->where('id', $data['contact_id'])
                ->where('account_id', $event->account_id)
                ->exists();
            if (! $belongs) {
                throw ValidationException::withMessages([
                    'contact_id' => 'Contact must belong to this event\'s account.',
                ]);
            }
        }

        return $data;
    }

    protected function ensureNoteBelongsToEvent(Event $event, EventNote $note): void
    {
        if ((int) $note->event_id !== (int) $event->id) {
            abort(404);
        }
    }

    protected function authorizePermission(string $permission): void
    {
        if (! auth()->user()?->can($permission)) {
            abort(403, 'Unauthorized.');
        }
    }
}
