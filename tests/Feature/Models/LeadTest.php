<?php

declare(strict_types=1);

use App\Models\CallLog;
use App\Models\Lead;
use App\Models\Reminder;
use App\Models\User;
use Illuminate\Support\Carbon;

it('eagerly resolves the assigned-to user', function () {
    $user = User::factory()->create();
    $lead = Lead::factory()->create(['assigned_to_id' => $user->id]);

    expect($lead->assignedTo->is($user))->toBeTrue();
});

it('exposes call_logs and reminders as has-many relations', function () {
    $lead = Lead::factory()->create();
    $callLogs = CallLog::factory()->count(3)->create(['lead_id' => $lead->id]);
    $reminders = Reminder::factory()->count(2)->create(['lead_id' => $lead->id]);

    expect($lead->callLogs)->toHaveCount(3)
        ->and($lead->reminders)->toHaveCount(2)
        ->and($lead->callLogs->pluck('id')->all())->toEqualCanonicalizing($callLogs->pluck('id')->all())
        ->and($lead->reminders->pluck('id')->all())->toEqualCanonicalizing($reminders->pluck('id')->all());
});

it('returns the most recent call via lastCall', function () {
    $lead = Lead::factory()->create();
    CallLog::factory()->create(['lead_id' => $lead->id, 'called_at' => Carbon::parse('2026-01-01 09:00')]);
    $latest = CallLog::factory()->create(['lead_id' => $lead->id, 'called_at' => Carbon::parse('2026-01-05 16:00')]);
    CallLog::factory()->create(['lead_id' => $lead->id, 'called_at' => Carbon::parse('2026-01-03 12:00')]);

    expect($lead->lastCall->is($latest))->toBeTrue();
});

it('stamps assigned_at when assigned_to_id changes', function () {
    Carbon::setTestNow('2026-05-06 10:00:00');

    $user = User::factory()->create();
    $lead = Lead::factory()->create(['assigned_to_id' => null, 'assigned_at' => null]);

    $lead->update(['assigned_to_id' => $user->id]);

    expect($lead->fresh()->assigned_at?->toDateTimeString())->toBe('2026-05-06 10:00:00');
});

it('respects an explicit assigned_at value during the same save', function () {
    $user = User::factory()->create();
    $explicit = Carbon::parse('2025-12-25 09:00:00');

    $lead = Lead::factory()->create([
        'assigned_to_id' => $user->id,
        'assigned_at' => $explicit,
    ]);

    expect($lead->fresh()->assigned_at?->toDateTimeString())->toBe($explicit->toDateTimeString());
});

it('does not touch assigned_at when assigned_to_id is unchanged', function () {
    $user = User::factory()->create();
    $original = Carbon::parse('2025-11-01 08:00:00');

    $lead = Lead::factory()->create([
        'assigned_to_id' => $user->id,
        'assigned_at' => $original,
    ]);

    $lead->update(['name' => 'Renamed']);

    expect($lead->fresh()->assigned_at?->toDateTimeString())->toBe($original->toDateTimeString());
});
