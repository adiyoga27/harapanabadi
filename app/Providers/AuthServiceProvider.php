<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;
use Laravel\Passport\Passport;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        // 'App\Model' => 'App\Policies\ModelPolicy',
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        Passport::ignoreRoutes();

        Gate::before(function ($user, $ability) {
            if (in_array($ability, ['backup', 'superadmin', 
                'manage_modules'])) {
                $administrator_list = config('constants.administrator_usernames');
            
                if (in_array($user->username, explode(',', $administrator_list))) {
                    return true;
                }
            } else {
                if ($user->hasRole('Admin#' . $user->business_id)) {
                    return true;
                }
            }
        });
    }
}
