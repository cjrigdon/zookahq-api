<?php

namespace App\Models\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CalendarActivity extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'title',
        'activity_type',
        'notes',
        'location',
        'starts_at',
        'ends_at',
        'all_day',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'all_day' => 'boolean',
        ];
    }
}
