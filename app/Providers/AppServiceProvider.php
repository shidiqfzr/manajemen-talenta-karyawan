<?php

namespace App\Providers;

use App\Models\Employee;
use App\Observers\EmployeeObserver;
use App\Services\Formasi\FormasiCatalog;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(FormasiCatalog::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Employee::observe(EmployeeObserver::class);
    }
}
