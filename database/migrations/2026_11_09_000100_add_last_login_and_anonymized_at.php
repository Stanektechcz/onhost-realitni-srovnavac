<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->timestamp('last_login_at')->nullable()->index());
        Schema::table('listings', fn (Blueprint $t) => $t->timestamp('anonymized_at')->nullable());
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('last_login_at'));
        Schema::table('listings', fn (Blueprint $t) => $t->dropColumn('anonymized_at'));
    }
};
