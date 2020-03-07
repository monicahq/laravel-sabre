<?php

namespace LaravelSabre;

use Closure;
use LaravelSabre\Exception\InvalidStateException;

final class LaravelSabre
{
    /**
     * The collection of node to use with the sabre server.
     *
     * @var array|\Sabre\DAV\Tree|\Sabre\DAV\INode|\Closure
     */
    private static $nodes;

    /**
     * The collection of plugins to register to the sabre server.
     *
     * @var array|\Closure
     */
    private static $plugins;

    /**
     * The callback used to authenticate a request.
     *
     * @var null|\Closure
     */
    private static $auth;

    /**
     * Returns list of nodes to create the sabre collection.
     *
     * @return array|\Sabre\DAV\Tree|\Sabre\DAV\INode
     */
    public static function getNodes()
    {
        if (static::$nodes instanceof Closure) {
            return (static::$nodes)();
        }

        return static::$nodes;
    }

    /**
     * Sets the list of nodes used to create the sabre collection.
     *
     * @param array|\Sabre\DAV\Tree|\Sabre\DAV\INode|\Closure  $nodes
     * @return static
     */
    public static function nodes($nodes)
    {
        if ($nodes instanceof Closure ||
            $nodes instanceof \Sabre\DAV\Tree ||
            $nodes instanceof \Sabre\DAV\INode) {
            static::$nodes = $nodes;
        } else {
            static::$nodes = collect($nodes)->toArray();
        }

        return new static;
    }

    /**
     * Return the list of plugins to add to the sabre server.
     *
     * @return array
     */
    public static function getPlugins()
    {
        if (static::$plugins instanceof Closure) {
            return (static::$plugins)();
        }

        return static::$plugins;
    }

    /**
     * Sets the list of plugins to add to the sabre server.
     *
     * @param mixed  $plugins
     * @return static
     */
    public static function plugins($plugins)
    {
        if ($plugins instanceof Closure) {
            static::$plugins = $plugins;
        } else {
            static::$plugins = collect($plugins)->toArray();
        }

        return new static;
    }

    /**
     * Add a plugin to the sabre server.
     *
     * @param mixed  $plugin
     * @return static
     * @throws InvalidStateException
     */
    public static function plugin($plugin)
    {
        if (! isset(static::$plugins)) {
            static::$plugins = [];
        }

        if (is_array(static::$plugins)) {
            static::$plugins[] = $plugin;
        } else {
            throw new InvalidStateException('plugins is not an array, impossible to use plugin() function.');
        }

        return new static;
    }

    /**
     * Register the LaravelSabre authentication callback.
     *
     * @param \Closure  $callback
     * @return static
     */
    public static function auth(Closure $callback)
    {
        static::$auth = $callback;

        return new static;
    }

    /**
     * Return if the given request can open this dav resource.
     *
     * @param \Illuminate\Http\Request  $request
     * @return bool
     */
    public static function check($request)
    {
        return (static::$auth ?: function (): bool {
            return true;
        })($request);
    }

    /**
     * Clear all datas.
     *
     * @return void
     */
    public static function clear()
    {
        static::$nodes = [];
        static::$plugins = [];
        static::$auth = null;
    }
}
