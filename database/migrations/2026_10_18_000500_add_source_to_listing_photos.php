<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('listing_photos', function (Blueprint $t) {
            $t->string('source_url', 2048)->nullable();
            $t->char('source_hash', 64)->nullable(); // sha256 of the downloaded bytes
        });
    }

    public function down(): void
    {
        Schema::table('listing_photos', function (Blueprint $t) {
            $t->dropColumn(['source_url', 'source_hash']);
        });
    }
};
