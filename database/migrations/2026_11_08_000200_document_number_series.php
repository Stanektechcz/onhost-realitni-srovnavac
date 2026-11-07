<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Kontrakt onhost-operator/1, pravidlo 4: číselné řady dokladů per subjekt + rok (App\Support\DocumentNumber). Jen přidává, stávající záznamy zůstávají beze změny (číslo null). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_sequences', function (Blueprint $t) {
            $t->id();
            $t->string('operator_slug', 64);
            $t->string('series', 32);
            $t->unsignedSmallInteger('year');
            $t->unsignedBigInteger('last_number')->default(0);
            $t->timestamps();
            $t->unique(['operator_slug', 'series', 'year']);
        });
        Schema::table('payments', function (Blueprint $t) {
            $t->string('document_number', 64)->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $t) {
            $t->dropUnique(['document_number']);
            $t->dropColumn('document_number');
        });
        Schema::dropIfExists('document_sequences');
    }
};
