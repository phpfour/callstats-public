<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Composite index for the hot agent-scoped, date-ranged call_logs queries
 * (agent detail today/30-day stats, recent calls, per-user listing). Equality
 * column (user_id) first, range/sort column (called_at) second.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('call_logs', function (Blueprint $table) {
            $table->index(['user_id', 'called_at']);
        });
    }

    public function down(): void
    {
        Schema::table('call_logs', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'called_at']);
        });
    }
};
