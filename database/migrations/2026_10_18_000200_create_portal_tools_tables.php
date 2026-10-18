<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('listings', function (Blueprint $t) {
            $t->timestamp('boosted_until')->nullable()->index();
            $t->timestamp('renewed_at')->nullable();
        });

        Schema::table('favorites', function (Blueprint $t) {
            $t->text('note')->nullable();
        });

        Schema::table('messages', function (Blueprint $t) {
            $t->timestamp('archived_at')->nullable();
        });

        // Daily aggregated counters per listing (no personal data).
        Schema::create('listing_stats', function (Blueprint $t) {
            $t->id();
            $t->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $t->date('day');
            $t->unsignedInteger('views')->default(0);
            $t->unsignedInteger('saves')->default(0);
            $t->unsignedInteger('inquiries')->default(0);
            $t->unsignedInteger('bookings')->default(0);
            $t->unique(['listing_id', 'day']);
        });

        Schema::create('viewing_slots', function (Blueprint $t) {
            $t->id();
            $t->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $t->dateTime('starts_at')->index();
            $t->unsignedSmallInteger('duration_min')->default(30);
            $t->foreignId('booked_by')->nullable()->constrained('users')->nullOnDelete();
            $t->string('booked_name')->nullable();
            $t->string('booked_phone', 30)->nullable();
            $t->string('booked_note', 500)->nullable();
            $t->timestamp('booked_at')->nullable();
            $t->timestamps();
        });

        Schema::create('handover_protocols', function (Blueprint $t) {
            $t->id();
            $t->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->json('data');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('handover_protocols');
        Schema::dropIfExists('viewing_slots');
        Schema::dropIfExists('listing_stats');
        Schema::table('messages', fn (Blueprint $t) => $t->dropColumn('archived_at'));
        Schema::table('favorites', fn (Blueprint $t) => $t->dropColumn('note'));
        Schema::table('listings', function (Blueprint $t) {
            $t->dropIndex(['boosted_until']);
            $t->dropColumn(['boosted_until', 'renewed_at']);
        });
    }
};
