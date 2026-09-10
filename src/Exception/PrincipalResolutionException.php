<?php

namespace LaravelSabre\Exception;

/**
 * Raised when the signed in user cannot be mapped to a DAV principal.
 *
 * 1.x concatenated whatever it found, so a user without an email address produced the principal
 * "principals/", which clients then failed on in confusing ways. Failing here instead names the
 * cause: either the configured attribute is missing on the user, or a registered mapping returned
 * nothing usable.
 *
 * @api
 */
final class PrincipalResolutionException extends LaravelSabreException
{
    public static function emptyValue(string $source, string $identifier): self
    {
        return new self(sprintf(
            'Cannot build a DAV principal for the signed in user [%s]: %s produced no value. '
            .'Register a mapping with LaravelSabre::principal() or set the laravelsabre.principal_attribute option.',
            $identifier,
            $source
        ));
    }
}
