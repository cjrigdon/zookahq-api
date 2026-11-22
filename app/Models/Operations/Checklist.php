<?php

namespace App\Models\Operations;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Checklist extends Model
{
    use SoftDeletes;

    public const APPLIES_TO = [
        'lead',
        'account',
        'event',
        'function',
        'service_order',
    ];

    protected $fillable = [
        'name',
        'description',
        'applies_to',
        'is_active',
        'is_required',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_required' => 'boolean',
        ];
    }

    public function steps(): HasMany
    {
        return $this->hasMany(ChecklistStep::class)->orderBy('sort_order')->orderBy('id');
    }

    public function instances(): HasMany
    {
        return $this->hasMany(ChecklistInstance::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
