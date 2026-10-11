<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Module extends Model
{
    protected $fillable = [
        'code',
        'name',
        'description',
        'billable',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'billable' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function tenantModules(): HasMany
    {
        return $this->hasMany(TenantModule::class);
    }
}
