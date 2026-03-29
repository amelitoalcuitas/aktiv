<?php

namespace App\Providers;

use App\Services\Location\ApproximateLocationService;
use App\Services\Location\Contracts\ApproximateLocationProvider;
use App\Services\Location\Providers\IpWhoIsApproximateLocationProvider;
use App\Models\Court;
use App\Models\Hub;
use App\Policies\CourtPolicy;
use App\Policies\HubPolicy;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ApproximateLocationProvider::class, function () {
            return match (config('services.ip_geolocation.provider')) {
                'ipwhois' => new IpWhoIsApproximateLocationProvider(),
                default => throw new InvalidArgumentException('Unsupported IP geolocation provider configured.'),
            };
        });

        $this->app->singleton(ApproximateLocationService::class);
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
