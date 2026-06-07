<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The leads list defaults to sorting by assigned_at and filters it by range.
 * Index it so both the range scan and the order-by are served by an index
 * once the queries are made sargable (no DATE() wrapping).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->index('assigned_at');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndex(['assigned_at']);
        });
    }
};
