<?php

namespace LaravelSabre\Http\Auth;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use LaravelSabre\Exception\PrincipalResolutionException;
use LaravelSabre\Registry;

/**
 * Resolves the application's signed in user and maps them to a DAV principal.
 *
 * The guard and the attribute come from configuration, defaulting to the application's own default
 * guard and to the email address, which is what 1.x hardcoded. An application that needs more than
 * an attribute registers a mapping with LaravelSabre::principal().
 *
 * @internal
 */
final class PrincipalResolver
{
    public const PREFIX = 'principals/';

    private Registry $registry;

    private AuthFactory $auth;

    private ?string $guard;

    private string $attribute;

    public function __construct(Registry $registry, AuthFactory $auth, ?string $guard, string $attribute)
    {
        $this->registry = $registry;
        $this->auth = $auth;
        $this->guard = $guard;
        $this->attribute = $attribute;
    }

    /**
     * The user signed in on the configured guard, or null when the request is anonymous.
     */
    public function user(): ?Authenticatable
    {
        return $this->auth->guard($this->guard)->user();
    }

    /**
     * The DAV principal for the given user.
     *
     * @throws PrincipalResolutionException when the mapping yields nothing usable
     */
    public function principalFor(Authenticatable $user): string
    {
        return self::PREFIX.$this->identifierFor($user);
    }

    private function identifierFor(Authenticatable $user): string
    {
        $mapper = $this->registry->principalMapper();

        if (! is_null($mapper)) {
            return $this->assertUsable($mapper($user), 'the registered principal mapping', $user);
        }

        return $this->assertUsable(
            data_get($user, $this->attribute),
            sprintf("the '%s' attribute", $this->attribute),
            $user
        );
    }

    /**
     * @param  mixed  $value
     */
    private function assertUsable($value, string $source, Authenticatable $user): string
    {
        if (is_string($value) && trim($value) !== '') {
            return $value;
        }

        throw PrincipalResolutionException::emptyValue($source, (string) $user->getAuthIdentifier());
    }
}
