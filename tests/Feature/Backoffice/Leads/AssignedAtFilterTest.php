<?php

declare(strict_types=1);

use App\Models\Lead;
use App\Models\User;
use Illuminate\Support\Carbon;

it('includes leads at both edges of the assigned_from/assigned_to range', function () {
    $admin = User::factory()->admin()->create();

    Lead::factory()->create([
        'name' => 'Start Edge',
        'assigned_at' => Carbon::parse('2026-06-01 00:00:00'),
    ]);
    Lead::factory()->create([
        'name' => 'End Edge',
        'assigned_at' => Carbon::parse('2026-06-03 23:59:59'),
    ]);
    Lead::factory()->create([
        'name' => 'Before Window',
        'assigned_at' => Carbon::parse('2026-05-31 23:59:59'),
    ]);
    Lead::factory()->create([
        'name' => 'After Window',
        'assigned_at' => Carbon::parse('2026-06-04 00:00:00'),
    ]);

    $this->actingAs($admin)
        ->get('/backoffice/leads?assigned_from=2026-06-01&assigned_to=2026-06-03')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('leads.data', 2));
});
