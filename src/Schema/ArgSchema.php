<?php

namespace Arg\Laravel\Schema;

use Closure;
use Illuminate\Database\Schema\Blueprint as LaravelBlueprint;
use Illuminate\Support\Facades\Schema as LaravelSchema;

class ArgSchema
{
    public static function create(string $table, Closure $callback): void
    {
        LaravelSchema::create($table, function (LaravelBlueprint $table) use ($callback) {
            $callback(new ArgBlueprint($table));
        });
    }

    public static function dropIfExists(string $table): void
    {
        LaravelSchema::dropIfExists($table);
    }
}
