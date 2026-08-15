<?php

namespace App\Providers;

use App\Contracts\DatabaseBackupCreator;
use App\Services\MySqlDatabaseBackupCreator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(DatabaseBackupCreator::class, MySqlDatabaseBackupCreator::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
