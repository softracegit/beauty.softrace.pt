<?php

namespace App\Providers;

use App\Models\Agent;
use App\Models\Organization;
use App\Models\Service;
use App\Models\Store;
use App\Policies\AgentPolicy;
use App\Policies\OrganizationPolicy;
use App\Policies\StorePolicy;
use App\Support\CurrentStore;
use App\View\Composers\BookingStoreComposer;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Agent::class => AgentPolicy::class,
        Organization::class => OrganizationPolicy::class,
        Store::class => StorePolicy::class,
    ];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(CurrentStore::class, fn () => new CurrentStore);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrapFive();

        // Register policies
        $this->registerPolicies();

        $this->registerBookingMailer();

        View::composer(['booking.*', 'booking.partials.*'], BookingStoreComposer::class);

        Route::bind('service', function (string $value, \Illuminate\Routing\Route $route): Service {
            $name = $route->getName() ?? '';
            if (! is_string($name) || ! str_starts_with($name, 'booking.')) {
                return Service::query()->whereKey($value)->firstOrFail();
            }

            $store = $route->parameter('store');
            if (! $store instanceof Store) {
                $raw = $store;
                $slug = is_string($raw) || is_numeric($raw) ? trim((string) $raw) : '';
                $store = $slug !== '' ? Store::query()->where('slug', $slug)->first() : null;
            }
            if (! $store instanceof Store) {
                abort(404);
            }

            $service = Service::query()
                ->where('store_id', $store->id)
                ->whereKey($value)
                ->first();

            if (! $service) {
                abort(404);
            }

            return $service;
        });

        Route::bind('agent', function (string $value, \Illuminate\Routing\Route $route): Agent {
            $name = $route->getName() ?? '';
            if (! is_string($name) || $name !== 'booking.agent') {
                return Agent::query()->whereKey($value)->firstOrFail();
            }

            $store = $route->parameter('store');
            if (! $store instanceof Store) {
                $raw = $store;
                $slug = is_string($raw) || is_numeric($raw) ? trim((string) $raw) : '';
                $store = $slug !== '' ? Store::query()->where('slug', $slug)->first() : null;
            }
            if (! $store instanceof Store) {
                abort(404);
            }

            $bookingSlug = Agent::normalizeBookingSlug($value);
            if ($bookingSlug === null) {
                abort(404);
            }

            $agent = Agent::query()
                ->where('store_id', $store->id)
                ->where('booking_slug', $bookingSlug)
                ->first();

            if (! $agent) {
                abort(404);
            }

            return $agent;
        });
    }

    /**
     * Mailer dedicado ao fluxo público /booking: mesmo transporte que o default (log, smtp, …)
     * mas sem o redirecionamento global config('mail.to') usado fora de production.
     */
    protected function registerBookingMailer(): void
    {
        $defaultName = config('mail.default');
        if (! is_string($defaultName) || $defaultName === '') {
            return;
        }

        $base = config("mail.mailers.{$defaultName}");
        if (! is_array($base)) {
            return;
        }

        config([
            'mail.mailers.booking' => array_merge($base, [
                'to' => null,
            ]),
        ]);
    }

    /**
     * Register the application's policies.
     */
    protected function registerPolicies(): void
    {
        foreach ($this->policies as $model => $policy) {
            Gate::policy($model, $policy);
        }
    }
}
