<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
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
        // Hosted under a sub-path (IIS sub-application, e.g. https://sdg.mmmut.ac.in/academic-audit):
        // build every link from APP_URL instead of guessing the base path from the request.
        $url = (string) config('app.url');
        if (trim((string) parse_url($url, PHP_URL_PATH), '/') !== '') {
            URL::forceRootUrl(rtrim($url, '/'));
            if (str_starts_with($url, 'https://')) {
                URL::forceScheme('https');
            }
        }
    }
}
