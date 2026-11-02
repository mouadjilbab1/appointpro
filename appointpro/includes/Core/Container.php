<?php
declare(strict_types=1);

namespace AppointPro\Core;

use InvalidArgumentException;
use RuntimeException;

/**
 * High-performance Inversion of Control (IoC) Service Container.
 */
class Container {
    /**
     * @var array<string, mixed>
     */
    protected array $bindings = [];

    /**
     * @var array<string, mixed>
     */
    protected array $instances = [];

    /**
     * Bind a service resolver to the container.
     *
     * @param string $id
     * @param mixed $concrete
     * @return void
     */
    public function bind(string $id, mixed $concrete): void {
        $this->bindings[$id] = [
            'concrete' => $concrete,
            'shared' => false,
        ];
    }

    /**
     * Bind a singleton service to the container.
     *
     * @param string $id
     * @param mixed $concrete
     * @return void
     */
    public function singleton(string $id, mixed $concrete): void {
        $this->bindings[$id] = [
            'concrete' => $concrete,
            'shared' => true,
        ];
    }

    /**
     * Register an existing instance as a singleton.
     *
     * @param string $id
     * @param mixed $instance
     * @return void
     */
    public function instance(string $id, mixed $instance): void {
        $this->instances[$id] = $instance;
    }

    /**
     * Retrieve an entry from the container.
     *
     * @param string $id
     * @return mixed
     * @throws RuntimeException
     */
    public function get(string $id): mixed {
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }

        if (!isset($this->bindings[$id])) {
            if (class_exists($id)) {
                return $this->build($id);
            }
            throw new RuntimeException("Service or binding '{$id}' not found in container.");
        }

        $concrete = $this->bindings[$id]['concrete'];
        $isShared = $this->bindings[$id]['shared'];

        $resolved = ($concrete instanceof \Closure) ? $concrete($this) : $this->build($concrete);

        if ($isShared) {
            $this->instances[$id] = $resolved;
        }

        return $resolved;
    }

    /**
     * Check if a service identifier exists.
     *
     * @param string $id
     * @return bool
     */
    public function has(string $id): bool {
        return isset($this->bindings[$id]) || isset($this->instances[$id]) || class_exists($id);
    }

    /**
     * Instantiate a class instance.
     *
     * @param mixed $concrete
     * @return mixed
     */
    protected function build(mixed $concrete): mixed {
        if (!is_string($concrete) || !class_exists($concrete)) {
            throw new InvalidArgumentException("Cannot build non-class concrete: " . print_r($concrete, true));
        }

        return new $concrete();
    }
}
