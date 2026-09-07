<?php

namespace Goldnead\Assessments;

use Goldnead\Assessments\Integrations\Automations\AutomationsBridge;
use Goldnead\Assessments\Integrations\LeadHubBridge;
use Goldnead\Assessments\Support\Settings;
use Goldnead\BrandContext\Settings\SettingsRegistry;
use Statamic\Facades\CP\Nav;
use Statamic\Facades\Permission;
use Statamic\Providers\AddonServiceProvider;

class ServiceProvider extends AddonServiceProvider
{
    protected $routes = [
        'cp' => __DIR__.'/../routes/cp.php',
        'web' => __DIR__.'/../routes/web.php',
    ];

    // Registered by hand in register() under the short `assessments`
    // namespace, plus the JSON path the Vue layer's `__('Some sentence')`
    // calls resolve through. The parent's automatic registration would use
    // the package slug and cover only the first of the two.
    protected $translations = false;

    protected $config = false;

    protected $viewNamespace = 'assessments';

    /**
     * Statamic 6 reads the addon's Vite configuration from this property and
     * from nowhere else. The three values must byte-match `laravel()` in
     * vite.config.js.
     */
    protected $vite = [
        'hotFile' => __DIR__.'/../dist/hot',
        'publicDirectory' => 'dist',
        'input' => ['resources/js/cp.js', 'resources/css/cp.css'],
    ];

    public function register(): void
    {
        parent::register();

        $this->mergeConfigFrom(__DIR__.'/../config/assessments.php', 'assessments');

        $langPath = __DIR__.'/../lang';

        $this->app->resolving('translator', function ($translator) use ($langPath) {
            $translator->addNamespace('assessments', $langPath);
            $translator->addJsonPath($langPath);
        });

        if ($this->app->resolved('translator')) {
            $this->app['translator']->addNamespace('assessments', $langPath);
            $this->app['translator']->addJsonPath($langPath);
        }

        $this->app->singleton(AssessmentsManager::class);
        $this->app->singleton(LeadHubBridge::class);
        $this->app->singleton(AutomationsBridge::class);
    }

    /**
     * In `boot()`, nicht in `bootAddon()`, und das ist keine Stilfrage.
     *
     * brand-context legt die gespeicherten Werte aus einem `app->booted()` auf
     * die Config, absichtlich erst dann, damit jedes Provider-`boot()` seine
     * Anmeldung hinter sich hat. `bootAddon()` läuft selbst aus einem
     * `app->booted()`, und welches der beiden zuerst feuert, hängt an der
     * Ladereihenfolge der Pakete.
     */
    public function boot(): void
    {
        parent::boot();

        $this->app->make(SettingsRegistry::class)->register(Settings::class);
    }

    public function bootAddon(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'assessments');

        $this
            ->bootNav()
            ->bootPermissions()
            ->bootAutomations()
            ->bootPublishables();
    }

    protected function bootNav(): self
    {
        Nav::extend(function ($nav) {
            $nav->create(__('assessments::messages.nav'))
                ->section('Tools')
                ->icon('clipboard-check')
                ->route('assessments.index')
                ->can('view assessments');
        });

        return $this;
    }

    protected function bootPermissions(): self
    {
        Permission::extend(function () {
            Permission::group('assessments', __('assessments::messages.permission_group'), function () {
                Permission::register('view assessments')
                    ->label(__('assessments::messages.permission_view'))
                    ->children([
                        Permission::make('edit assessments')
                            ->label(__('assessments::messages.permission_edit')),
                        Permission::make('view assessment responses')
                            ->label(__('assessments::messages.permission_responses')),
                    ]);

                // Eigenes Recht, nicht als Kind von `view assessments`: wer
                // Assessments ansehen darf, darf deshalb noch nicht die
                // Betriebswerte des Addons verstellen.
                Permission::register('manage assessments settings')
                    ->label(__('assessments::settings.permission_manage'));
            });
        });

        return $this;
    }

    /**
     * Offer the trigger to the automations addon, if it is there.
     *
     * From a booted callback: the sibling's bindings exist only once its own
     * provider has booted, and this one may boot first. The bridge is
     * idempotent, so the second pass of Statamic's double-booted callbacks
     * costs nothing.
     */
    protected function bootAutomations(): self
    {
        $register = fn () => $this->app->make(AutomationsBridge::class)->register();

        $this->app->booted(function () use ($register): void {
            $register();

            $this->app->booted($register);
        });

        return $this;
    }

    protected function bootPublishables(): self
    {
        $this->publishes([
            __DIR__.'/../config/assessments.php' => config_path('assessments.php'),
        ], 'assessments-config');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/assessments'),
        ], 'assessments-views');

        $this->publishes([
            __DIR__.'/../lang' => $this->app->langPath('vendor/assessments'),
        ], 'assessments-translations');

        return $this;
    }
}
