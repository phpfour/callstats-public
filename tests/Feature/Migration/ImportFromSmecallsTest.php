<?php

declare(strict_types=1);

use App\Actions\Migration\ImportFromSmecallsAction;
use App\Models\CallLog;
use App\Models\Lead;
use App\Models\Reminder;
use App\Models\User;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

// The global Pest beforeEach seeds roles + permissions, which collides
// with the import command's "destination must be empty" guard. Clear
// the seeded rows so the destination starts blank — but stay inside the
// RefreshDatabase transaction (DELETE, not TRUNCATE).
beforeEach(function () {
    DB::table('model_has_permissions')->delete();
    DB::table('model_has_roles')->delete();
    DB::table('role_has_permissions')->delete();
    DB::table('roles')->delete();
    DB::table('permissions')->delete();
});

/**
 * Configure a SQLite in-memory connection that mimics the smecalls
 * schema closely enough for the import command to chew through it.
 * Tables track only the columns the action actually copies.
 */
function setupSmecallsSource(string $connection = 'smecalls_test'): void
{
    Config::set("database.connections.{$connection}", [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
        'foreign_key_constraints' => false,
    ]);

    DB::purge($connection);
    $schema = Schema::connection($connection);

    $schema->create('users', function ($table): void {
        $table->id();
        $table->string('name');
        $table->string('email')->unique();
        $table->timestamp('email_verified_at')->nullable();
        $table->string('password');
        $table->string('remember_token', 100)->nullable();
        $table->text('two_factor_secret')->nullable();
        $table->text('two_factor_recovery_codes')->nullable();
        $table->timestamp('two_factor_confirmed_at')->nullable();
        $table->string('code')->nullable();
        $table->timestamps();
    });

    $schema->create('roles', function ($table): void {
        $table->id();
        $table->string('name');
        $table->string('guard_name');
        $table->timestamps();
    });

    $schema->create('permissions', function ($table): void {
        $table->id();
        $table->string('name');
        $table->string('guard_name');
        $table->timestamps();
    });

    $schema->create('role_has_permissions', function ($table): void {
        $table->unsignedBigInteger('permission_id');
        $table->unsignedBigInteger('role_id');
        $table->primary(['permission_id', 'role_id']);
    });

    $schema->create('model_has_roles', function ($table): void {
        $table->unsignedBigInteger('role_id');
        $table->string('model_type');
        $table->unsignedBigInteger('model_id');
        $table->primary(['role_id', 'model_id', 'model_type']);
    });

    $schema->create('model_has_permissions', function ($table): void {
        $table->unsignedBigInteger('permission_id');
        $table->string('model_type');
        $table->unsignedBigInteger('model_id');
        $table->primary(['permission_id', 'model_id', 'model_type']);
    });

    $schema->create('leads', function ($table): void {
        $table->id();
        $table->string('name');
        $table->string('phone_number')->unique();
        $table->string('email')->nullable();
        $table->string('study_destination')->nullable();
        $table->string('source')->nullable();
        $table->string('ielts_score')->nullable();
        $table->unsignedBigInteger('assigned_to_id')->nullable();
        $table->timestamp('assigned_at')->nullable();
        $table->timestamps();
    });

    $schema->create('call_logs', function ($table): void {
        $table->id();
        $table->unsignedBigInteger('lead_id');
        $table->unsignedBigInteger('user_id');
        $table->timestamp('called_at');
        $table->integer('duration')->nullable();
        $table->text('notes')->nullable();
        $table->string('outcome')->nullable();
        $table->timestamps();
    });

    $schema->create('reminders', function ($table): void {
        $table->id();
        $table->unsignedBigInteger('lead_id');
        $table->unsignedBigInteger('call_log_id')->nullable();
        $table->unsignedBigInteger('user_id');
        $table->dateTime('remind_at');
        $table->text('notes')->nullable();
        $table->string('type')->nullable();
        $table->timestamps();
    });
}

