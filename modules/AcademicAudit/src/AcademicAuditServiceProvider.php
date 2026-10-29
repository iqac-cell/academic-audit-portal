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

        $this->app->singleton(DirectoryProvider::class, fn ($app) => $app->make(config('academic-audit.directory_provider')));
        $this->app->singleton(PrefillProvider::class, fn ($app) => $app->make(config('academic-audit.prefill_provider')));
        $this->app->scoped(Access::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'audit');
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');

        $this->publishes([__DIR__.'/../config/academic-audit.php' => config_path('academic-audit.php')], 'academic-audit-config');

        View::composer('audit::*', fn ($view) => $view->with('access', app(Access::class)));
        Blade::directive('marks', fn ($e) => "<?php echo \\Mmmut\\AcademicAudit\\Support\\Fmt::marks({$e}); ?>");

        if ($this->app->runningInConsole()) {
            $this->commands([
                Console\ImportErpCommand::class,
                Console\NewCycleCommand::class,
                Console\GrantRoleCommand::class,
            ]);
        }
    }
}
