<?php

namespace App\Models\Operations;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChecklistStepEvent extends Model
{
    public const ACTION_COMPLETED = 'completed';

    public const ACTION_UNCOMPLETED = 'uncompleted';

    protected $fillable = [
        'checklist_instance_step_id',
        'action',
        'user_id',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
        ];
    }

    public function step(): BelongsTo
    {
        return $this->belongsTo(ChecklistInstanceStep::class, 'checklist_instance_step_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
