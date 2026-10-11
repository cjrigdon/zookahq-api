<?php

namespace App\Http\Controllers\Events;

use App\Http\Controllers\Concerns\AuthorizesPermissions;
use App\Http\Controllers\Controller;
use App\Models\Events\EventFunction;
use App\Services\EventChangeLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventFunctionController extends Controller
{
    use AuthorizesPermissions;

    public function index(Request $request): JsonResponse
    {
        $this->authorizePermission('events.functions.view');

        $query = EventFunction::with('event')->latest('starts_at');

        if ($eventId = $request->integer('event_id')) {
            $query->where('event_id', $eventId);
        }

        return response()->json(['data' => $query->paginate(25)]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizePermission('events.functions.manage');

        $data = $request->validate([
            'event_id' => ['required', 'exists:events,id'],
            'name' => ['required', 'string', 'max:255'],
            'function_type' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'max:50'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after_or_equal:starts_at'],
            'room' => ['nullable', 'string', 'max:255'],
            'guaranteed_count' => ['nullable', 'integer', 'min:0'],
            'set_count' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        $data['created_by'] = auth()->id();
        $data['updated_by'] = auth()->id();
        $data['function_type'] = $data['function_type'] ?? 'general';
        $data['status'] = $data['status'] ?? 'scheduled';

        $function = EventFunction::create($data);

        EventChangeLogger::log(
            $function->event_id,
            "Function added: {$function->name}",
            'event_function',
            $function->id,
            'create',
        );

        return response()->json(['data' => $function->load('event')], 201);
    }

    public function show(EventFunction $function): JsonResponse
    {
        $this->authorizePermission('events.functions.view');

        return response()->json(['data' => $function->load(['event', 'serviceOrders'])]);
    }

    public function update(Request $request, EventFunction $function): JsonResponse
    {
        $this->authorizePermission('events.functions.manage');

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'function_type' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'max:50'],
            'starts_at' => ['sometimes', 'date'],
            'ends_at' => ['sometimes', 'date'],
            'room' => ['nullable', 'string', 'max:255'],
            'guaranteed_count' => ['nullable', 'integer', 'min:0'],
            'set_count' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        foreach (['name', 'status', 'starts_at', 'ends_at', 'room', 'guaranteed_count', 'set_count'] as $field) {
            if (array_key_exists($field, $data) && (string) $function->{$field} !== (string) $data[$field]) {
                EventChangeLogger::log(
                    $function->event_id,
                    "Function {$function->name}: updated {$field}",
                    'event_function',
                    $function->id,
                    'update',
                    $field,
                    $function->{$field},
                    $data[$field],
                );
            }
        }

        $data['updated_by'] = auth()->id();
        $function->update($data);

        return response()->json(['data' => $function->fresh('event')]);
    }

    public function destroy(EventFunction $function): JsonResponse
    {
        $this->authorizePermission('events.functions.manage');

        EventChangeLogger::log(
            $function->event_id,
            "Function removed: {$function->name}",
            'event_function',
            $function->id,
            'delete',
        );

        $function->delete();

        return response()->json(['message' => 'Function deleted']);
    }
}
