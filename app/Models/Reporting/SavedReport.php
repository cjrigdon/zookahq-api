<?php

namespace App\Models\Reporting;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SavedReport extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name', 'description', 'dataset', 'columns', 'filters',
        'sort_column', 'sort_direction', 'chart_type', 'group_by',
        'ai_prompt', 'is_favorite', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'columns' => 'array',
            'filters' => 'array',
            'is_favorite' => 'boolean',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
