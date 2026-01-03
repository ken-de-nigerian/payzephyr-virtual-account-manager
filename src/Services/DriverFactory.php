<?php

declare(strict_types=1);

namespace PayZephyr\VirtualAccounts\Services;

use PayZephyr\VirtualAccounts\Contracts\VirtualAccountProvider;
use PayZephyr\VirtualAccounts\Exceptions\DriverNotFoundException;

/**
 * Factory for creating virtual account provider driver instances.
 *
 * This factory handles driver resolution using convention over configuration,
 * matching PayZephyr's architectural pattern.
 */
final class DriverFactory
{
    /** @var array<string, string> */
    protected array $drivers = [];

    /**
     * Create a driver instance for the given provider name.
     *
     * @param  string  $name  Provider name (e.g., 'flutterwave', 'moniepoint')
     * @param  array<string, mixed>  $config  Provider configuration
     * @return VirtualAccountProvider Driver instance
     *
     * @throws DriverNotFoundException
     */
    public function create(string $name, array $config): VirtualAccountProvider
    {
        $class = $this->resolveDriverClass($name);

        if (! class_exists($class)) {
            throw new DriverNotFoundException("Driver class [$class] not found for driver [$name]");
        }

        if (! is_subclass_of($class, VirtualAccountProvider::class)) {
            throw new DriverNotFoundException("Driver class [$class] must implement VirtualAccountProvider");
        }

        return new $class($config);
    }

    /**
     * Resolve driver class name from provider name.
     *
     * Uses convention over configuration: 'flutterwave' -> FlutterwaveDriver
     * Falls back to config if driver_class is specified.
     *
     * @param  string  $name  Provider name
     * @return string Fully qualified class name
     */
    protected function resolveDriverClass(string $name): string
    {
        if (isset($this->drivers[$name])) {
            return $this->drivers[$name];
        }

        $config = config('virtual-accounts', []);
        $configDriver = $config['providers'][$name]['driver_class'] ?? null;
        if ($configDriver && class_exists($configDriver)) {
            return $configDriver;
        }

        $className = str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $name)));
        $fqcn = "PayZephyr\\VirtualAccounts\\Drivers\\{$className}Driver";

        if (class_exists($fqcn)) {
            return $fqcn;
        }

        return $name;
    }

    /**
     * Register a custom driver class for a provider name.
     *
     * @param  string  $name  Provider name
     * @param  string  $class  Fully qualified class name
     *
     * @throws DriverNotFoundException
     */
    public function register(string $name, string $class): self
    {
        if (! class_exists($class)) {
            throw new DriverNotFoundException("Cannot register driver [$name]: class [$class] does not exist");
        }

        if (! is_subclass_of($class, VirtualAccountProvider::class)) {
            throw new DriverNotFoundException("Cannot register driver [$name]: class [$class] must implement VirtualAccountProvider");
        }

        $this->drivers[$name] = $class;

        return $this;
    }

    /**
     * Get all registered custom drivers.
     *
     * @return array<int, string>
     */
    public function getRegisteredDrivers(): array
    {
        return array_keys($this->drivers);
    }

    /**
     * Check if a driver is registered.
     */
    public function isRegistered(string $name): bool
    {
        return isset($this->drivers[$name]);
    }
}
