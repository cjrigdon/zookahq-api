<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Events\EventType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class EventTypeController extends Controller
{
    public function index(): JsonResponse
    {
        $this->authorizeAdmin();

        $types = EventType::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $types]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeAdmin();

        $data = $this->validated($request);
        $maxSort = (int) EventType::query()->max('sort_order');
        $data['sort_order'] = $data['sort_order'] ?? ($maxSort + 1);
        $data['is_active'] = $data['is_active'] ?? true;
        $data['code'] = $this->normalizeCode($data['code'] ?? null, $data['name']);

        $type = EventType::create($data);

        return response()->json(['data' => $type], 201);
    }

    public function update(Request $request, EventType $eventType): JsonResponse
    {
        $this->authorizeAdmin();

        $data = $this->validated($request, updating: true, typeId: $eventType->id);
        if (array_key_exists('code', $data)) {
            $data['code'] = $this->normalizeCode(
                $data['code'],
                $data['name'] ?? $eventType->name,
            );
        }

        $eventType->update($data);

        return response()->json(['data' => $eventType->fresh()]);
    }

    public function destroy(EventType $eventType): JsonResponse
    {
        $this->authorizeAdmin();

        $eventType->delete();

        return response()->json(['message' => 'Event type deleted']);
    }

    private function validated(Request $request, bool $updating = false, ?int $typeId = null): array
    {
        $hex = ['regex:/^#[0-9A-Fa-f]{6}$/'];

        return $request->validate([
            'code' => [
                $updating ? 'sometimes' : 'nullable',
                'string',
                'max:64',
                Rule::unique('event_types', 'code')->ignore($typeId),
            ],
            'name' => [$updating ? 'sometimes' : 'required', 'string', 'max:255'],
            'background_color' => [$updating ? 'sometimes' : 'required', 'string', ...$hex],
            'text_color' => [$updating ? 'sometimes' : 'required', 'string', ...$hex],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }

    private function normalizeCode(?string $code, string $name): string
    {
        $value = $code !== null && trim($code) !== ''
            ? Str::upper(Str::slug(trim($code), '_'))
            : Str::upper(Str::slug($name, '_'));

        return $value !== '' ? substr($value, 0, 64) : 'TYPE';
    }

    private function authorizeAdmin(): void
    {
        if (! auth()->user()?->can('admin.event_types.manage')) {
            abort(403, 'Unauthorized.');
        }
    }
}
