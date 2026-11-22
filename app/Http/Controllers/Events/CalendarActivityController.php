<?php

namespace App\Http\Controllers\Events;

use App\Http\Controllers\Concerns\AuthorizesPermissions;
use App\Http\Controllers\Controller;
use App\Models\Events\CalendarActivity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CalendarActivityController extends Controller
{
    use AuthorizesPermissions;

    public function index(Request $request): JsonResponse
    {
        $this->authorizePermission('events.calendar.view');

        $query = CalendarActivity::query()->latest('starts_at');

        if ($from = $request->date('from')) {
            $query->where('ends_at', '>=', $from);
        }

        if ($to = $request->date('to')) {
            $query->where('starts_at', '<=', $to);
        }

        return response()->json(['data' => $query->paginate(50)]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizePermission('events.calendar.manage');

        $data = $this->validated($request);
        $data['created_by'] = auth()->id();
        $data['updated_by'] = auth()->id();
        $data['activity_type'] = $data['activity_type'] ?? 'activity';
        $data['all_day'] = $data['all_day'] ?? false;

        $activity = CalendarActivity::create($data);

        return response()->json(['data' => $activity], 201);
    }

    public function show(CalendarActivity $calendarActivity): JsonResponse
    {
        $this->authorizePermission('events.calendar.view');

        return response()->json(['data' => $calendarActivity]);
    }

    public function update(Request $request, CalendarActivity $calendarActivity): JsonResponse
    {
        $this->authorizePermission('events.calendar.manage');

        $data = $this->validated($request, creating: false);
        $data['updated_by'] = auth()->id();
        $calendarActivity->update($data);

        return response()->json(['data' => $calendarActivity->fresh()]);
    }

    public function destroy(CalendarActivity $calendarActivity): JsonResponse
    {
        $this->authorizePermission('events.calendar.manage');

        $calendarActivity->delete();

        return response()->json(['message' => 'Activity deleted']);
    }

    private function validated(Request $request, bool $creating = true): array
    {
        return $request->validate([
            'title' => [$creating ? 'required' : 'sometimes', 'string', 'max:255'],
            'activity_type' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
            'location' => ['nullable', 'string', 'max:255'],
            'starts_at' => [$creating ? 'required' : 'sometimes', 'date'],
            'ends_at' => [$creating ? 'required' : 'sometimes', 'date', 'after_or_equal:starts_at'],
            'all_day' => ['nullable', 'boolean'],
        ]);
    }
}
