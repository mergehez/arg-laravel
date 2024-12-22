<?php

namespace Arg\Laravel\Schema;

use Illuminate\Database\Migrations\Migration;

abstract class ArgMigration extends Migration
{
    public abstract function up(): void;
    public abstract function down(): void;
}
