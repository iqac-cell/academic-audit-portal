<?php

namespace Mmmut\AcademicAudit;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Mmmut\AcademicAudit\Contracts\DirectoryProvider;
use Mmmut\AcademicAudit\Contracts\PrefillProvider;
use Mmmut\AcademicAudit\Support\Access;

class AcademicAuditServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/academic-audit.php', 'academic-audit');

        $this->app->singleton(Services\SettingsService::class);
        $this->app->singleton(DirectoryProvider::class, fn ($app) => $app->make(config('academic-audit.directory_provider')));
        // configured source (IQAC data after merge) first, then the previous academic year
        $this->app->singleton(PrefillProvider::class, fn ($app) => new Providers\CompositePrefillProvider([
            $app->make(config('academic-audit.prefill_provider')),
            $app->make(Providers\PreviousCyclePrefillProvider::class),
        ]));
        $this->app->bind(Services\Ai\DocumentExtractor::class, fn ($app) => $app->make(config('academic-audit.document_extractor')));
        $this->app->scoped(Access::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'audit');
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');

        $this->publishes([__DIR__.'/../config/academic-audit.php' => config_path('academic-audit.php')], 'academic-audit-config');

        View::composer('audit::*', fn ($view) => $view->with(['access' => app(Access::class), 'settings' => app(Services\SettingsService::class)]));
        $this->app->booted(fn () => $this->app->make(Services\SettingsService::class)->applyToConfig());
        Blade::directive('marks', fn ($e) => "<?php echo \\Mmmut\\AcademicAudit\\Support\\Fmt::marks({$e}); ?>");

        if ($this->app->runningInConsole()) {
            $this->commands([
                Console\ImportErpCommand::class,
                Console\NewCycleCommand::class,
                Console\GrantRoleCommand::class,
                Console\RemindCommand::class,
            ]);
        }
    }
}
