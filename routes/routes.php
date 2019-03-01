<?php

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

Illuminate\Routing\Router::$verbs = array_merge(Illuminate\Routing\Router::$verbs, $verbs);

Route::match($verbs, '{path?}', 'DAVController@init')->where('path', '(.)*');