function seedSmecallsSource(string $connection = 'smecalls_test'): void
{
    $now = now()->toDateTimeString();

    DB::connection($connection)->table('users')->insert([
        ['id' => 17, 'name' => 'Source Admin', 'email' => 'admin@smecalls.example', 'password' => 'hash', 'code' => null, 'created_at' => $now, 'updated_at' => $now],
        ['id' => 42, 'name' => 'Source Agent', 'email' => 'agent@smecalls.example', 'password' => 'hash', 'code' => 'A-007', 'created_at' => $now, 'updated_at' => $now],
    ]);

    DB::connection($connection)->table('roles')->insert([
        ['id' => 1, 'name' => 'admin', 'guard_name' => 'web', 'created_at' => $now, 'updated_at' => $now],
        ['id' => 2, 'name' => 'agent', 'guard_name' => 'web', 'created_at' => $now, 'updated_at' => $now],
    ]);

    DB::connection($connection)->table('permissions')->insert([
        ['id' => 1, 'name' => 'manage leads', 'guard_name' => 'web', 'created_at' => $now, 'updated_at' => $now],
    ]);

    DB::connection($connection)->table('role_has_permissions')->insert([
        ['role_id' => 1, 'permission_id' => 1],
    ]);

    DB::connection($connection)->table('model_has_roles')->insert([
        ['role_id' => 1, 'model_type' => User::class, 'model_id' => 17],
        ['role_id' => 2, 'model_type' => User::class, 'model_id' => 42],
    ]);

    DB::connection($connection)->table('leads')->insert([
        ['id' => 100, 'name' => 'Imported Lead', 'phone_number' => '+8801711000111', 'assigned_to_id' => 42, 'assigned_at' => $now, 'created_at' => $now, 'updated_at' => $now],
        ['id' => 101, 'name' => 'Other Lead', 'phone_number' => '+8801722000222', 'assigned_to_id' => null, 'assigned_at' => null, 'created_at' => $now, 'updated_at' => $now],
    ]);

    DB::connection($connection)->table('call_logs')->insert([
        ['id' => 500, 'lead_id' => 100, 'user_id' => 42, 'called_at' => $now, 'duration' => 60, 'outcome' => 'Successful Contact', 'notes' => 'Spoke briefly.', 'created_at' => $now, 'updated_at' => $now],
    ]);

    DB::connection($connection)->table('reminders')->insert([
        ['id' => 800, 'lead_id' => 100, 'call_log_id' => 500, 'user_id' => 42, 'remind_at' => $now, 'type' => 'callback', 'created_at' => $now, 'updated_at' => $now],
    ]);
}

it('imports every domain table and preserves IDs', function () {
    setupSmecallsSource();
    seedSmecallsSource();

    $report = app(ImportFromSmecallsAction::class)
        ->execute('smecalls_test', dryRun: false, force: false, resyncAutoIncrement: false);

    expect($report->allMatched())->toBeTrue();

    expect(User::find(17))->not->toBeNull()
        ->and(User::find(17)->email)->toBe('admin@smecalls.example')
        ->and(User::find(42)->code)->toBe('A-007');

    expect(Lead::find(100))->not->toBeNull()
        ->and(Lead::find(100)->phone_number)->toBe('+8801711000111')
        ->and(Lead::find(100)->assigned_to_id)->toBe(42);

    expect(CallLog::find(500))->not->toBeNull()
        ->and(CallLog::find(500)->lead_id)->toBe(100)
        ->and(CallLog::find(500)->user_id)->toBe(42);

    expect(Reminder::find(800))->not->toBeNull()
        ->and(Reminder::find(800)->call_log_id)->toBe(500);

    expect(Role::where('name', 'admin')->where('id', 1)->exists())->toBeTrue();
});

it('does NOT import personal_access_tokens (agents must re-login)', function () {
    setupSmecallsSource();
    seedSmecallsSource();

    app(ImportFromSmecallsAction::class)
        ->execute('smecalls_test', dryRun: false, force: false, resyncAutoIncrement: false);

    expect(DB::table('personal_access_tokens')->count())->toBe(0);
});

it('reports row counts for each table after import', function () {
    setupSmecallsSource();
    seedSmecallsSource();

    $report = app(ImportFromSmecallsAction::class)
        ->execute('smecalls_test', dryRun: false, force: false, resyncAutoIncrement: false);

    $byTable = collect($report->rows())->keyBy('table');

    expect($byTable['users']['source'])->toBe(2)
        ->and($byTable['users']['imported'])->toBe(2)
        ->and($byTable['leads']['source'])->toBe(2)
        ->and($byTable['leads']['imported'])->toBe(2)
        ->and($byTable['call_logs']['imported'])->toBe(1)
        ->and($byTable['reminders']['imported'])->toBe(1);
});

it('refuses to run when destination has data and --force is not passed', function () {
    setupSmecallsSource();
    seedSmecallsSource();

    User::factory()->create(); // dirties the destination users table

    expect(fn () => app(ImportFromSmecallsAction::class)
        ->execute('smecalls_test', dryRun: false, force: false, resyncAutoIncrement: false))
        ->toThrow(RuntimeException::class, 'already has rows');
});

it('returns counts without writing in dry-run mode', function () {
    setupSmecallsSource();
    seedSmecallsSource();

    $report = app(ImportFromSmecallsAction::class)
        ->execute('smecalls_test', dryRun: true, force: false, resyncAutoIncrement: false);

    $byTable = collect($report->rows())->keyBy('table');

    expect($byTable['leads']['source'])->toBe(2)
        ->and($byTable['leads']['imported'])->toBe(0);

    expect(Lead::count())->toBe(0);
});

it('the artisan command rejects an unknown source connection', function () {
    $this->artisan('callstats:import-from-smecalls', ['--source' => 'does-not-exist'])
        ->expectsOutputToContain('Unknown DB connection [does-not-exist]')
        ->assertFailed();
});

it('the artisan command rejects when --source is omitted', function () {
    $this->artisan('callstats:import-from-smecalls')
        ->expectsOutputToContain('Pass --source=')
        ->assertFailed();
});
