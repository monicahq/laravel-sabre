<?php

namespace LaravelSabre\Tests\Support;

use Illuminate\Support\Facades\Auth;
use Sabre\DAV\File;

/**
 * A DAV file whose body is the email address of the currently signed in user, used to prove that no
 * identity leaks from one request into the next.
 */
class IdentityFile extends File
{
    public function getName()
    {
        return 'whoami.txt';
    }

    public function get()
    {
        $user = Auth::user();

        return is_null($user) ? 'anonymous' : (string) $user->email;
    }

    public function getContentType()
    {
        return 'text/plain';
    }
}
