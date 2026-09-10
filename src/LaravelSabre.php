<?php

namespace LaravelSabre;

use Closure;
use Illuminate\Container\Container;
use Illuminate\Http\Request;
use Sabre\DAV\INode;
use Sabre\DAV\ServerPlugin;
use Sabre\DAV\Tree;

/**
 * The entry point an application uses to register its DAV resource tree, plugins, access rule and
 * principal mapping.
 *
 * Every call is delegated to the registry bound in the application container, so no registration
 * state lives in this class and two applications in one process never share it.
 *
 * @api
 */
final class LaravelSabre
{
    /**
     * Register the resource tree: an array of nodes, one node, a prebuilt tree, or a provider
     * closure returning any of those.
     *
     * @param  array<int, mixed>|Tree|INode|Closure|iterable<mixed>|null  $nodes
     */
    public static function nodes($nodes): Registry
    {
        return self::registry()->nodes($nodes);
    }

    /**
     * Register plugins in bulk, directly or as a provider closure. Appends to what is registered.
     *
     * @param  array<int, mixed>|ServerPlugin|callable|iterable<mixed>|null  $plugins
     */
    public static function plugins($plugins): Registry
    {
        return self::registry()->plugins($plugins);
    }

    /**
     * Register one plugin. May be called before or after a bulk registration.
     *
     * @param  mixed  $plugin
     */
    public static function plugin($plugin): Registry
    {
        return self::registry()->plugin($plugin);
    }

    /**
     * Register the rule deciding whether a request may use the endpoint.
     */
    public static function auth(Closure $callback): Registry
    {
        return self::registry()->auth($callback);
    }

    /**
     * Register the mapping from the signed in user to a DAV principal identifier.
     */
    public static function principal(Closure $callback): Registry
    {
        return self::registry()->principal($callback);
    }

    /**
     * Whether the given request may use the endpoint.
     */
    public static function check(Request $request): bool
    {
        return self::registry()->check($request);
    }

    /**
     * Forget every registration.
     */
    public static function clear(): void
    {
        self::registry()->clear();
    }

    private static function registry(): Registry
    {
        /** @var Registry */
        return Container::getInstance()->make(Registry::class);
    }
}
