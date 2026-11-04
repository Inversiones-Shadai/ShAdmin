<?php

use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\HttpSecurityServiceProvider;

return [
    HttpSecurityServiceProvider::class,
    AppServiceProvider::class,
    FortifyServiceProvider::class,
];
