<?php

namespace App\Providers;

use App\Models\Employee;
use App\Models\User;
use App\Observers\EmployeeObserver;
use App\Services\Formasi\FormasiCatalog;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
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

        $this->configureLoginRateLimit();

        Gate::define('export-data', fn (User $user) => $user->role === 'admin');

        // Permanent deletion of employee master records (directory bulk action); admin only, like the admin route group.
        Gate::define('delete-employee', fn (User $user) => $user->role === 'admin');
    }

    /**
     * Brute-force protection: at most 5 failed login attempts per minute per IP (SECURITY.md §3).
     * Only failures count, so staff signing in from one shared office IP do not lock each other out.
     */
    private function configureLoginRateLimit(): void
    {
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)
                ->by($request->ip())
                ->after(fn () => Auth::guest())
                ->response(function (Request $request, array $headers) {
                    return back()->withErrors([
                        'username' => "Terlalu banyak percobaan masuk. Silakan coba lagi dalam {$headers['Retry-After']} detik.",
                    ])->onlyInput('username')->withHeaders($headers);
                });
        });
    }
}
