# Arg-Laravel


Steps

1. Extend `AppServiceProvider` with `ArgAppServiceProvider`
    - shares theme with app.blade.php
    - rate-limits login attempts
2. Extend Enums with `ArgBaseEnum`
3. Add `IUser` interface to `User` model
4. Extend `HandleInertiaRequests` with `ArgHandleInertiaRequests`
5. Add following to `bootstrap/app.php`:
```php
return Application::configure(basePath: dirname(__DIR__))
    // starting here
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            // ...
        ]);

        Arg::registerMiddlewares($middleware, HandleInertiaRequests::class);
    })
    ->withExceptions(fn ($e) => Arg::withExceptionFn($e))->create();
```