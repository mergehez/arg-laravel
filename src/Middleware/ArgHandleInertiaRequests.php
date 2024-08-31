<?php

namespace Arg\Laravel\Middleware;

use Arg\Laravel\Enums\ArgBaseEnum;
use Arg\Laravel\Models\ArgUser;
use Arg\Laravel\Support\ArgState;
use Illuminate\Http\Request;
use Inertia\Middleware;

abstract class ArgHandleInertiaRequests extends Middleware
{
    protected array $supportedLanguages;

    /**
     * @param ArgBaseEnum|class-string  $displayLangEnum
     */
    public function __construct(string $displayLangEnum)
    {
        $this->supportedLanguages = $displayLangEnum::getValues();
    }

    protected $rootView = 'app';

    abstract public function shareCustom(Request $request, bool $isPanel, ?ArgUser $user): array;

    public function share(Request $request): array
    {
        $isPanel = $request->routeIs('panel.*');

        $auth = ArgState::authNullable();
        $sessionLifetime = intval(config('session.lifetime'));

        $base = array_merge(parent::share($request), [
            'csrf_token' => csrf_token(),
            'auth' => [
                'user' => $auth,
                'session_lifetime' => $sessionLifetime,
                'session_expire' => fn () => $auth ? time() + $sessionLifetime * 60 : null,
                // 'activeUsers' => $auth ? State::getActiveUsers()->unique('user_id') : [],
            ],
            'localization' => function () {
                if (! session()->has('locale')) {
                    session()->put('locale', app()->getLocale());
                }

                return [
                    'locale' => session()->get('locale'),
                    'locales' => $this->supportedLanguages,
                    'current_time' => time(),
                ];
            },
        ]);

        return array_merge($base, $this->shareCustom($request, $isPanel, $auth));
    }
}
