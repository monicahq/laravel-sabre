<?php

namespace LaravelSabre;

use Closure;
use ArrayAccess;
use Illuminate\Support\Facades\App;

class LaravelSabre
{
    /**
     * The collection of node to use with the sabre server.
     *
     * @var array|\Sabre\DAV\Tree|\Sabre\DAV\INode
     */
    private static $nodes = [];

    /**
     * The collection of plugins to register to the sabre server.
     *
     * @var array
     */
    private static $plugins = [];

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
        return static::$nodes;
    }

    /**
     * Sets the list of nodes used to create the sabre collection.
     *
     * @param array|\Sabre\DAV\Tree|\Sabre\DAV\INode  $nodes
     * @return static
     */
    public static function nodes($nodes)
    {
        static::$nodes = $nodes;

        return new static;
    }

    /**
     * Return the list of plugins to add to the sabre server.
     *
     * @return array
     */
    public static function getPlugins()
    {
        return static::$plugins;
    }

    /**
     * Sets the list of plugins to add to the sabre server.
     *
     * @param array  $plugins
     * @return static
     */
    public static function plugins($plugins)
    {
        static::$plugins = $plugins;

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
        return (static::$auth ?: function() : bool {
            return true;
        })($request);
    }
}
