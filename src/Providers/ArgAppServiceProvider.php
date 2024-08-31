<?php

namespace Arg\Laravel\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

abstract class ArgAppServiceProvider extends ServiceProvider
{
    abstract function argBoot(): void;

    public function boot(): void
    {
        Model::shouldBeStrict();
        DB::prohibitDestructiveCommands(config('app.env') === 'production');


        View::composer('app', function ($view) {
            $theme = (array_key_exists('COLOR-THEME', $_COOKIE) && $_COOKIE['COLOR-THEME'] === 'dark') ? 'dark' : '';
            return $view
                ->with('theme', $theme);
        });

        RateLimiter::for('login', function (Request $request) {
            /** @var string $username */
            $username = $request->get('email');
            $throttleKey = Str::transliterate(Str::lower($username).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        $this->argBoot();
    }
}
