<?php

declare(strict_types=1);

use App\Models\CallLog;
use App\Models\Lead;
use App\Models\Reminder;
use App\Models\User;

it('belongs to a lead and a user', function () {
    $lead = Lead::factory()->create();
    $user = User::factory()->create();
    $callLog = CallLog::factory()->create(['lead_id' => $lead->id, 'user_id' => $user->id]);

    expect($callLog->lead->is($lead))->toBeTrue()
        ->and($callLog->user->is($user))->toBeTrue();
});

it('formats duration as H:i:s and returns null when duration is null', function () {
    $callLog = CallLog::factory()->make(['duration' => 3725]);

    expect($callLog->duration_hms)->toBe('01:02:05');

    $callLog->duration = null;
    expect($callLog->duration_hms)->toBeNull();
});

it('exposes only callback-typed reminders via callbackReminder', function () {
    $callLog = CallLog::factory()->create();

    Reminder::factory()->create(['call_log_id' => $callLog->id, 'type' => 'follow-up']);
    $callback = Reminder::factory()->callback()->create(['call_log_id' => $callLog->id]);

    expect($callLog->callbackReminder->is($callback))->toBeTrue();
});
