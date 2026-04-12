<?php

namespace App\Providers;

use App\Models\Agent;
use App\Policies\AgentPolicy;
use Illuminate\Support\Facades\Gate;
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
    ];

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
        // Register policies
        $this->registerPolicies();

        $this->registerBookingMailer();
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
