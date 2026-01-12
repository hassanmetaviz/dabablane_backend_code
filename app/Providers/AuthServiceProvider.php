<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;
use App\Models\Blane;
use App\Models\Order;
use App\Models\Reservation;
use App\Models\Rating;
use App\Policies\BlanePolicy;
use App\Policies\OrderPolicy;
use App\Policies\ReservationPolicy;
use App\Policies\RatingPolicy;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Blane::class => BlanePolicy::class,
        Order::class => OrderPolicy::class,
        Reservation::class => ReservationPolicy::class,
        Rating::class => RatingPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        // Define a global "super-admin" gate for super-admin bypass
        Gate::before(function ($user, $ability) {
            if ($user->hasRole('super-admin')) {
                return true;
            }
        });
    }
}




