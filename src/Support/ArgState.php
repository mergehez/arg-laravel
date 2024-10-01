<?php

namespace Arg\Laravel\Support;

use Arg\Laravel\Contracts\IUser;
use Arg\Laravel\Controllers\ArgUserController;
use Closure;
use Illuminate\Database\Eloquent\Collection;

class ArgState
{
    protected static ?IUser $loggedUser;

    protected static bool $checkedLoggedUser = false;

    public static ?Closure $onUserSet = null;


    private static ?Collection $activeUsers;

    private static int $activeGuestCount = -1;

    private static bool $checkedActiveUsers = false;

    public static function authNullable(): ?IUser
    {
        if (! self::$checkedLoggedUser) {
            self::$loggedUser = auth()->user();

            if (self::$onUserSet) {
                call_user_func(self::$onUserSet, self::$loggedUser);
            }

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


    public static function getActiveGuestCount(): int
    {
        if (! self::$checkedActiveUsers && self::$loggedUser?->id) {
            $activeUsersAndGuests = ArgUserController::activeUsersAndGuests();
            self::$activeUsers = $activeUsersAndGuests['users'] ?? null;
            self::$activeGuestCount = $activeUsersAndGuests['guests'] ?? null;
            self::$checkedActiveUsers = true;
        }

        return self::$activeGuestCount;
    }

    public static function getActiveUsers(): ?Collection
    {
        self::getActiveGuestCount(); // to make sure activeUsers is populated

        return self::$activeUsers;
    }
}
