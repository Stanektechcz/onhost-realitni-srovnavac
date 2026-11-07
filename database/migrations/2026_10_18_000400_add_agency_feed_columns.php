<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->string('feed_token_hash', 64)->nullable()->unique();
            $t->boolean('feed_autopublish')->default(false);
            $t->timestamp('feed_last_used_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->dropUnique(['feed_token_hash']);
            $t->dropColumn(['feed_token_hash', 'feed_autopublish', 'feed_last_used_at']);
        });
    }
};
