<?php

namespace LaravelSabre\Tests;

use Illuminate\Contracts\Auth\Authenticatable;

class Authenticated implements Authenticatable
{
    public $email;

    public function getAuthIdentifierName()
    {
        return 'Identifier name';
    }

    public function getAuthIdentifier()
    {
        return 'auth-identifier';
    }

    public function getAuthPasswordName()
    {
        return 'password';
    }

    public function getAuthPassword()
    {
        return 'secret';
    }

    public function getRememberToken()
    {
        return 'token';
    }

    public function setRememberToken($value)
    {
    }

    public function getRememberTokenName()
    {
    }
}
