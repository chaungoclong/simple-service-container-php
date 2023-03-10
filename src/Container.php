<?php

namespace Chaungoclong\Container;

use Chaungoclong\Container\Exceptions\BindingResolutionException;
use Closure;
use ReflectionClass;
use ReflectionException;
use ReflectionNamedType;

class Container implements \Psr\Container\ContainerInterface
{
    /** @var static */
    protected static ?Container $instance = null;

    /** @var array[] */
    protected array $bindings = [];

    /** @var object[] */
    protected array $instances = [];

    private function __construct()
    {
    }

    public static function getInstance(): Container
    {
        if (is_null(static::$instance)) {
            static::$instance = new static();
        }

        return static::$instance;
    }

    public function bind(string $abstract, $concrete = null, bool $shared = false): void
    {
        $this->bindings[$abstract] = [
            'concrete' => $concrete,
            'shared' => $shared,
        ];
    }

    public function singleton(string $abstract, $concrete = null): void
    {
        $this->bind($abstract, $concrete, true);
    }

    public function instance(string $abstract, $instance): void
    {
        $this->instances[$abstract] = $instance;
    }

    /**
     * @throws ReflectionException
     * @throws BindingResolutionException
     */
    public function make(string $abstract)
    {
        // 1. If the type has already been resolved as a singleton, just return it
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        // 2. Get the registered concrete resolver for this type, otherwise we'll assume we were passed a concretion that we can instantiate
        $concrete = $this->bindings[$abstract]['concrete'] ?? $abstract;

        // 3. If the concrete is either a closure, or we didn't get a resolver, then we'll try to instantiate it.
        if ($concrete instanceof Closure || $concrete === $abstract) {
            $object = $this->build($concrete);
        } // 4. Otherwise, the concrete must be referencing something else, so we'll recursively resolve it until we get either a singleton instance, a closure, or run out of references and will have to try instantiating it.
        else {
            $object = $this->make($concrete);
        }

        // 5. If the class was registered as a singleton, we will hold the instance, so we can always return it.
        if (isset($this->bindings[$abstract]) && $this->bindings[$abstract]['shared']) {
            $this->instances[$abstract] = $object;
        }

        return $object;
    }

    /**
     * @throws ReflectionException
     * @throws BindingResolutionException
     */
    public function build($concrete)
    {
        if ($concrete instanceof Closure) {
            return $concrete($this);
        }

        try {
            $reflector = new ReflectionClass($concrete);
        } catch (ReflectionException $e) {
            throw new BindingResolutionException("Target class [$concrete] does not exist.", 0, $e);
        }

        if (!$reflector->isInstantiable()) {
            throw new BindingResolutionException("Target [$concrete] is not instantiable.");
        }

        $constructor = $reflector->getConstructor();

        if (is_null($constructor)) {
            return new $concrete();
        }

        $dependencies = $constructor->getParameters();

        $instances = $this->resolveDependencies($dependencies);

        return $reflector->newInstanceArgs($instances);
    }

    /**
     * @throws ReflectionException
     * @throws BindingResolutionException
     */
    protected function resolveDependencies(array $dependencies): array
    {
        $results = [];

        foreach ($dependencies as $dependency) {
            // This is a much simpler version of what Laravel does

            $type = $dependency->getType(); // ReflectionType|null

            if (!$type instanceof ReflectionNamedType || $type->isBuiltin()) {
                throw new BindingResolutionException(
                    "Unresolvable dependency resolving [$dependency] in class {$dependency->getDeclaringClass()->getName()}"
                );
            }

            $results[] = $this->make($type->getName());
        }

        return $results;
    }

    /**
     * @inheritDoc
     * @throws ReflectionException
     */
    public function get(string $id)
    {
        return $this->make($id);
    }

    /**
     * @inheritDoc
     */
    public function has(string $id): bool
    {
        return array_key_exists($id, $this->bindings);
    }

    public function flush(): void
    {
        $this->bindings = [];
        $this->instances = [];
    }
}
