<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rebuild agent_scorecards with a properly typed, FK-backed schema.
 *
 * The table is a derived daily snapshot — every value is recomputable from
 * call_logs via AgentScorecardSeeder — so it is safe to drop and recreate
 * this single table and reseed it. No other table is touched.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('agent_scorecards');

        Schema::create('agent_scorecards', function (Blueprint $table) {
            $table->id();

            // Real agent reference, replacing the copied agent_name/agent_email.
            $table->unsignedBigInteger('user_id');

            // The business day this snapshot covers.
            $table->date('scorecard_date');

            // Snapshot lifecycle: draft, final, flagged.
            $table->string('status', 20)->default('final');

            // Call activity totals for the day.
            $table->unsignedInteger('total_calls')->default(0);
            $table->unsignedInteger('connected_calls')->default(0);
            $table->unsignedInteger('conversions')->default(0);

            // Whole seconds of talk time and the day's conversion rate.
            $table->unsignedInteger('talk_time_seconds')->default(0);
            $table->decimal('conversion_rate', 5, 2)->default(0);

            // Promoted out of the raw_payload JSON-in-text blob.
            $table->boolean('review')->default(false);

            // Retained for auditing only — never queried with LIKE.
            $table->json('raw_payload')->nullable();

            $table->timestamps();

            // One snapshot per agent per day. Doubles as the per-agent range
            // lookup index (leftmost user_id) and satisfies the FK, so no
            // separate single-column index is needed.
            $table->unique(['user_id', 'scorecard_date']);

            // Date-window + status scoped scans (leaderboard range, reports).
            $table->index(['scorecard_date', 'status']);

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_scorecards');

        // Recreate the original (pre-audit) shape for reversibility.
        Schema::create('agent_scorecards', function (Blueprint $table) {
            $table->id();
            $table->string('agent_name', 255);
            $table->string('agent_email', 255);
            $table->string('scorecard_date', 255);
            $table->string('status', 255)->default('final');
            $table->integer('total_calls')->default(0);
            $table->integer('connected_calls')->default(0);
            $table->integer('conversions')->default(0);
            $table->float('talk_time_seconds');
            $table->float('conversion_rate');
            $table->text('top_outcomes')->nullable();
            $table->text('raw_payload')->nullable();
            $table->timestamps();
            $table->index('scorecard_date');
            $table->index(['scorecard_date', 'status']);
        });
    }
};
