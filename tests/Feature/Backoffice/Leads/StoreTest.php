<?php

declare(strict_types=1);

use App\Models\Lead;
use App\Models\User;
use Illuminate\Support\Carbon;

it('renders the create form for an admin', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get('/backoffice/leads/create')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('backoffice/leads/create'));
});

it('rejects an agent attempting to create a lead', function () {
    $agent = User::factory()->agent()->create();

    $this->actingAs($agent)
        ->get('/backoffice/leads/create')
        ->assertForbidden();
});

it('persists a new lead and redirects with a success flash', function () {
    $admin = User::factory()->admin()->create();
    $assignee = User::factory()->agent()->create();

    Carbon::setTestNow('2026-05-06 10:00:00');

    $response = $this->actingAs($admin)->post('/backoffice/leads', [
        'name' => 'Aisha Khan',
        'phone_number' => '+8801711000111',
        'email' => 'aisha@example.com',
        'study_destination' => 'Canada',
        'source' => 'Facebook',
        'ielts_score' => '7.0',
        'assigned_to_id' => $assignee->id,
    ]);

    $response->assertRedirect('/backoffice/leads')->assertSessionHas('success');

    $lead = Lead::where('phone_number', '+8801711000111')->firstOrFail();

    expect($lead->name)->toBe('Aisha Khan')
        ->and($lead->email)->toBe('aisha@example.com')
        ->and($lead->study_destination)->toBe('Canada')
        ->and($lead->source)->toBe('Facebook')
        ->and($lead->ielts_score)->toBe('7.0')
        ->and($lead->assigned_to_id)->toBe($assignee->id)
        ->and($lead->assigned_at?->toDateTimeString())->toBe('2026-05-06 10:00:00');
});

it('rejects a lead with a duplicate phone number', function () {
    $admin = User::factory()->admin()->create();
    Lead::factory()->create(['phone_number' => '+8801999000000']);

    $this->actingAs($admin)
        ->post('/backoffice/leads', [
            'name' => 'Duplicate Donna',
            'phone_number' => '+8801999000000',
        ])
        ->assertSessionHasErrors('phone_number');
});

it('requires name and phone number', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post('/backoffice/leads', [])
        ->assertSessionHasErrors(['name', 'phone_number']);
});
