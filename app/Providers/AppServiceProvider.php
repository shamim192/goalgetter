<?php

namespace App\Providers;

use App\Models\SystemSetting;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::composer('*', function ($view) {
            $systemSetting = SystemSetting::first();
            $view->with('systemSetting', $systemSetting);
        });
    }
}
