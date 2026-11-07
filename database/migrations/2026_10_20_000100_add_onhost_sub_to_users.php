<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'onhost_sub')) {
            return; // sloučené větve: sloupec už přidala druhá migrace
        }
        Schema::table('users', function (Blueprint $table) {
            $table->string('onhost_sub', 255)->nullable()->unique();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('users', 'onhost_sub')) {
            return;
        }
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['onhost_sub']);
            $table->dropColumn('onhost_sub');
        });
    }
};
