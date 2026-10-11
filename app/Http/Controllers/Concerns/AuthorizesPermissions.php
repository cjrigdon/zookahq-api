<?php

namespace App\Http\Controllers\Concerns;

trait AuthorizesPermissions
{
    protected function authorizePermission(string $permission): void
    {
        if (! auth()->user()?->can($permission)) {
            abort(403, 'Unauthorized.');
        }
    }
}
