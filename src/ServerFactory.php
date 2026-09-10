<?php

namespace LaravelSabre;

use LaravelSabre\Sabre\Server;

/**
 * Builds a configured DAV engine for the current request.
 *
 * Registration lives in the registry and is resolved here, once per request and after middleware, so
 * a provider closure may depend on the signed in user.
 *
 * @internal
 */
final class ServerFactory
{
    public static function make(Registry $registry): Server
    {
        $server = new Server($registry->resolveNodes());

        foreach ($registry->resolvePlugins() as $plugin) {
            $server->addPlugin($plugin);
        }

        return $server;
    }
}
