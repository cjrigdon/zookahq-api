<?php

namespace App\Models\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SpaceAttributeValue extends Model
{
    protected $fillable = [
        'space_id',
        'space_attribute_id',
        'value',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'boolean',
        ];
    }

    public function space(): BelongsTo
    {
        return $this->belongsTo(Space::class);
    }

    public function attribute(): BelongsTo
    {
        return $this->belongsTo(SpaceAttribute::class, 'space_attribute_id');
    }
}
