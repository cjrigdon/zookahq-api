<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TenantSetting extends Model
{
    protected $fillable = [
        'timezone',
        'locale',
        'currency',
        'company_name',
        'branding',
        'defaults',
    ];

    protected function casts(): array
    {
        return [
            'branding' => 'array',
            'defaults' => 'array',
        ];
    }
}
