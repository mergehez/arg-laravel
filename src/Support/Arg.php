<?php

namespace Arg\Laravel\Support;


use Arg\Laravel\Middleware\ArgSetLocale;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

class Arg
{
    /**
     * Call this in bootstrap/app.php > withMiddleware() method
     */
    public static function registerMiddlewares(Middleware $middleware, $handleInertiaClass): void
    {
        $middleware->web(append: [
            $handleInertiaClass,
            ArgSetLocale::class
        ]);
        $middleware->redirectGuestsTo(fn(Request $r) => route('page.login', ['redirect_url' => $r->fullUrl()]));
    }
}