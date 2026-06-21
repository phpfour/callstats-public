<?php

declare(strict_types=1);

use App\Models\CallLog;
use App\Models\Lead;
use App\Models\Reminder;
use App\Models\User;
use Illuminate\Support\Carbon;
use Tests\TestCase;

beforeEach(function () {
    Carbon::setTestNow('2026-06-21 12:00:00');
});

function asAdmin(): TestCase
{
    return test()->actingAs(User::factory()->admin()->create());
}

/**
 * Create a lead whose latest call has the given outcome.
 */
function leadWithLastOutcome(string $outcome): Lead
{
    $lead = Lead::factory()->create();

    CallLog::factory()->for($lead)->create([
        'called_at' => now()->subDays(1),
        'outcome' => $outcome,
    ]);

    return $lead;
}

/**
 * Seed one lead per queue: an overdue reminder, a reminder due today,
 * and a lead whose latest call needs a callback.
 *
 * @return array{overdue: Lead, dueToday: Lead, needsCall: Lead}
 */
function seedQueueFixture(): array
{
    $overdue = leadWithLastOutcome('Interested');
    Reminder::factory()->for($overdue)->callback()->create(['remind_at' => now()->subWeek()]);

    $dueToday = leadWithLastOutcome('Interested');
    Reminder::factory()->for($dueToday)->callback()->create(['remind_at' => now()]);

    $needsCall = leadWithLastOutcome('Missed');

    return ['overdue' => $overdue, 'dueToday' => $dueToday, 'needsCall' => $needsCall];
}

// --- Access control -------------------------------------------------------

it('renders the follow-ups page for a supervisor', function () {
    $supervisor = User::factory()->supervisor()->create();

    $this->actingAs($supervisor)
        ->get('/backoffice/follow-ups')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('backoffice/follow-ups/index'));
});

it('renders the follow-ups page for an admin', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get('/backoffice/follow-ups')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('backoffice/follow-ups/index'));
});

it('forbids an agent from viewing the follow-ups page', function () {
    $agent = User::factory()->agent()->create();

    $this->actingAs($agent)
        ->get('/backoffice/follow-ups')
        ->assertForbidden();
});

it('redirects guests to login', function () {
    $this->get('/backoffice/follow-ups')->assertRedirect('/login');
});

// --- Qualifying by call outcome ------------------------------------------

it('lists a lead whose latest call was missed', function () {
    leadWithLastOutcome('Missed');

    asAdmin()
        ->get('/backoffice/follow-ups')
        ->assertInertia(fn ($page) => $page->has('leads.data', 1));
});

it('lists a lead whose latest call was unanswered', function () {
    leadWithLastOutcome('No Answer');

    asAdmin()
        ->get('/backoffice/follow-ups')
        ->assertInertia(fn ($page) => $page->has('leads.data', 1));
});

it('excludes a lead whose latest call had a non-qualifying outcome', function () {
    leadWithLastOutcome('Successful Contact');

    asAdmin()
        ->get('/backoffice/follow-ups')
        ->assertInertia(fn ($page) => $page->has('leads.data', 0));
});

it('excludes a lead whose missed call was superseded by a newer outcome', function () {
    $lead = Lead::factory()->create();

    CallLog::factory()->for($lead)->create([
        'called_at' => now()->subDays(3),
        'outcome' => 'Missed',
    ]);
    CallLog::factory()->for($lead)->create([
        'called_at' => now()->subDay(),
        'outcome' => 'Interested',
    ]);

    asAdmin()
        ->get('/backoffice/follow-ups')
        ->assertInertia(fn ($page) => $page->has('leads.data', 0));
});

it('excludes a lead with no calls and no due reminder', function () {
    Lead::factory()->create();

    asAdmin()
        ->get('/backoffice/follow-ups')
        ->assertInertia(fn ($page) => $page->has('leads.data', 0));
});

