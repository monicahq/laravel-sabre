<?php

namespace LaravelSabre\Tests\Support;

use Illuminate\Support\Facades\Auth;
use LaravelSabre\Tests\Authenticated;

/**
 * Middleware that alters the request and signs a user in, so a test can prove the engine sees the
 * request as it stands after middleware.
 */
class InjectsHeaderAndUser
{
    public function handle($request, $next)
    {
        $request->headers->set('X-Injected-By-Middleware', 'yes');

        $user = new Authenticated();
        $user->email = 'middleware@example.com';
        Auth::setUser($user);

        return $next($request);
    }
}
