<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('listings', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('slug')->unique();
            $t->string('title')->nullable();
            $t->text('description')->nullable();
            $t->string('offer_type', 10)->index();          // rent|sale
            $t->string('property_type', 20)->index();       // flat|house|land|commercial|room
            $t->string('disposition', 10)->nullable()->index();
            $t->decimal('area_m2', 8, 2)->nullable();
            $t->unsignedBigInteger('price_halere')->nullable()->index();
            $t->unsignedBigInteger('fees_halere')->nullable();
            $t->unsignedBigInteger('deposit_halere')->nullable();
            $t->string('city')->nullable()->index();
            $t->string('district')->nullable()->index();
            $t->string('street')->nullable();
            $t->decimal('lat', 10, 7)->nullable();
            $t->decimal('lng', 10, 7)->nullable();
            $t->string('penb', 10)->nullable();             // A..G | none
            $t->string('furnishing', 10)->nullable();       // none|partial|full
            $t->smallInteger('floor')->nullable();
            $t->boolean('has_elevator')->default(false);
            $t->boolean('has_balcony')->default(false);
            $t->boolean('has_parking')->default(false);
            $t->boolean('pets_allowed')->default(false);
            $t->date('available_from')->nullable();
            $t->string('status', 10)->default('draft')->index();
            $t->string('source', 10)->default('internal')->index();
            $t->string('source_name')->nullable();
            $t->string('source_id')->nullable();
            $t->string('source_url', 1000)->nullable();
            $t->text('moderation_note')->nullable();
            $t->unsignedInteger('views')->default(0);
            $t->timestamp('published_at')->nullable()->index();
            $t->timestamp('expires_at')->nullable();
            $t->timestamps();
            $t->unique(['source_name', 'source_id']);
        });

        Schema::create('listing_photos', function (Blueprint $t) {
            $t->id();
            $t->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $t->string('path');
            $t->string('thumb_path');
            $t->string('avif_path')->nullable();
            $t->unsignedSmallInteger('width')->nullable();
            $t->unsignedSmallInteger('height')->nullable();
            $t->unsignedSmallInteger('position')->default(0);
            $t->timestamps();
        });

        Schema::create('saved_searches', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->json('filters');
            $t->timestamp('last_notified_at')->nullable();
            $t->timestamps();
        });

        Schema::create('favorites', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $t->timestamps();
            $t->unique(['user_id', 'listing_id']);
        });

        Schema::create('messages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $t->foreignId('sender_id')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('recipient_id')->constrained('users')->cascadeOnDelete();
            $t->string('sender_name');
            $t->string('sender_email');
            $t->string('sender_phone', 30)->nullable();
            $t->text('body');
            $t->string('relay_token', 40)->unique();
            $t->timestamp('read_at')->nullable();
            $t->string('ip', 45)->nullable();
            $t->timestamps();
        });

        Schema::create('reports', function (Blueprint $t) {
            $t->id();
            $t->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $t->foreignId('reporter_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('reporter_email')->nullable();
            $t->string('reason', 30);
            $t->text('description');
            $t->string('status', 12)->default('open')->index(); // open|upheld|dismissed
            $t->text('decision_reason')->nullable();
            $t->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('decided_at')->nullable();
            $t->timestamps();
        });

        Schema::create('membership_plans', function (Blueprint $t) {
            $t->id();
            $t->string('code')->unique();
            $t->string('name');
            $t->string('audience', 12); // advertiser|seeker
            $t->unsignedBigInteger('price_halere')->default(0);
            $t->unsignedSmallInteger('period_days')->default(30);
            $t->json('features')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('subscriptions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('membership_plan_id')->constrained()->cascadeOnDelete();
            $t->string('status', 12)->default('pending'); // pending|active|cancelled|expired
            $t->timestamp('starts_at')->nullable();
            $t->timestamp('ends_at')->nullable();
            $t->timestamps();
        });

        Schema::create('commission_rules', function (Blueprint $t) {
            $t->id();
            $t->string('service', 40)->index(); // e.g. membership, listing_boost
            $t->decimal('percent', 5, 2)->default(0);
            $t->unsignedBigInteger('fixed_halere')->default(0);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('payments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->nullableMorphs('payable');
            $t->string('service', 40);
            $t->string('provider', 20);
            $t->string('provider_ref')->nullable()->index();
            $t->unsignedBigInteger('amount_halere');
            $t->unsignedBigInteger('commission_halere')->default(0);
            $t->string('currency', 3)->default('CZK');
            $t->string('status', 12)->default('created'); // created|paid|failed|refunded
            $t->json('meta')->nullable();
            $t->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('action', 60)->index();
            $t->nullableMorphs('subject');
            $t->json('data')->nullable();
            $t->string('ip', 45)->nullable();
            $t->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        foreach (['audit_logs', 'payments', 'commission_rules', 'subscriptions', 'membership_plans', 'reports', 'messages', 'favorites', 'saved_searches', 'listing_photos', 'listings'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
