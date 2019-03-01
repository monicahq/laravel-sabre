<?php

namespace LaravelSabre;

use Closure;

class LaravelSabre
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
     * @var \Closure
     */
    private static $auth;

    /**
     * Returns list of nodes to create the sabre collection.
     *
     * @return array|\Sabre\DAV\Tree|\Sabre\DAV\INode
     */
    public static function getNodes()
    {
        if (is_callable(static::$nodes)) {
            return call_user_func_array(static::$nodes, []);
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
        if (is_callable(static::$plugins)) {
            return call_user_func_array(static::$plugins, []);
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
     */
    public static function plugin($plugin)
    {
        if (is_null(static::$plugins)) {
            static::$plugins = [];
        }

        static::$plugins[] = $plugin;

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
        return (static::$auth ?: function () : bool {
            return true;
        })($request);
    }
}
