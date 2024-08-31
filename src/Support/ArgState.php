<?php

namespace Arg\Laravel\Support;

use Arg\Laravel\Contracts\IUser;
use Illuminate\Contracts\Auth\Authenticatable;

class ArgState
{
    public const int lastLocalizationUpdate = 1714481662;
    private static ?Authenticatable $loggedUser;

    private static bool $checkedLoggedUser = false;

    public static function authNullable(): ?IUser
    {
        if (! self::$checkedLoggedUser) {
            self::$loggedUser = auth()->user();
            self::$checkedLoggedUser = true;
        }

        /** @var IUser */
        return self::$loggedUser;
    }

    /**
     * this is used when we are sure that user is logged in
     */
    public static function auth(): IUser
    {
        $user = self::authNullable();
        if (! $user) {
            abort(401, 'Unauthorized access');
        }

        return $user;
    }
}
