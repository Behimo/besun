<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if (! class_exists('Helper', false)) {
            class_alias(\App\Helpers\Helpers::class, 'Helper');
        }
    }
}
