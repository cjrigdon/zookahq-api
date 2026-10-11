<?php

namespace App\Http\Controllers\Events;

use App\Http\Controllers\Concerns\AuthorizesPermissions;
use App\Http\Controllers\Controller;
use App\Models\Events\Event;
use App\Models\Events\EventFunction;
use App\Services\EventChangeLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventController extends Controller
{
    use AuthorizesPermissions;

    public function index(Request $request): JsonResponse
    {
        $this->authorizePermission('events.events.view');

        $query = Event::with('owner')->latest('starts_at');

        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }

        if ($search = $request->string('search')->toString()) {
            $query->where('name', 'like', "%{$search}%");
        }

        return response()->json(['data' => $query->paginate(25)]);
    }

    public function calendar(Request $request): JsonResponse
    {
        $this->authorizePermission('events.calendar.view');

        $from = $request->date('from') ?? now()->startOfMonth();
        $to = $request->date('to') ?? now()->endOfMonth();

        $events = Event::query()
            ->where(function ($q) use ($from, $to) {
                $q->whereBetween('starts_at', [$from, $to])
                    ->orWhereBetween('ends_at', [$from, $to])
                    ->orWhere(function ($inner) use ($from, $to) {
                        $inner->where('starts_at', '<=', $from)->where('ends_at', '>=', $to);
                    });
            })
            ->orderBy('starts_at')
            ->get(['id', 'name', 'status', 'starts_at', 'ends_at', 'venue_space']);

        $functions = EventFunction::with('event:id,name')
            ->whereBetween('starts_at', [$from, $to])
            ->orderBy('starts_at')
            ->get();

        return response()->json([
            'data' => [
                'events' => $events,
                'functions' => $functions,
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizePermission('events.events.manage');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'max:50'],
            'opportunity_id' => ['nullable', 'integer'],
            'account_id' => ['nullable', 'integer'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after_or_equal:starts_at'],
            'venue_space' => ['nullable', 'string', 'max:255'],
            'expected_attendance' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string'],
            'owner_user_id' => ['nullable', 'exists:users,id'],
        ]);

        $data['created_by'] = auth()->id();
        $data['updated_by'] = auth()->id();
        $data['owner_user_id'] = $data['owner_user_id'] ?? auth()->id();
        $data['status'] = $data['status'] ?? 'confirmed';

        $event = Event::create($data);

        EventChangeLogger::log(
            $event->id,
            "Event created: {$event->name}",
            'event',
            $event->id,
            'create',
        );

        return response()->json(['data' => $event->load('owner')], 201);
    }

    public function show(Event $event): JsonResponse
    {
        $this->authorizePermission('events.events.view');

        return response()->json([
            'data' => $event->load(['owner', 'functions', 'serviceOrders', 'changes.changedByUser']),
        ]);
    }

    public function update(Request $request, Event $event): JsonResponse
    {
        $this->authorizePermission('events.events.manage');

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'max:50'],
            'opportunity_id' => ['nullable', 'integer'],
            'account_id' => ['nullable', 'integer'],
            'starts_at' => ['sometimes', 'date'],
            'ends_at' => ['sometimes', 'date'],
            'venue_space' => ['nullable', 'string', 'max:255'],
            'expected_attendance' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string'],
            'owner_user_id' => ['nullable', 'exists:users,id'],
        ]);

        $tracked = ['name', 'status', 'starts_at', 'ends_at', 'venue_space', 'expected_attendance'];
        foreach ($tracked as $field) {
            if (array_key_exists($field, $data) && (string) $event->{$field} !== (string) $data[$field]) {
                EventChangeLogger::log(
                    $event->id,
                    "Updated {$field}",
                    'event',
                    $event->id,
                    'update',
                    $field,
                    $event->{$field},
                    $data[$field],
                );
            }
        }

        $data['updated_by'] = auth()->id();
        $event->update($data);

        return response()->json(['data' => $event->fresh('owner')]);
    }

    public function destroy(Event $event): JsonResponse
    {
        $this->authorizePermission('events.events.manage');

        EventChangeLogger::log(
            $event->id,
            "Event deleted: {$event->name}",
            'event',
            $event->id,
            'delete',
        );

        $event->delete();

        return response()->json(['message' => 'Event deleted']);
    }
}
