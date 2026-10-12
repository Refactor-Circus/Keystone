<?php

declare(strict_types=1);

namespace RefactorCircus\Keystone\Packages;

use RefactorCircus\Keystone\Exceptions\UnknownPackageException;

/**
 * Every package of the suite that is installed, by key.
 *
 * Each package registers itself from its service provider. Base classes then
 * find the package a class belongs to by namespace, and the audit log names
 * the package a change came from.
 */
final class PackageRegistry
{
    /**
     * @var array<string, Package>
     */
    private array $packages = [];

    /**
     * The package each class looked up so far belongs to, cleared whenever a
     * package registers: base classes ask on every request, tool and entry.
     *
     * @var array<string, ?Package>
     */
    private array $owners = [];

    public function register(Package $package): Package
    {
        $this->owners = [];

        return $this->packages[$package->key] = $package;
    }

    public function has(string $key): bool
    {
        return isset($this->packages[$key]);
    }

    public function find(string $key): ?Package
    {
        return $this->packages[$key] ?? null;
    }

    public function get(string $key): Package
    {
        return $this->find($key) ?? throw UnknownPackageException::forKey($key);
    }

    /**
     * The package a class belongs to: the one with the longest matching
     * namespace, so `RefactorCircus\Atrium` never claims a class of `RefactorCircus\AtriumPlus`.
     *
     * @param  object|class-string  $class
     */
    public function for(object|string $class): ?Package
    {
        $name = is_object($class) ? $class::class : $class;

        if (array_key_exists($name, $this->owners)) {
            return $this->owners[$name];
        }

        $match = null;

        foreach ($this->packages as $package) {
            if ($package->owns($name) && strlen($package->namespace) > strlen($match->namespace ?? '')) {
                $match = $package;
            }
        }

        return $this->owners[$name] = $match;
    }

    /**
     * @param  object|class-string  $class
     */
    public function forOrFail(object|string $class): Package
    {
        return $this->for($class) ?? throw UnknownPackageException::forClass(is_object($class) ? $class::class : $class);
    }

    /**
     * @return array<string, Package>
     */
    public function all(): array
    {
        return $this->packages;
    }
}