// --- Qualifying by callback reminder -------------------------------------

it('lists a lead with a callback reminder due today', function () {
    $lead = leadWithLastOutcome('Interested');
    Reminder::factory()->for($lead)->callback()->create(['remind_at' => now()]);

    asAdmin()
        ->get('/backoffice/follow-ups')
        ->assertInertia(fn ($page) => $page->has('leads.data', 1));
});

it('lists a lead with an overdue callback reminder', function () {
    $lead = leadWithLastOutcome('Interested');
    Reminder::factory()->for($lead)->callback()->create(['remind_at' => now()->subWeek()]);

    asAdmin()
        ->get('/backoffice/follow-ups')
        ->assertInertia(fn ($page) => $page->has('leads.data', 1));
});

it('excludes a lead whose only callback reminder is in the future', function () {
    $lead = leadWithLastOutcome('Interested');
    Reminder::factory()->for($lead)->callback()->create(['remind_at' => now()->addWeek()]);

    asAdmin()
        ->get('/backoffice/follow-ups')
        ->assertInertia(fn ($page) => $page->has('leads.data', 0));
});

it('excludes a lead whose due reminder is not a callback', function () {
    $lead = leadWithLastOutcome('Interested');
    Reminder::factory()->for($lead)->create([
        'type' => 'follow-up',
        'remind_at' => now()->subDay(),
    ]);

    asAdmin()
        ->get('/backoffice/follow-ups')
        ->assertInertia(fn ($page) => $page->has('leads.data', 0));
});

// --- De-duplication -------------------------------------------------------

it('lists a lead once when it qualifies under both rules', function () {
    $lead = leadWithLastOutcome('Missed');
    Reminder::factory()->for($lead)->callback()->create(['remind_at' => now()]);

    asAdmin()
        ->get('/backoffice/follow-ups')
        ->assertInertia(fn ($page) => $page->has('leads.data', 1));
});

// --- Row detail -----------------------------------------------------------

it('exposes the required fields for each row', function () {
    $agent = User::factory()->agent()->create(['name' => 'Dana Agent']);
    $lead = Lead::factory()->create([
        'name' => 'Priya Lead',
        'phone_number' => '+8801712345678',
        'assigned_to_id' => $agent->id,
    ]);
    CallLog::factory()->for($lead)->create([
        'called_at' => now()->subDay(),
        'outcome' => 'Missed',
    ]);
    Reminder::factory()->for($lead)->callback()->create(['remind_at' => now()]);

    asAdmin()
        ->get('/backoffice/follow-ups')
        ->assertInertia(fn ($page) => $page
            ->has('leads.data', 1)
            ->where('leads.data.0.name', 'Priya Lead')
            ->where('leads.data.0.phone_number', '+8801712345678')
            ->where('leads.data.0.assigned_agent', 'Dana Agent')
            ->where('leads.data.0.last_outcome', 'Missed')
            ->where('leads.data.0.next_reminder_at', fn ($value) => $value !== null)
            ->where('leads.data.0.last_called_at', fn ($value) => $value !== null));
});

it('renders a row for a lead with no assigned agent and no reminder', function () {
    $lead = Lead::factory()->create(['assigned_to_id' => null]);
    CallLog::factory()->for($lead)->create([
        'called_at' => now()->subDay(),
        'outcome' => 'No Answer',
    ]);

    asAdmin()
        ->get('/backoffice/follow-ups')
        ->assertInertia(fn ($page) => $page
            ->has('leads.data', 1)
            ->where('leads.data.0.assigned_agent', null)
            ->where('leads.data.0.next_reminder_at', null));
});

// --- Row navigation data --------------------------------------------------

