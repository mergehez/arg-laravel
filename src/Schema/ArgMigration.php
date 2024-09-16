<?php

namespace Arg\Laravel\Schema;

use Illuminate\Database\Migrations\Migration;

abstract class ArgMigration extends Migration
{
    //    /**
    //     * Add audit columns to the given table.
    //     * - created_at, created_by, updated_at, updated_by
    //     * - deleted_at, deleted_by (if soft deletable).
    //     */
    //    protected function addAuditColumns(MyBlueprint $table, bool $softDeletable): void
    //    {
    //        $table->timestamp('created_at')->default(DB::raw('(UNIX_TIMESTAMP())'));
    //        $table->foreignId('created_by')->references('id')->on('users');
    //        $table->timestamp('updated_at')->default(DB::raw('(UNIX_TIMESTAMP())'));
    //        $table->foreignId('updated_by')->references('id')->on('users');
    //
    //        if ($softDeletable) {
    //            $table->timestamp('deleted_at')->nullable();
    //            $table->foreignId('deleted_by')->nullable()->references('id')->on('users');
    //        }
    //    }
    //
    //    protected function jsonArray(MyBlueprint $table, string $column): ColumnDefinition
    //    {
    //        return $table->json($column)->default(DB::raw('(json_array())'));
    //    }
}
