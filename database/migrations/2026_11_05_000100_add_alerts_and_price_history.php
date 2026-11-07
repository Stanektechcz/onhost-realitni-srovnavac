<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('saved_searches', function (Blueprint $t) {
            $t->boolean('notify')->default(true);
        });
        Schema::create('listing_price_changes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $t->unsignedBigInteger('old_price_halere');
            $t->unsignedBigInteger('new_price_halere');
            $t->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('listing_price_changes');
        Schema::table('saved_searches', fn (Blueprint $t) => $t->dropColumn('notify'));
    }
};
