<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Models\Operations\Checklist;
use App\Models\Operations\ChecklistInstance;
use App\Models\Operations\ChecklistInstanceStep;
use App\Models\Operations\ChecklistStepEvent;
use App\Services\Checklistables;
use App\Services\ChecklistStarter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ChecklistInstanceController extends Controller
{
    public function available(Request $request): JsonResponse
    {
        $data = $request->validate([
            'applies_to' => ['required', 'string', Rule::in(Checklist::APPLIES_TO)],
        ]);

        $this->authorizeView($data['applies_to']);

        $templates = Checklist::query()
            ->where('applies_to', $data['applies_to'])
            ->where('is_active', true)
            ->withCount('steps')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $templates]);
    }

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', 'string', Rule::in(Checklist::APPLIES_TO)],
            'id' => ['required', 'integer'],
        ]);

        $this->authorizeView($data['type']);
        Checklistables::findOrFail($data['type'], (int) $data['id']);

        $instances = ChecklistInstance::query()
            ->where('checklistable_type', $data['type'])
            ->where('checklistable_id', $data['id'])
            ->with([
                'checklist:id,name,applies_to',
                'steps.completedByUser:id,name',
                'steps.events.user:id,name',
                'submitter:id,name',
                'creator:id,name',
            ])
            ->orderByDesc('id')
            ->get();

        return response()->json(['data' => $instances]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'checklist_id' => ['required', 'integer', 'exists:checklists,id'],
            'type' => ['required', 'string', Rule::in(Checklist::APPLIES_TO)],
            'id' => ['required', 'integer'],
        ]);

        $this->authorizeManage($data['type']);
        Checklistables::findOrFail($data['type'], (int) $data['id']);

        $checklist = Checklist::query()
            ->with('steps')
            ->findOrFail($data['checklist_id']);

        if (! $checklist->is_active) {
            throw ValidationException::withMessages([
                'checklist_id' => 'This checklist is inactive.',
            ]);
        }

        if ($checklist->applies_to !== $data['type']) {
            throw ValidationException::withMessages([
                'checklist_id' => 'Checklist does not apply to this record type.',
            ]);
        }

        if ($checklist->steps->isEmpty()) {
            throw ValidationException::withMessages([
                'checklist_id' => 'Checklist has no steps.',
            ]);
        }

        $existing = ChecklistInstance::query()
            ->where('checklist_id', $checklist->id)
            ->where('checklistable_type', $data['type'])
            ->where('checklistable_id', $data['id'])
            ->with([
                'checklist:id,name,applies_to',
                'steps.completedByUser:id,name',
                'steps.events.user:id,name',
                'submitter:id,name',
                'creator:id,name',
            ])
            ->first();

        if ($existing) {
            return response()->json(['data' => $existing], 200);
        }

        $instance = ChecklistStarter::start($checklist, $data['type'], (int) $data['id']);

        if (! $instance) {
            throw ValidationException::withMessages([
                'checklist_id' => 'Unable to start checklist.',
            ]);
        }

        $instance->load([
            'checklist:id,name,applies_to',
            'steps.completedByUser:id,name',
            'steps.events.user:id,name',
            'submitter:id,name',
            'creator:id,name',
        ]);

        return response()->json(['data' => $instance], 201);
    }

    public function show(ChecklistInstance $instance): JsonResponse
    {
        $this->authorizeView($instance->checklistable_type);

        return response()->json([
            'data' => $instance->load([
                'checklist:id,name,applies_to',
                'steps.completedByUser:id,name',
                'steps.events.user:id,name',
                'submitter:id,name',
                'creator:id,name',
            ]),
        ]);
    }

    public function completeStep(ChecklistInstance $instance, ChecklistInstanceStep $step): JsonResponse
    {
        $this->ensureStepBelongs($instance, $step);
        $this->authorizeManage($instance->checklistable_type);
        $this->ensureOpen($instance);

        if ($step->is_completed) {
            return response()->json(['data' => $this->loaded($instance)]);
        }

        DB::transaction(function () use ($step) {
            $now = now();
            $step->update([
                'is_completed' => true,
                'completed_at' => $now,
                'completed_by' => auth()->id(),
            ]);

            ChecklistStepEvent::create([
                'checklist_instance_step_id' => $step->id,
                'action' => ChecklistStepEvent::ACTION_COMPLETED,
                'user_id' => auth()->id(),
                'occurred_at' => $now,
            ]);
        });

        return response()->json(['data' => $this->loaded($instance)]);
    }

    public function uncompleteStep(ChecklistInstance $instance, ChecklistInstanceStep $step): JsonResponse
    {
        $this->ensureStepBelongs($instance, $step);
        $this->authorizeManage($instance->checklistable_type);
        $this->ensureOpen($instance);

        if (! $step->is_completed) {
            return response()->json(['data' => $this->loaded($instance)]);
        }

        DB::transaction(function () use ($step) {
            $now = now();
            $step->update([
                'is_completed' => false,
                'completed_at' => null,
                'completed_by' => null,
            ]);

            ChecklistStepEvent::create([
                'checklist_instance_step_id' => $step->id,
                'action' => ChecklistStepEvent::ACTION_UNCOMPLETED,
                'user_id' => auth()->id(),
                'occurred_at' => $now,
            ]);
        });

        return response()->json(['data' => $this->loaded($instance)]);
    }

    public function submit(ChecklistInstance $instance): JsonResponse
    {
        $this->authorizeManage($instance->checklistable_type);
        $this->ensureOpen($instance);

        $instance->load('steps');
        $incomplete = $instance->steps
            ->where('is_required', true)
            ->where('is_completed', false)
            ->values();

        if ($incomplete->isNotEmpty()) {
            throw ValidationException::withMessages([
                'checklist' => 'Complete all required steps before submitting.',
            ]);
        }

        $instance->update([
            'status' => ChecklistInstance::STATUS_SUBMITTED,
            'submitted_at' => now(),
            'submitted_by' => auth()->id(),
        ]);

        return response()->json(['data' => $this->loaded($instance)]);
    }

    public function destroy(ChecklistInstance $instance): JsonResponse
    {
        $this->authorizeManage($instance->checklistable_type);

        if ($instance->isSubmitted()) {
            abort(422, 'Submitted checklists cannot be removed.');
        }

        $instance->delete();

        return response()->json(['message' => 'Checklist removed']);
    }

    protected function loaded(ChecklistInstance $instance): ChecklistInstance
    {
        return $instance->fresh([
            'checklist:id,name,applies_to',
            'steps.completedByUser:id,name',
            'steps.events.user:id,name',
            'submitter:id,name',
            'creator:id,name',
        ]);
    }

    protected function ensureStepBelongs(ChecklistInstance $instance, ChecklistInstanceStep $step): void
    {
        if ((int) $step->checklist_instance_id !== (int) $instance->id) {
            abort(404);
        }
    }

    protected function ensureOpen(ChecklistInstance $instance): void
    {
        if ($instance->isSubmitted()) {
            abort(422, 'This checklist has already been submitted.');
        }
    }

    protected function authorizeView(string $type): void
    {
        if (! auth()->user()?->can(Checklistables::viewPermission($type))) {
            abort(403, 'Unauthorized.');
        }
    }

    protected function authorizeManage(string $type): void
    {
        if (! auth()->user()?->can(Checklistables::managePermission($type))) {
            abort(403, 'Unauthorized.');
        }
    }
}
