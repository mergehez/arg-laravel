<?php

namespace Arg\Laravel\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin Model
 */
trait IsArgMenuItem
{
    public function getFillable(): array
    {
        return ['menu_id', 'type', 'title', 'url', 'post_id', 'sequence'];
    }

    public function menu(): BelongsTo
    {
        return $this->belongsTo('App\Models\Menu');
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo('App\Models\Post');
    }

    public function newQuery(){
        return parent::newQuery()->with('post:id,title,slug');
    }
}
