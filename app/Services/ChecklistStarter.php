<?php

namespace App\Services;

use App\Models\Operations\Checklist;
use App\Models\Operations\ChecklistInstance;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ChecklistStarter
{
    /**
     * Attach all active required checklists for a newly created record.
     */
    public static function attachRequired(string $type, Model $model): void
    {
        Checklistables::assertAppliesTo($type);

        $checklists = Checklist::query()
            ->where('applies_to', $type)
            ->where('is_active', true)
            ->where('is_required', true)
            ->with('steps')
            ->orderBy('name')
            ->get();

        foreach ($checklists as $checklist) {
            self::start($checklist, $type, (int) $model->getKey());
        }
    }

    /**
     * Create a checklist instance for a record when missing.
     */
    public static function start(Checklist $checklist, string $type, int $recordId): ?ChecklistInstance
    {
        Checklistables::assertAppliesTo($type);

        if (! $checklist->is_active || $checklist->applies_to !== $type) {
            return null;
        }

        $checklist->loadMissing('steps');

        if ($checklist->steps->isEmpty()) {
            return null;
        }

        $exists = ChecklistInstance::query()
            ->where('checklist_id', $checklist->id)
            ->where('checklistable_type', $type)
            ->where('checklistable_id', $recordId)
            ->exists();

        if ($exists) {
            return null;
        }

        return DB::transaction(function () use ($checklist, $type, $recordId) {
            $instance = ChecklistInstance::create([
                'checklist_id' => $checklist->id,
                'checklistable_type' => $type,
                'checklistable_id' => $recordId,
                'status' => ChecklistInstance::STATUS_OPEN,
                'created_by' => auth()->id(),
            ]);

            foreach ($checklist->steps as $index => $step) {
                $instance->steps()->create([
                    'checklist_step_id' => $step->id,
                    'title' => $step->title,
                    'description' => $step->description,
                    'sort_order' => $step->sort_order ?? $index,
                    'is_required' => $step->is_required,
                ]);
            }

            return $instance;
        });
    }
}
