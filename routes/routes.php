<?php

use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;

$verbs = [
    'GET',
    'HEAD',
    'POST',
    'PUT',
    'PATCH',
    'DELETE',
    'PROPFIND',
    'PROPPATCH',
    'MKCOL',
    'COPY',
    'MOVE',
    'LOCK',
    'UNLOCK',
    'OPTIONS',
    'REPORT',
];

Router::$verbs = array_merge(Router::$verbs, $verbs);

Route::any('{path?}', 'DAVController@init')
    ->name('sabre.dav')
    ->where('path', '(.)*');
