<?php

declare(strict_types=1);

use App\Models\Lead;
use App\Models\User;
use Illuminate\Http\UploadedFile;

it('rejects an upload that exceeds the size limit', function () {
    $admin = User::factory()->admin()->create();

    // 6 MB > the 5 MB (5120 KB) cap; valid xlsx mime so the size rule is what fails.
    $file = UploadedFile::fake()->create(
        'leads.xlsx',
        6144,
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    );

    $this->actingAs($admin)
        ->post('/backoffice/leads/import', ['file' => $file])
        ->assertSessionHasErrors('file');

    expect(Lead::count())->toBe(0);
});
