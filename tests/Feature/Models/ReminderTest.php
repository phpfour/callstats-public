<?php

declare(strict_types=1);

use App\Models\CallLog;
use App\Models\Lead;
use App\Models\Reminder;
use App\Models\User;

it('belongs to a lead, a call_log, and a user', function () {
    $lead = Lead::factory()->create();
    $callLog = CallLog::factory()->create(['lead_id' => $lead->id]);
    $user = User::factory()->create();

    $reminder = Reminder::factory()->create([
        'lead_id' => $lead->id,
        'call_log_id' => $callLog->id,
        'user_id' => $user->id,
    ]);

    expect($reminder->lead->is($lead))->toBeTrue()
        ->and($reminder->callLog->is($callLog))->toBeTrue()
        ->and($reminder->user->is($user))->toBeTrue();
});

it('allows null call_log_id (reminder not tied to a specific call)', function () {
    $reminder = Reminder::factory()->create(['call_log_id' => null]);

    expect($reminder->call_log_id)->toBeNull()
        ->and($reminder->callLog)->toBeNull();
});
