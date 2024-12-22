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

    public function timestampInt(string $column, bool $defaultNow = true): ColumnDefinition
    {
        $res = $this->backingBlueprint->unsignedBigInteger($column);
        if ($defaultNow) {
            $res->default(DB::raw('(UNIX_TIMESTAMP())'));
        }
        return $res;
    }

    /**
     * Add audit columns to the given table.
     * - created_at, created_by, updated_at, updated_by
     * - deleted_at, deleted_by (if soft deletable).
     */
    public function addAuditColumns(bool $softDeletable, bool $updatable = true, bool $nullCreator = false, bool $nullUpdator = false): void
    {
        $this->timestampInt('created_at');
        $this->foreignKey('App\Models\User', 'created_by', nullable: $nullCreator);
        if ($updatable) {
            $this->timestampInt('updated_at');
            $this->foreignKey('App\Models\User', 'updated_by', nullable: $nullUpdator);
        }

        if ($softDeletable) {
            //            $this->softDeletes();
            $this->timestampInt('deleted_at', false)->nullable()->index();
            $this->foreignKey('App\Models\User', 'deleted_by', nullable: true);
        }
    }
    /**
     * Add audit columns to the given table.
     * - created_at, updated_at
     * - deleted_at, deleted_by (if soft deletable).
     */
    public function addAuditColumnsNoUser(bool $softDeletable, bool $updatable = true): void
    {
        $this->timestampInt('created_at');
        if ($updatable) {
            $this->timestampInt('updated_at');
        }

        if ($softDeletable) {
            $this->timestampInt('deleted_at', false)->nullable()->index();
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
