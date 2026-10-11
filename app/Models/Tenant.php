<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase, HasDomains;

    public static function getCustomColumns(): array
    {
        return [
            'id',
            'name',
            'slug',
            'status',
        ];
    }

    public function tenantModules(): HasMany
    {
        return $this->hasMany(TenantModule::class, 'tenant_id');
    }

    public function hasModule(string $code): bool
    {
        return $this->tenantModules()
            ->where('enabled', true)
            ->whereHas('module', fn ($q) => $q->where('code', $code)->where('is_active', true))
            ->exists();
    }
}
