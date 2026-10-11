<?php

use App\Models\PlatformUser;
use App\Models\User;
use App\Providers\AppServiceProvider;
use App\Providers\TenancyServiceProvider;

return [
    AppServiceProvider::class,
    TenancyServiceProvider::class,
];
