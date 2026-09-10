<?php

namespace LaravelSabre;

use Closure;
use Illuminate\Http\Request;
use LaravelSabre\Exception\LaravelSabreException;
use Sabre\DAV\INode;
use Sabre\DAV\ServerPlugin;
use Sabre\DAV\Tree;
use Traversable;

/**
 * Holds everything an application registers with the DAV endpoint.
 *
 * One instance belongs to one application instance, which is what keeps two applications in a single
 * process, as the test suite and long-lived workers create, from sharing or overwriting each other's
 * registration.
 *
 * @internal
 */
final class Registry
{
    /**
     * The resource tree, as an array of nodes, a single node, a prebuilt tree or a provider.
     *
     * @var array<int, mixed>|Tree|INode|Closure
     */
    private $nodes = [];

    /**
     * Plugin registrations in the order they were made. An entry is a plugin or a provider.
     *
     * @var array<int, ServerPlugin|callable>
     */
    private array $plugins = [];

    private ?Closure $accessRule = null;

    private ?Closure $principalMapper = null;

    /**
     * Register the resource tree.
     *
     * @param  array<int, mixed>|Tree|INode|Closure|iterable<mixed>|null  $nodes
     */
    public function nodes($nodes): self
    {
        if ($nodes instanceof Closure || $nodes instanceof Tree || $nodes instanceof INode) {
            $this->nodes = $nodes;

            return $this;
        }

        $this->nodes = $this->toList($nodes);

        return $this;
    }

    /**
     * Register plugins in bulk, either directly or as a provider resolved per request.
     *
     * Entries are appended, so bulk and single registration may be interleaved in any order.
     *
     * @param  array<int, mixed>|ServerPlugin|callable|iterable<mixed>|null  $plugins
     */
    public function plugins($plugins): self
    {
        if (is_null($plugins)) {
            return $this;
        }

        if ($plugins instanceof ServerPlugin) {
            return $this->plugin($plugins);
        }

        if (is_callable($plugins)) {
            $this->plugins[] = $plugins;

            return $this;
        }

        foreach ($this->toList($plugins) as $plugin) {
            $this->plugin($plugin);
        }

        return $this;
    }

    /**
     * Register a single plugin.
     *
     * @param  mixed  $plugin
     */
    public function plugin($plugin): self
    {
        $this->plugins[] = $this->assertPlugin($plugin);

        return $this;
    }

    /**
     * Register the rule deciding whether a request may use the endpoint.
     */
    public function auth(Closure $rule): self
    {
        $this->accessRule = $rule;

        return $this;
    }

    /**
     * Register the mapping from the signed in user to a principal identifier.
     */
    public function principal(Closure $mapper): self
    {
        $this->principalMapper = $mapper;

        return $this;
    }

    /**
     * Whether the given request may use the endpoint. Admits when no rule is registered.
     */
    public function check(Request $request): bool
    {
        if (is_null($this->accessRule)) {
            return true;
        }

        return (bool) ($this->accessRule)($request);
    }

    public function principalMapper(): ?Closure
    {
        return $this->principalMapper;
    }

    /**
     * Resolve the resource tree for the current request, running a provider if one is registered.
     *
     * @return array<int, mixed>|Tree|INode
     */
    public function resolveNodes()
    {
        $nodes = $this->nodes;

        if ($nodes instanceof Closure) {
            $nodes = $nodes();
        }

        if ($nodes instanceof Tree || $nodes instanceof INode) {
            return $nodes;
        }

        return $this->toList($nodes);
    }

    /**
     * Resolve every registered plugin for the current request, in registration order.
     *
     * @return array<int, ServerPlugin>
     */
    public function resolvePlugins(): array
    {
        $resolved = [];

        foreach ($this->plugins as $entry) {
            if ($entry instanceof ServerPlugin) {
                $resolved[] = $entry;

                continue;
            }

            $provided = $entry();

            if (is_null($provided)) {
                continue;
            }

            if ($provided instanceof ServerPlugin) {
                $resolved[] = $provided;

                continue;
            }

            foreach ($this->toList($provided) as $plugin) {
                $resolved[] = $this->assertPlugin($plugin);
            }
        }

        return $resolved;
    }

    /**
     * Forget every registration. Kept so consuming test suites can reset between cases.
     */
    public function clear(): void
    {
        $this->nodes = [];
        $this->plugins = [];
        $this->accessRule = null;
        $this->principalMapper = null;
    }

    /**
     * @param  mixed  $value
     * @return array<int, mixed>
     */
    private function toList($value): array
    {
        if (is_null($value)) {
            return [];
        }

        if (is_array($value)) {
            return array_values($value);
        }

        if ($value instanceof Traversable) {
            return iterator_to_array($value, false);
        }

        throw new LaravelSabreException(sprintf(
            'Expected an array, a traversable, a node, a tree or a closure, got %s.',
            get_debug_type($value)
        ));
    }

    /**
     * @param  mixed  $plugin
     */
    private function assertPlugin($plugin): ServerPlugin
    {
        if ($plugin instanceof ServerPlugin) {
            return $plugin;
        }

        throw new LaravelSabreException(sprintf(
            'A plugin must be an instance of %s, got %s.',
            ServerPlugin::class,
            get_debug_type($plugin)
        ));
    }
}
