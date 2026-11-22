<?php

namespace App\Models\Operations;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChecklistInstanceStep extends Model
{
    protected $fillable = [
        'checklist_instance_id',
        'checklist_step_id',
        'title',
        'description',
        'sort_order',
        'is_required',
        'is_completed',
        'completed_at',
        'completed_by',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_required' => 'boolean',
            'is_completed' => 'boolean',
            'completed_at' => 'datetime',
        ];
    }

    public function instance(): BelongsTo
    {
        return $this->belongsTo(ChecklistInstance::class, 'checklist_instance_id');
    }

    public function templateStep(): BelongsTo
    {
        return $this->belongsTo(ChecklistStep::class, 'checklist_step_id');
    }

    public function completedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function events(): HasMany
    {
        return $this->hasMany(ChecklistStepEvent::class)->orderByDesc('occurred_at')->orderByDesc('id');
    }
}
