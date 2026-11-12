<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Carriers & locations: admin manages, staff may read. Customers have no access.
        Gate::define('manage-reference-data', fn (User $user) => $user->isAdmin());
        Gate::define('view-reference-data', fn (User $user) => $user->isStaff());
    }
}
