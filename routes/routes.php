<?php

use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use LaravelSabre\Http\Controllers\DAVController;

/** @var array<int, string> $methods */
$methods = array_values(array_unique(array_map(
    'strtoupper',
    (array) config('laravelsabre.methods', [])
)));

// Kept for parity with 1.x: the framework verb list is extended so that a host application's own
// catch-all routes and the framework's 405 alternate-verb diagnostics still recognise DAV methods.
// Dispatching the endpoint itself does not depend on this, because the route below names its
// methods explicitly.
Router::$verbs = array_values(array_unique(array_merge(Router::$verbs, $methods)));

Route::match($methods, '{path?}', [DAVController::class, 'init'])
    ->name('sabre.dav')
    ->where('path', '(.)*');
