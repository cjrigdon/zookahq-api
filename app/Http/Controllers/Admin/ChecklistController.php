<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Operations\Checklist;
use App\Models\Operations\ChecklistStep;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ChecklistController extends Controller
{
    public function index(): JsonResponse
    {
        $this->authorizeAdmin();

        $checklists = Checklist::query()
            ->withCount('steps')
            ->withCount('instances')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $checklists]);
    }

    public function show(Checklist $checklist): JsonResponse
    {
        $this->authorizeAdmin();

        return response()->json([
            'data' => $checklist->load(['steps', 'creator']),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeAdmin();

        $data = $this->validated($request);
        $steps = $data['steps'] ?? [];
        unset($data['steps']);

        $checklist = DB::transaction(function () use ($data, $steps) {
            $checklist = Checklist::create([
                ...$data,
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);
            $this->syncSteps($checklist, $steps);

            return $checklist->fresh(['steps', 'creator']);
        });

        return response()->json(['data' => $checklist], 201);
    }

    public function update(Request $request, Checklist $checklist): JsonResponse
    {
        $this->authorizeAdmin();

        $data = $this->validated($request, updating: true);
        $steps = array_key_exists('steps', $data) ? $data['steps'] : null;
        unset($data['steps']);

        $checklist = DB::transaction(function () use ($checklist, $data, $steps) {
            if ($data !== []) {
                $checklist->fill($data);
                $checklist->updated_by = auth()->id();
                $checklist->save();
            }

            if (is_array($steps)) {
                $this->syncSteps($checklist, $steps);
            }

            return $checklist->fresh(['steps', 'creator']);
        });

        return response()->json(['data' => $checklist]);
    }

    public function destroy(Checklist $checklist): JsonResponse
    {
        $this->authorizeAdmin();

        if ($checklist->instances()->exists()) {
            abort(422, 'Cannot delete a checklist that has been started on records. Deactivate it instead.');
        }

        $checklist->delete();

        return response()->json(['message' => 'Checklist deleted']);
    }

    protected function validated(Request $request, bool $updating = false): array
    {
        return $request->validate([
            'name' => [$updating ? 'sometimes' : 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'applies_to' => [$updating ? 'sometimes' : 'required', 'string', Rule::in(Checklist::APPLIES_TO)],
            'is_active' => ['sometimes', 'boolean'],
            'is_required' => ['sometimes', 'boolean'],
            'steps' => [$updating ? 'sometimes' : 'required', 'array', 'min:1'],
            'steps.*.id' => ['nullable', 'integer'],
            'steps.*.title' => ['required', 'string', 'max:255'],
            'steps.*.description' => ['nullable', 'string'],
            'steps.*.is_required' => ['sometimes', 'boolean'],
            'steps.*.sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $steps
     */
    protected function syncSteps(Checklist $checklist, array $steps): void
    {
        $keepIds = [];

        foreach (array_values($steps) as $index => $stepData) {
            $payload = [
                'title' => $stepData['title'],
                'description' => $stepData['description'] ?? null,
                'sort_order' => $stepData['sort_order'] ?? $index,
                'is_required' => array_key_exists('is_required', $stepData)
                    ? (bool) $stepData['is_required']
                    : true,
            ];

            $existingId = isset($stepData['id']) ? (int) $stepData['id'] : null;
            if ($existingId) {
                $step = ChecklistStep::query()
                    ->where('checklist_id', $checklist->id)
                    ->where('id', $existingId)
                    ->first();
                if ($step) {
                    $step->update($payload);
                    $keepIds[] = $step->id;
                    continue;
                }
            }

            $created = $checklist->steps()->create($payload);
            $keepIds[] = $created->id;
        }

        $checklist->steps()->whereNotIn('id', $keepIds)->delete();
    }

    protected function authorizeAdmin(): void
    {
        if (! auth()->user()?->can('admin.checklists.manage')) {
            abort(403, 'Unauthorized.');
        }
    }
}
