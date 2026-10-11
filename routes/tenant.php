<?php

use Illuminate\Support\Facades\Route;

// Tenant web routes unused — API routes handle tenant context via X-Tenant / subdomain.
Route::middleware([
    //
])->group(function () {
    //
});
