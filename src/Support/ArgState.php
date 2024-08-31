<?php

namespace Arg\Laravel\Support;

use Arg\Laravel\Models\ArgUser;
use Illuminate\Contracts\Auth\Authenticatable;

class ArgState
{
    public const int lastLocalizationUpdate = 1714481662;
    private static ?Authenticatable $loggedUser;

    private static bool $checkedLoggedUser = false;

    public static function authNullable(): ?ArgUser
    {
        if (! self::$checkedLoggedUser) {
            self::$loggedUser = auth()->user();
            self::$checkedLoggedUser = true;
        }

        /** @var ArgUser */
        return self::$loggedUser;
    }

    /**
     * this is used when we are sure that user is logged in
     */
    public static function auth(): ArgUser
    {
        $user = self::authNullable();
        if (! $user) {
            abort(401, 'Unauthorized access');
        }

        return $user;
    }
}
