<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A pre-computed daily performance snapshot per agent, so the leaderboard
     * doesn't have to aggregate call_logs on every page load.
     */
    public function up(): void
    {
        Schema::create('agent_scorecards', function (Blueprint $table) {
            $table->id();

            // Which agent this row belongs to, copied off the user record.
            $table->string('agent_name', 255);
            $table->string('agent_email', 255);

            // The day this snapshot covers, e.g. "2026-06-06".
            $table->string('scorecard_date', 255);

            // Snapshot lifecycle: draft, final, flagged...
            $table->string('status', 255)->default('final');

            // Call activity totals for the day.
            $table->integer('total_calls')->default(0);
            $table->integer('connected_calls')->default(0);
            $table->integer('conversions')->default(0);

            // Total talk time and the day's conversion rate.
            $table->float('talk_time_seconds');
            $table->float('conversion_rate');

            // The agent's most frequent outcomes that day, e.g.
            // "Successful Contact,Interested,Follow-up".
            $table->text('top_outcomes')->nullable();

            // The full computed metrics blob, kept for auditing.
            $table->text('raw_payload')->nullable();

            $table->timestamps();

            // Index the date for day-scoped reports, plus a combined one for
            // date + status filtering.
            $table->index('scorecard_date');
            $table->index(['scorecard_date', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agent_scorecards');
    }
};