it('points the row at the latest call log', function () {
    $lead = Lead::factory()->create();
    CallLog::factory()->for($lead)->create([
        'called_at' => now()->subDays(3),
        'outcome' => 'Successful Contact',
    ]);
    $latest = CallLog::factory()->for($lead)->create([
        'called_at' => now()->subDay(),
        'outcome' => 'Missed',
    ]);

    asAdmin()
        ->get('/backoffice/follow-ups')
        ->assertInertia(fn ($page) => $page
            ->where('leads.data.0.last_call_id', $latest->id));
});

it('exposes a null call id for a reminder-only lead with no calls', function () {
    $lead = Lead::factory()->create();
    Reminder::factory()->for($lead)->callback()->create(['remind_at' => now()->subDay()]);

    asAdmin()
        ->get('/backoffice/follow-ups')
        ->assertInertia(fn ($page) => $page
            ->has('leads.data', 1)
            ->where('leads.data.0.last_call_id', null));
});

// --- Queue tabs -----------------------------------------------------------

it('defaults to the All queue and lists every lead needing follow-up', function () {
    seedQueueFixture();

    asAdmin()
        ->get('/backoffice/follow-ups')
        ->assertInertia(fn ($page) => $page
            ->where('activeQueue', 'all')
            ->has('leads.data', 3));
});

it('falls back to the All queue for an unrecognized queue value', function () {
    seedQueueFixture();

    asAdmin()
        ->get('/backoffice/follow-ups?queue=bogus')
        ->assertInertia(fn ($page) => $page
            ->where('activeQueue', 'all')
            ->has('leads.data', 3));
});

it('overdue queue shows only leads with a callback reminder due before today', function () {
    ['overdue' => $overdue] = seedQueueFixture();

    asAdmin()
        ->get('/backoffice/follow-ups?queue=overdue')
        ->assertInertia(fn ($page) => $page
            ->where('activeQueue', 'overdue')
            ->has('leads.data', 1)
            ->where('leads.data.0.lead_id', $overdue->id));
});

it('due today queue shows only leads with a callback reminder due today', function () {
    ['dueToday' => $dueToday] = seedQueueFixture();

    asAdmin()
        ->get('/backoffice/follow-ups?queue=due-today')
        ->assertInertia(fn ($page) => $page
            ->where('activeQueue', 'due-today')
            ->has('leads.data', 1)
            ->where('leads.data.0.lead_id', $dueToday->id));
});

it('needs call queue shows only leads whose latest call was missed or unanswered', function () {
    ['needsCall' => $needsCall] = seedQueueFixture();

    asAdmin()
        ->get('/backoffice/follow-ups?queue=needs-call')
        ->assertInertia(fn ($page) => $page
            ->where('activeQueue', 'needs-call')
            ->has('leads.data', 1)
            ->where('leads.data.0.lead_id', $needsCall->id));
});

it('keeps the active queue in pagination links', function () {
    Lead::factory()->count(26)->create()->each(function (Lead $lead) {
        CallLog::factory()->for($lead)->create([
            'called_at' => now()->subDay(),
            'outcome' => 'Missed',
        ]);
    });

    asAdmin()
        ->get('/backoffice/follow-ups?queue=needs-call')
        ->assertInertia(fn ($page) => $page
            ->where('activeQueue', 'needs-call')
            ->where('leads.next_page_url', fn ($url) => str_contains((string) $url, 'queue=needs-call')));
});

it('counts each queue independent of the active queue', function () {
    seedQueueFixture();

    asAdmin()
        ->get('/backoffice/follow-ups?queue=overdue')
        ->assertInertia(fn ($page) => $page
            ->where('counts.all', 3)
            ->where('counts.overdue', 1)
            ->where('counts.due-today', 1)
            ->where('counts.needs-call', 1));
});

it('reports a zero count for an empty queue', function () {
    leadWithLastOutcome('Missed');

    asAdmin()
        ->get('/backoffice/follow-ups')
        ->assertInertia(fn ($page) => $page
            ->where('counts.needs-call', 1)
            ->where('counts.overdue', 0)
            ->where('counts.due-today', 0));
});
