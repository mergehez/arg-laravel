<?php

namespace Arg\Laravel\Models;

use App\Models\BaseModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArgSession extends BaseModel
{
    protected $table = 'sessions';

    public $timestamps = false;

    public function user(): BelongsTo // nullable
    {
        return $this->belongsTo(User::class);
    }

    public function scopeActiveGuestCount(Builder $query, int $since = 0): int
    {
        if ($since == 0) {
            $since = now()->subMinutes(15)->timestamp;
        }

        return $query->whereNull('user_id')
            ->where('last_activity', '>=', $since)
            ->count();
    }

    public function scopeActiveUsers(Builder $query, int $since = 0): Builder
    {
        if ($since == 0) {
            /** @var int $sessionLifetime */
            $sessionLifetime = config('session.lifetime');
            $since = now()->subMinutes($sessionLifetime)->timestamp;
        } else {
            $since = now()->subMinutes($since)->timestamp;
        }

        return $query
            ->with(['user:id,username,avatar'])
            ->select(['user_id', 'last_activity', 'created_at'])
            ->whereNotNull('user_id')
            ->where('last_activity', '>=', $since);
    }
}
