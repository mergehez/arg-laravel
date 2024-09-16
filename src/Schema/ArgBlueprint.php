<?php

namespace Arg\Laravel\Schema;

use Arg\Laravel\Enums\ArgBaseEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\ColumnDefinition;
use Illuminate\Database\Schema\ForeignIdColumnDefinition;
use Illuminate\Database\Schema\ForeignKeyDefinition;
use Illuminate\Support\Facades\DB;

/**
 * @mixin Blueprint
 */
class ArgBlueprint // extends Blueprint
{
    private Blueprint $backingBlueprint;

    public function __construct(Blueprint $original)
    {
        // copy all properties from original
        //        foreach ($original as $key => $value) {
        //            $this->{$key} = $value;
        //        }
        $this->backingBlueprint = $original;
        //        parent::__construct($original->table);
    }

    /**
     * Add audit columns to the given table.
     * - created_at, created_by, updated_at, updated_by
     * - deleted_at, deleted_by (if soft deletable).
     */
    public function addAuditColumns(bool $softDeletable, bool $updatable = true, bool $nullCreator = false, bool $nullUpdator = false): void
    {
        $this->backingBlueprint->unsignedBigInteger('created_at')->default(DB::raw('(UNIX_TIMESTAMP())'));
        $this->foreignKey('App\Models\User', 'created_by', nullable: $nullCreator);
        if ($updatable) {
            $this->backingBlueprint->unsignedBigInteger('updated_at')->default(DB::raw('(UNIX_TIMESTAMP())'));
            $this->foreignKey('App\Models\User', 'updated_by', nullable: $nullUpdator);
        }

        if ($softDeletable) {
            //            $this->softDeletes();
            $this->backingBlueprint->unsignedBigInteger('deleted_at')->nullable()->index();
            $this->foreignKey('App\Models\User', 'deleted_by', nullable: true);
        }
    }

    public function addRatingColumns(string $model, string $valueComment): void
    {
        $this->foreignKey($model, 'target_id');
        $this->unsignedTinyInteger('value')->comment($valueComment);
        $this->addAuditColumns(false, false);

        $this->unique(['target_id', 'created_by']);
    }

    public function jsonArray(string $column): ColumnDefinition
    {
        return $this->backingBlueprint->json($column)->default(DB::raw('(json_array())'));
    }

    public function foreignKey(string $model, ?string $column = null, bool $constrained = true, bool $nullable = false): ForeignKeyDefinition|ForeignIdColumnDefinition
    {
        $model = new $model;
        /** @var Model $model */
        $res = $this->backingBlueprint->foreignIdFor($model, $column);
        if ($nullable) {
            $res = $res->nullable();
        }
        if ($constrained) {
            return $res->constrained($model->getTable());
        }

        return $res;
    }

    public function argEnum(string|ArgBaseEnum $enumClass, string $column): ColumnDefinition
    {
        return $this->backingBlueprint->enum($column, $enumClass::getValues())->comment($enumClass::toColumnComment());
    }

    public function __call($method, $parameters)
    {
        return $this->backingBlueprint->{$method}(...$parameters);
    }
}
