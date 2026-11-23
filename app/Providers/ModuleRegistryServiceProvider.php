<?php

namespace App\Providers;

use App\Core\Modules\ModuleRegistry;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

/**
 * The single Laravel lifecycle hook for the reviewed explicit module list.
 */
final class ModuleRegistryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $definitions = $this->app['config']->get('modules', []);

        if (! is_array($definitions)) {
            throw new InvalidArgumentException('Module registry config must be an array.');
        }

        // Validate every entry before activating any provider.
        $registry = new ModuleRegistry($definitions);

        $this->app->instance(ModuleRegistry::class, $registry);

        foreach ($registry->providers() as $provider) {
            $this->app->register($provider);
        }
    }
}
