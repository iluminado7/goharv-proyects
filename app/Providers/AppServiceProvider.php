<?php

namespace App\Providers;

use App\Http\Controllers\ThemeController;
use Illuminate\Support\Facades\View;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\ServiceProvider;

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
        Password::defaults(fn () => $this->app->isProduction()
            ? Password::min(8)->uncompromised()
            : Password::min(8));

        // El fondo elegido lo necesitan las dos plantillas, la del panel y la del login.
        View::composer('*', function ($view) {
            $view->with('tema', ThemeController::current(request()));
        });
    }
}
