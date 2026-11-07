<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agencies', function (Blueprint $t) {
            $t->id();
            $t->string('ico', 8)->unique();
            $t->string('slug')->unique();
            $t->string('name');
            $t->string('legal_form', 10)->nullable();
            $t->boolean('natural_person')->default(false);
            $t->string('address')->nullable();
            $t->string('city')->nullable();
            $t->string('region')->nullable()->index();
            $t->string('web')->nullable();
            $t->string('phone', 30)->nullable();
            $t->string('email')->nullable();
            $t->string('licence')->nullable();
            $t->string('source', 10)->default('ares');
            $t->foreignId('claimed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('verified_at')->nullable();
            $t->decimal('rating_avg', 3, 2)->nullable();
            $t->unsignedInteger('reviews_count')->default(0);
            $t->date('founded_on')->nullable();
            $t->timestamp('ares_synced_at')->nullable();
            $t->timestamps();
        });

        Schema::create('brokers', function (Blueprint $t) {
            $t->id();
            $t->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $t->string('slug')->unique();
            $t->string('name');
            $t->string('bio', 2000)->nullable();
            $t->string('specialization')->nullable();
            $t->boolean('is_public')->default(true);
            $t->decimal('rating_avg', 3, 2)->nullable();
            $t->unsignedInteger('reviews_count')->default(0);
            $t->timestamps();
        });

        Schema::create('agency_reviews', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('agency_id')->nullable()->constrained()->cascadeOnDelete();
            $t->foreignId('broker_id')->nullable()->constrained()->cascadeOnDelete();
            $t->unsignedTinyInteger('rating');
            $t->text('text');
            $t->boolean('verified')->default(false);
            $t->string('status', 10)->default('pending')->index();
            $t->unsignedInteger('report_count')->default(0);
            $t->string('moderation_note', 500)->nullable();
            $t->timestamp('moderated_at')->nullable();
            $t->timestamps();
        });

        Schema::create('agency_review_reports', function (Blueprint $t) {
            $t->id();
            $t->foreignId('agency_review_id')->constrained()->cascadeOnDelete();
            $t->foreignId('reporter_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('reporter_email')->nullable();
            $t->string('reason', 30);
            $t->text('description');
            $t->string('status', 10)->default('open');
            $t->timestamps();
        });

        Schema::create('agency_claims', function (Blueprint $t) {
            $t->id();
            $t->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('message', 1000)->nullable();
            $t->string('status', 10)->default('pending');
            $t->string('method', 10)->nullable(); // feed | admin
            $t->timestamp('decided_at')->nullable();
            $t->timestamps();
            $t->unique(['agency_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agency_claims');
        Schema::dropIfExists('agency_review_reports');
        Schema::dropIfExists('agency_reviews');
        Schema::dropIfExists('brokers');
        Schema::dropIfExists('agencies');
    }
};
