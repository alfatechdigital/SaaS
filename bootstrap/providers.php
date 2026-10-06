<?php

use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\ProductionConfigServiceProvider;

return [
    AppServiceProvider::class,
    FortifyServiceProvider::class,
    ProductionConfigServiceProvider::class,
];
