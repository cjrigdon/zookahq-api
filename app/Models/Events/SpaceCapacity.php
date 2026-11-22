<?php

namespace App\Models\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SpaceCapacity extends Model
{
    protected $fillable = [
        'space_id',
        'space_style_id',
        'seating_capacity',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'seating_capacity' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function space(): BelongsTo
    {
        return $this->belongsTo(Space::class);
    }

    public function style(): BelongsTo
    {
        return $this->belongsTo(SpaceStyle::class, 'space_style_id');
    }
}
