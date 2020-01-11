<?php

namespace LaravelSabre\Tests\Unit;

use LaravelSabre\Http\Auth\AuthBackend;
use LaravelSabre\Tests\FeatureTestCase;
use Sabre\HTTP\Request;
use Sabre\HTTP\Response;

class AuthBackendTest extends FeatureTestCase
{
    public function test_check_not_authenticated()
    {
        $backend = new AuthBackend;

        $check = $backend->check(new Request('', ''), new Response);

        $this->assertIsArray($check);
        $this->assertEquals([false, 'User is not authenticated'], $check);
    }

    public function test_check_is_authenticated()
    {
        $this->signin();
        $backend = new AuthBackend;

        $check = $backend->check(new Request('', ''), new Response);

        $this->assertIsArray($check);
        $this->assertEquals([true, 'principals/john@doe.com'], $check);
    }
}
