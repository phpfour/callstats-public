<?php

declare(strict_types=1);

use App\Models\User;

it('deletes a non-admin user', function () {
    $admin = User::factory()->admin()->create();
    $target = User::factory()->agent()->create();

    $this->actingAs($admin)
        ->delete("/backoffice/users/{$target->id}")
        ->assertRedirect('/backoffice/users')
        ->assertSessionHas('success');

    expect(User::find($target->id))->toBeNull();
});

it('deletes an admin when another admin exists', function () {
    $admin = User::factory()->admin()->create();
    $second = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->delete("/backoffice/users/{$second->id}")
        ->assertRedirect('/backoffice/users');

    expect(User::find($second->id))->toBeNull();
});

it('refuses to delete the last admin', function () {
    $onlyAdmin = User::factory()->admin()->create();

    $this->actingAs($onlyAdmin)
        ->delete("/backoffice/users/{$onlyAdmin->id}")
        ->assertSessionHasErrors('user');

    expect(User::find($onlyAdmin->id))->not->toBeNull();
});

it('forbids supervisors from deleting users', function () {
    $supervisor = User::factory()->supervisor()->create();
    $target = User::factory()->agent()->create();

    $this->actingAs($supervisor)
        ->delete("/backoffice/users/{$target->id}")
        ->assertForbidden();

    expect(User::find($target->id))->not->toBeNull();
});
