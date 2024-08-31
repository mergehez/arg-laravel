<?php

namespace Arg\Laravel\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @mixin Model
 */
trait IsArgMenu
{
    public function getFillable(): array
    {
        return ['title'];
    }
    public function items(): HasMany
    {
        return $this->hasMany('App\Models\MenuItem');
    }
}
