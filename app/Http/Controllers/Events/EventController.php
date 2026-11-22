<?php

namespace App\Http\Controllers\Events;

use App\Http\Controllers\Concerns\AuthorizesPermissions;
use App\Http\Controllers\Controller;
use App\Models\Events\CalendarActivity;
use App\Models\Events\Event;
use App\Models\Events\EventFunction;
use App\Models\Events\Space;
use App\Models\Events\SpaceAttribute;
use App\Models\User;
use App\Services\EventChangeLogger;
use App\Services\Notifications\NotificationDispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EventController extends Controller
{
    use AuthorizesPermissions;

    public function index(Request $request): JsonResponse
    {
        $this->authorizePermission('events.events.view');

        $query = Event::with(['owner', 'account', 'eventType'])->latest('starts_at');

        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }

        if ($accountId = $request->integer('account_id')) {
            $query->where('account_id', $accountId);
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
            ->with('eventType:id,name,code,background_color,text_color')
            ->where(function ($q) use ($from, $to) {
                $q->whereBetween('starts_at', [$from, $to])
                    ->orWhereBetween('ends_at', [$from, $to])
                    ->orWhere(function ($inner) use ($from, $to) {
                        $inner->where('starts_at', '<=', $from)->where('ends_at', '>=', $to);
                    });
            })
            ->orderBy('starts_at')
            ->get(['id', 'name', 'status', 'event_type_id', 'starts_at', 'ends_at', 'venue_space']);

        $functions = EventFunction::with('event:id,name')
            ->whereBetween('starts_at', [$from, $to])
            ->orderBy('starts_at')
            ->get();

        $activities = CalendarActivity::query()
            ->where(function ($q) use ($from, $to) {
                $q->whereBetween('starts_at', [$from, $to])
                    ->orWhereBetween('ends_at', [$from, $to])
                    ->orWhere(function ($inner) use ($from, $to) {
                        $inner->where('starts_at', '<=', $from)->where('ends_at', '>=', $to);
                    });
            })
            ->orderBy('starts_at')
            ->get([
                'id',
                'title',
                'activity_type',
                'notes',
                'location',
                'starts_at',
                'ends_at',
                'all_day',
            ]);

        return response()->json([
            'data' => [
                'events' => $events,
                'functions' => $functions,
                'activities' => $activities,
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
        ]);
    }

    public function spaceOptions(): JsonResponse
    {
        $this->authorizePermission('events.calendar.view');

        $attributes = SpaceAttribute::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'description']);

        return response()->json(['data' => ['attributes' => $attributes]]);
    }

    public function availability(Request $request): JsonResponse
    {
        $this->authorizePermission('events.calendar.view');

        $data = $request->validate([
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'duration_hours' => ['required', 'numeric', 'min:0.5', 'max:720'],
            'capacity' => ['nullable', 'integer', 'min:1'],
            'attribute_ids' => ['nullable', 'array'],
            'attribute_ids.*' => ['integer', 'exists:space_attributes,id'],
        ]);

        $rangeStart = $request->date('starts_at');
        $rangeEnd = $request->date('ends_at');
        $durationHours = (float) $data['duration_hours'];
        $capacity = isset($data['capacity']) ? (int) $data['capacity'] : null;
        $attributeIds = array_values(array_unique(array_map('intval', $data['attribute_ids'] ?? [])));

        $rangeMinutes = $rangeStart->diffInMinutes($rangeEnd);
        $durationMinutes = (int) round($durationHours * 60);

        if ($durationMinutes < 30) {
            return response()->json([
                'message' => 'Duration must be at least 30 minutes.',
                'errors' => [
                    'duration_hours' => ['Duration must be at least 30 minutes.'],
                ],
            ], 422);
        }

        if ($durationMinutes > $rangeMinutes) {
            return response()->json([
                'message' => 'Duration cannot be longer than the selected date/time range.',
                'errors' => [
                    'duration_hours' => ['Duration cannot be longer than the selected date/time range.'],
                ],
            ], 422);
        }

        $stepMinutes = $durationMinutes <= 180 ? 30 : 60;
        $offsetLimit = $rangeMinutes - $durationMinutes;

        $spacesQuery = Space::query()
            ->where('is_active', true)
            ->with(['capacities.style', 'attributeValues']);

        if ($capacity !== null) {
            $spacesQuery->whereHas('capacities', function ($query) use ($capacity) {
                $query->where('seating_capacity', '>=', $capacity);
            });
        }

        foreach ($attributeIds as $attributeId) {
            $spacesQuery->whereHas('attributeValues', function ($query) use ($attributeId) {
                $query->where('space_attribute_id', $attributeId)->where('value', true);
            });
        }

        $spaces = $spacesQuery->orderBy('name')->get();

        $events = Event::query()
            ->where('starts_at', '<', $rangeEnd)
            ->where('ends_at', '>', $rangeStart)
            ->orderBy('starts_at')
            ->get(['id', 'name', 'status', 'starts_at', 'ends_at', 'venue_space', 'space_id']);

        $activities = CalendarActivity::query()
            ->where('starts_at', '<', $rangeEnd)
            ->where('ends_at', '>', $rangeStart)
            ->orderBy('starts_at')
            ->get([
                'id',
                'title',
                'activity_type',
                'starts_at',
                'ends_at',
                'location',
                'all_day',
            ]);

        $mapEvent = fn (Event $event) => [
            'id' => $event->id,
            'title' => $event->name,
            'status' => $event->status,
            'starts_at' => $event->starts_at,
            'ends_at' => $event->ends_at,
            'space' => $event->venue_space,
            'kind' => 'event',
        ];

        $mapActivity = fn (CalendarActivity $activity) => [
            'id' => $activity->id,
            'title' => $activity->title,
            'status' => $activity->activity_type,
            'starts_at' => $activity->starts_at,
            'ends_at' => $activity->ends_at,
            'space' => $activity->location,
            'all_day' => $activity->all_day,
            'kind' => 'activity',
        ];

        $spaceResults = [];

        foreach ($spaces as $space) {
            $matchingStyles = $space->capacities
                ->when($capacity !== null, fn ($capacities) => $capacities->where('seating_capacity', '>=', $capacity))
                ->values()
                ->map(fn ($capacityRow) => [
                    'id' => $capacityRow->id,
                    'space_style_id' => $capacityRow->space_style_id,
                    'code' => $capacityRow->style?->code,
                    'name' => $capacityRow->style?->name,
                    'seating_capacity' => $capacityRow->seating_capacity,
                ]);

            $spaceEvents = $events->filter(function (Event $event) use ($space) {
                if ($event->space_id && (int) $event->space_id === (int) $space->id) {
                    return true;
                }

                return filled($event->venue_space)
                    && strcasecmp(trim((string) $event->venue_space), trim($space->name)) === 0;
            });

            $spaceActivities = $activities->filter(function (CalendarActivity $activity) use ($space) {
                if (! filled($activity->location)) {
                    return true;
                }

                return str_contains(strtolower((string) $activity->location), strtolower($space->name));
            });

            $windows = [];

            for ($offset = 0; $offset <= $offsetLimit; $offset += $stepMinutes) {
                $windowStart = $rangeStart->copy()->addMinutes($offset);
                $windowEnd = $windowStart->copy()->addMinutes($durationMinutes);

                if ($windowEnd->gt($rangeEnd)) {
                    break;
                }

                $conflicts = $spaceEvents
                    ->filter(fn (Event $event) => $event->starts_at < $windowEnd && $event->ends_at > $windowStart)
                    ->map($mapEvent)
                    ->values();

                $tentative = $spaceActivities
                    ->filter(fn (CalendarActivity $activity) => $activity->starts_at < $windowEnd && $activity->ends_at > $windowStart)
                    ->map($mapActivity)
                    ->values();

                $windows[] = [
                    'from' => $windowStart->toIso8601String(),
                    'to' => $windowEnd->toIso8601String(),
                    'available' => $conflicts->isEmpty(),
                    'conflicts' => $conflicts,
                    'tentative' => $tentative,
                ];
            }

            $availableWindows = array_values(array_filter($windows, fn (array $window) => $window['available']));
            $conflictWindows = array_values(array_filter($windows, fn (array $window) => ! $window['available']));

            $spaceResults[] = [
                'id' => $space->id,
                'name' => $space->name,
                'description' => $space->description,
                'area_sq_ft' => $space->area_sq_ft,
                'max_capacity' => (int) $space->capacities->max('seating_capacity'),
                'matching_styles' => $matchingStyles,
                'available_count' => count($availableWindows),
                'window_count' => count($windows),
                'available_windows' => $availableWindows,
                'conflict_windows' => array_slice($conflictWindows, 0, 5),
            ];
        }

        usort($spaceResults, function (array $a, array $b) {
            if ($a['available_count'] === $b['available_count']) {
                return strcasecmp($a['name'], $b['name']);
            }

            return $b['available_count'] <=> $a['available_count'];
        });

        $availableSpaces = array_values(array_filter($spaceResults, fn (array $space) => $space['available_count'] > 0));

        return response()->json([
            'data' => [
                'starts_at' => $rangeStart->toIso8601String(),
                'ends_at' => $rangeEnd->toIso8601String(),
                'duration_hours' => $durationHours,
                'duration_minutes' => $durationMinutes,
                'step_minutes' => $stepMinutes,
                'capacity' => $capacity,
                'attribute_ids' => $attributeIds,
                'range_minutes' => $rangeMinutes,
                'space_count' => count($spaceResults),
                'available_space_count' => count($availableSpaces),
                'spaces' => $spaceResults,
                'available_spaces' => $availableSpaces,
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizePermission('events.events.manage');

        $data = $this->validated($request, creating: true);

        $data['created_by'] = auth()->id();
        $data['updated_by'] = auth()->id();
        $data['owner_user_id'] = $data['owner_user_id'] ?? auth()->id();
        $data['status'] = $data['status'] ?? 'confirmed';
        $data['value'] = $data['value'] ?? 0;

        $event = Event::create($data);

        EventChangeLogger::log(
            $event->id,
            "Event created: {$event->name}",
            'event',
            $event->id,
            'create',
        );

        $this->notifyOwner(
            $event,
            'Event created',
            "{$event->name} was created.",
            'event.created',
        );

        return response()->json(['data' => $event->load(['owner', 'account', 'eventType'])], 201);
    }

    public function show(Event $event): JsonResponse
    {
        $this->authorizePermission('events.events.view');

        return response()->json([
            'data' => $event->load([
                'owner',
                'account',
                'account.contacts',
                'contact',
                'eventType',
                'functions',
                'serviceOrders',
                'dignitaries',
                'notes' => fn ($q) => $q->latest()->with(['contact', 'author']),
                'changes.changedByUser',
                'proposals',
                'contracts',
            ]),
        ]);
    }

    public function update(Request $request, Event $event): JsonResponse
    {
        $this->authorizePermission('events.events.manage');

        $data = $this->validated($request, creating: false);

        $tracked = [
            'name', 'status', 'event_type_id', 'starts_at', 'ends_at', 'load_in_at', 'load_out_at',
            'venue_space', 'expected_attendance', 'value', 'client_venue_budget',
            'alcohol_served', 'description', 'purpose', 'referral',
            'planner_name', 'planner_title', 'planner_organization', 'planner_email', 'planner_phone',
        ];
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

        $previousStatus = $event->status;
        $data['updated_by'] = auth()->id();
        $event->update($data);

        if (array_key_exists('status', $data) && $data['status'] !== $previousStatus) {
            $this->notifyOwner(
                $event->fresh(),
                'Event status updated',
                "{$event->name} is now {$event->status}.",
                'event.status',
            );
        }

        return response()->json([
            'data' => $event->fresh([
                'owner',
                'account',
                'account.contacts',
                'contact',
                'eventType',
                'functions',
                'serviceOrders',
                'dignitaries',
                'notes' => fn ($q) => $q->latest()->with(['contact', 'author']),
                'changes.changedByUser',
                'proposals',
                'contracts',
            ]),
        ]);
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

    protected function validated(Request $request, bool $creating): array
    {
        $nameRule = $creating ? ['required', 'string', 'max:255'] : ['sometimes', 'string', 'max:255'];
        $startsRule = $creating ? ['required', 'date'] : ['sometimes', 'date'];
        $endsRule = $creating
            ? ['required', 'date', 'after_or_equal:starts_at']
            : ['sometimes', 'date'];

        return $request->validate([
            'name' => $nameRule,
            'status' => ['nullable', 'string', Rule::in(Event::STATUSES)],
            'event_type_id' => [
                $creating ? 'required' : 'sometimes',
                'integer',
                'exists:event_types,id',
            ],
            'account_id' => ['nullable', 'exists:accounts,id'],
            'contact_id' => ['nullable', 'exists:contacts,id'],
            'value' => ['nullable', 'numeric', 'min:0'],
            'client_venue_budget' => ['nullable', 'numeric', 'min:0'],
            'starts_at' => $startsRule,
            'ends_at' => $endsRule,
            'load_in_at' => ['nullable', 'date'],
            'load_out_at' => ['nullable', 'date'],
            'venue_space' => ['nullable', 'string', 'max:255'],
            'expected_attendance' => ['nullable', 'integer', 'min:0'],
            'alcohol_served' => ['nullable', 'boolean'],
            'description' => ['nullable', 'string'],
            'purpose' => ['nullable', 'string', 'max:255'],
            'planner_name' => ['nullable', 'string', 'max:255'],
            'planner_title' => ['nullable', 'string', 'max:255'],
            'planner_organization' => ['nullable', 'string', 'max:255'],
            'planner_email' => ['nullable', 'email', 'max:255'],
            'planner_phone' => ['nullable', 'string', 'max:50'],
            'referral' => ['nullable', 'string', 'max:255'],
            'owner_user_id' => ['nullable', 'exists:users,id'],
        ]);
    }

    private function notifyOwner(Event $event, string $title, string $body, string $type): void
    {
        if (! $event->owner_user_id) {
            return;
        }

        $owner = User::query()->find($event->owner_user_id);
        if (! $owner) {
            return;
        }

        app(NotificationDispatcher::class)->send(
            $owner,
            $title,
            $body,
            actionUrl: "/events/events/{$event->id}",
            type: $type,
            data: ['event_id' => $event->id],
        );
    }
}
