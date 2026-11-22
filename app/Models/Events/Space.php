<?php

namespace App\Models\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Space extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'area_sq_ft',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'area_sq_ft' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function capacities(): HasMany
    {
        return $this->hasMany(SpaceCapacity::class)->orderBy('sort_order')->orderBy('id');
    }

    public function attributeValues(): HasMany
    {
        return $this->hasMany(SpaceAttributeValue::class);
    }

    public function maxCapacity(): int
    {
        return (int) $this->capacities->max('seating_capacity');
    }
}
