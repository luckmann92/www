<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\PhotoComposeInterface;
use App\Services\PhotoComposeService;
use App\Services\GenApiService;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Bind PhotoComposeInterface to the container
        $this->app->singleton(PhotoComposeInterface::class, function ($app) {
            // Check settings to determine which service to use
            $settingsService = new \App\Services\SettingsService();
            $useGenApi = $settingsService->get('use_genapi_service', false);

            if ($useGenApi) {
                return new GenApiService();
            } else {
                return new PhotoComposeService();
            }
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
