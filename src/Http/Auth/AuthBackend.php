<?php

namespace LaravelSabre\Http\Auth;

use Illuminate\Support\Facades\Auth;
use Sabre\DAV\Auth\Backend\BackendInterface;
use Sabre\HTTP\RequestInterface;
use Sabre\HTTP\ResponseInterface;

/**
 * @psalm-suppress UnusedClass
 * @psalm-suppress ClassMustBeFinal
 */
class AuthBackend implements BackendInterface
{
    /**
     * Authentication Realm.
     *
     * The realm is often displayed by browser clients when showing the
     * authentication dialog.
     *
     * @var string
     */
    protected $realm = 'sabre/dav';

    /**
     * Sets the authentication realm for this backend.
     *
     * @param  string  $realm
     * @return void
     */
    public function setRealm($realm)
    {
        $this->realm = $realm;
    }

    /**
     * Check Laravel authentication.
     *
     * @param  RequestInterface  $request
     * @param  ResponseInterface  $response
     * @return array
     */
    #[\Override]
    public function check(RequestInterface $request, ResponseInterface $response)
    {
        /** @var \Illuminate\Foundation\Auth\User|null */
        $user = Auth::user();
        if (is_null($user)) {
            return [false, 'User is not authenticated'];
        }

        /**
         * @psalm-suppress UndefinedMagicPropertyFetch
         */
        return [true, 'principals/'.$user->email];
    }

    /**
     * This method is called when a user could not be authenticated, and
     * authentication was required for the current request.
     *
     * This gives you the opportunity to set authentication headers. The 401
     * status code will already be set.
     *
     * In this case of Bearer Auth, this would for example mean that the
     * following header needs to be set:
     *
     * $response->addHeader('WWW-Authenticate', 'Bearer realm=SabreDAV');
     *
     * Keep in mind that in the case of multiple authentication backends, other
     * WWW-Authenticate headers may already have been set, and you'll want to
     * append your own WWW-Authenticate header instead of overwriting the
     * existing one.
     *
     * @param  RequestInterface  $request
     * @param  ResponseInterface  $response
     * @return void
     */
    #[\Override]
    public function challenge(RequestInterface $request, ResponseInterface $response)
    {
        $auth = new \Sabre\HTTP\Auth\Bearer(
            $this->realm,
            $request,
            $response
        );
        $auth->requireLogin();
    }
}
