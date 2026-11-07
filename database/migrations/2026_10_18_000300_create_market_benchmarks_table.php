<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Official open-data benchmarks (ČSÚ etc.); always shown with source + licence citation.
        Schema::create('market_benchmarks', function (Blueprint $t) {
            $t->id();
            $t->string('source', 40);          // e.g. CSU:CEN0402T02
            $t->string('source_url', 255);
            $t->string('license', 255);
            $t->string('area', 120);           // e.g. Hlavní město Praha, Česko
            $t->string('period', 20);          // e.g. 2025
            $t->string('indicator', 120);
            $t->decimal('value', 14, 2);
            $t->string('unit', 40);
            $t->timestamps();
            $t->unique(['source', 'area', 'period', 'indicator'], 'mb_unique');
            $t->index(['area', 'indicator']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('market_benchmarks');
    }
};
