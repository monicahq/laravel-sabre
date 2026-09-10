<?php

namespace LaravelSabre\Http\Auth;

use Illuminate\Container\Container;
use Sabre\DAV\Auth\Backend\BackendInterface;
use Sabre\HTTP\Auth\Bearer;
use Sabre\HTTP\RequestInterface;
use Sabre\HTTP\ResponseInterface;

/**
 * Bridges the application's authentication to the DAV engine.
 *
 * The engine asks this backend who the requester is; the answer comes from the application's own
 * guard, so a user already signed in never re-enters credentials. No credential, token or challenge
 * material is stored on this object or logged.
 *
 * @api
 */
class AuthBackend implements BackendInterface
{
    /**
     * Overrides the configured realm when set through setRealm().
     */
    protected ?string $realm = null;

    private ?PrincipalResolver $resolver;

    public function __construct(?PrincipalResolver $resolver = null)
    {
        $this->resolver = $resolver;
    }

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
     * Report who the requester is, using the application's authentication.
     *
     * @return array{0: bool, 1: string}
     */
    #[\Override]
    public function check(RequestInterface $request, ResponseInterface $response)
    {
        $resolver = $this->resolver();
        $user = $resolver->user();

        if (is_null($user)) {
            return [false, 'User is not authenticated'];
        }

        return [true, $resolver->principalFor($user)];
    }

    /**
     * Ask the client to authenticate, naming the configured realm.
     *
     * @return void
     */
    #[\Override]
    public function challenge(RequestInterface $request, ResponseInterface $response)
    {
        $auth = new Bearer($this->realm(), $request, $response);

        $auth->requireLogin();
    }

    private function resolver(): PrincipalResolver
    {
        if (! is_null($this->resolver)) {
            return $this->resolver;
        }

        /** @var PrincipalResolver */
        return Container::getInstance()->make(PrincipalResolver::class);
    }

    private function realm(): string
    {
        if (! is_null($this->realm)) {
            return $this->realm;
        }

        return (string) config('laravelsabre.realm', 'sabre/dav');
    }
}
