<?php

namespace LaravelSabre\Exception;

use RuntimeException;

/**
 * Base class for every exception this package raises.
 *
 * Catching this type catches anything the adapter itself reports, and nothing the DAV engine or the
 * framework reports.
 *
 * @api
 */
class LaravelSabreException extends RuntimeException
{
}
