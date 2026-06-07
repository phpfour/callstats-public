<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Normalized child of agent_scorecards holding the day's top outcomes, one
 * row per outcome. Replaces the comma-joined top_outcomes text column so the
 * "interested days" lookup can use an index instead of a LIKE scan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_scorecard_outcomes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('agent_scorecard_id');
            $table->string('outcome', 50);
            $table->unsignedInteger('count')->default(0);

            $table->unique(['agent_scorecard_id', 'outcome']);
            $table->index('outcome');

            $table->foreign('agent_scorecard_id')
                ->references('id')
                ->on('agent_scorecards')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_scorecard_outcomes');
    }
};
