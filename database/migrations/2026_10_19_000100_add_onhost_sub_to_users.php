<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // ONhost ID: párový `sub` pro tohoto klienta – jediný klíč vazby účtu (kontrakt onhost-id).
            $table->string('onhost_sub', 191)->nullable()->unique()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['onhost_sub']);
            $table->dropColumn('onhost_sub');
        });
    }
};
