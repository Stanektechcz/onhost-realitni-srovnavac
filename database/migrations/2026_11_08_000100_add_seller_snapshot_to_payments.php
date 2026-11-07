<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Kontrakt onhost-operator/1: platba (doklad) zamrazí prodávajícího v okamžiku vzniku (App\Support\Operator::snapshot).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', fn (Blueprint $t) => $t->json('seller_snapshot')->nullable());
    }

    public function down(): void
    {
        Schema::table('payments', fn (Blueprint $t) => $t->dropColumn('seller_snapshot'));
    }
};
