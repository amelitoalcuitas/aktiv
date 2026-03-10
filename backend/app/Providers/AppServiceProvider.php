<?php

namespace App\Providers;

use App\Models\Court;
use App\Models\Hub;
use App\Policies\CourtPolicy;
use App\Policies\HubPolicy;
use Illuminate\Support\Facades\Gate;
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
        Gate::policy(Hub::class, HubPolicy::class);
        Gate::policy(Court::class, CourtPolicy::class);
    }
}
