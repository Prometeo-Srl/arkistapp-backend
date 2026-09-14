<?php

use App\Providers\AppServiceProvider;

// Telescope is a require-dev package: registering it here would fatal the app
// under `composer install --no-dev`. AppServiceProvider::register() registers it
// in local only.
return [
    AppServiceProvider::class,
];
