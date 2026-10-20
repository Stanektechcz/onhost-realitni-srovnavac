<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rental_contracts', function (Blueprint $t) {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $t->foreignId('landlord_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('tenant_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('tenant_email');
            $t->string('status', 20)->default('draft')->index(); // draft|signed_landlord|sent|signed_both|cancelled|expired
            $t->json('data');                                    // parties, property, terms
            $t->unsignedBigInteger('rent_halere');
            $t->unsignedBigInteger('fees_halere')->default(0);
            $t->unsignedBigInteger('deposit_halere')->default(0);
            $t->unsignedInteger('version')->default(1);
            $t->timestamp('sent_at')->nullable();
            $t->timestamp('completed_at')->nullable();
            $t->timestamp('cancelled_at')->nullable();
            $t->timestamps();
        });

        Schema::create('rental_contract_signatures', function (Blueprint $t) {
            $t->id();
            $t->foreignId('rental_contract_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('role', 10); // landlord|tenant
            $t->string('typed_name');
            $t->unsignedInteger('version');
            $t->char('text_sha256', 64);
            $t->string('ip', 45)->nullable();
            $t->string('user_agent', 500)->nullable();
            $t->timestamp('signed_at');
            $t->timestamp('invalidated_at')->nullable();
            $t->string('invalidated_reason')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rental_contract_signatures');
        Schema::dropIfExists('rental_contracts');
    }
};
