<?php

namespace App\Core\Modules;

use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use ReflectionClass;

/**
 * Validates a reviewed list of providers before any module is registered.
 * Directory discovery and implicit module autoloading are deliberately absent.
 */
final class ModuleRegistry
{
    /** @var list<class-string<ServiceProvider>> */
    private readonly array $providers;

    /**
     * @param array<mixed> $definitions
     */
    public function __construct(array $definitions)
    {
        if (! array_is_list($definitions)) {
            throw new InvalidArgumentException('Module definitions must be an explicit list.');
        }

        $ids = [];
        $providerNames = [];
        $providers = [];

        foreach ($definitions as $definition) {
            if (! is_array($definition)) {
                throw new InvalidArgumentException('Each module definition must be an array.');
            }

            $id = $definition['id'] ?? null;
            if (! is_string($id) || trim($id) === '') {
                throw new InvalidArgumentException('Module id must be a non-empty string.');
            }

            $id = trim($id);
            if (isset($ids[$id])) {
                throw new InvalidArgumentException("Duplicate module id [{$id}].");
            }

            $provider = $definition['provider'] ?? null;
            if (! is_string($provider) || trim($provider) === '') {
                throw new InvalidArgumentException('Module provider must be a non-empty class name.');
            }

            if (! class_exists($provider)) {
                throw new InvalidArgumentException("Module provider class [{$provider}] does not exist.");
            }

            if (! is_subclass_of($provider, ServiceProvider::class)) {
                throw new InvalidArgumentException("Module provider [{$provider}] must extend Illuminate\\Support\\ServiceProvider.");
            }

            $providerReflection = new ReflectionClass($provider);
            if (! $providerReflection->isInstantiable()) {
                throw new InvalidArgumentException("Module provider [{$provider}] must be instantiable.");
            }

            // PHP resolves class names case-insensitively after loading. Store
            // the declaration's real identity to prevent alias/case duplicate
            // registrations and register exactly the canonical class name.
            $provider = $providerReflection->getName();
            if (isset($providerNames[$provider])) {
                throw new InvalidArgumentException("Duplicate module provider [{$provider}].");
            }

            $ids[$id] = true;
            $providerNames[$provider] = true;
            $providers[] = $provider;
        }

        $this->providers = $providers;
    }

    /**
     * @return list<class-string<ServiceProvider>>
     */
    public function providers(): array
    {
        return $this->providers;
    }
}
