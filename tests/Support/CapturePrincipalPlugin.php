<?php

namespace LaravelSabre\Tests\Support;

use Sabre\DAV\Auth\Plugin as AuthPlugin;
use Sabre\DAV\Server;
use Sabre\DAV\ServerPlugin;

/**
 * Records the principal the DAV auth plugin settled on, so a test can assert the identity bridge.
 */
class CapturePrincipalPlugin extends ServerPlugin
{
    public ?string $principal = null;

    public bool $ran = false;

    public function initialize(Server $server)
    {
        // Priority 20 runs after the auth plugin, which authenticates at priority 10.
        $server->on('beforeMethod:*', function () use ($server): void {
            $this->ran = true;

            $auth = $server->getPlugin('auth');
            if ($auth instanceof AuthPlugin) {
                $this->principal = $auth->getCurrentPrincipal();
            }
        }, 20);
    }
}
