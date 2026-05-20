<?php

declare(strict_types=1);

namespace App\Console\Commands\Fixtures;

use App\Http\Resources\CallLogResource;
use App\Http\Resources\LeadResource;
use App\Http\Resources\ReminderResource;
use App\Models\CallLog;
use App\Models\Lead;
use App\Models\Reminder;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Http\Request;

#[Signature('callstats:generate-api-fixtures {--user-id= : The agent user ID to generate fixtures for}')]
#[Description('Generate JSON contract anchor fixtures from real DB data for the mobile API.')]
class GenerateApiFixtures extends Command
{
    private const FIXTURES_DIR = 'tests/Fixtures/api';

    public function handle(): int
    {
        $userId = $this->option('user-id');

        if (! $userId) {
            $this->error('Pass --user-id=<id> for an agent user with leads/call logs/reminders.');

            return self::FAILURE;
        }

        /** @var User|null $user */
        $user = User::find($userId);

        if (! $user) {
            $this->error("User {$userId} not found.");

            return self::FAILURE;
        }

        $this->info(sprintf('Generating fixtures from user #%d (%s).', $user->id, $user->email));

        $dir = base_path(self::FIXTURES_DIR);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $this->writeMeFixture($user, $dir);
        $this->writeLoginFixture($user, $dir);
        $this->writeLeadsFixture($user, $dir);
        $this->writeCallLogsFixture($user, $dir);
        $this->writeCallLogStoreFixture($user, $dir);
        $this->writeRemindersFixture($user, $dir);
        $this->writeReminderStoreFixture($user, $dir);

        $this->info('All fixtures written to '.self::FIXTURES_DIR.'/');

        return self::SUCCESS;
    }

    private function writeMeFixture(User $user, string $dir): void
    {
        $payload = $user->toArray();
        $this->dump($dir.'/me.json', $payload);
    }

    private function writeLoginFixture(User $user, string $dir): void
    {
        $payload = [
            'token' => '<plain-text-token-redacted>',
            'user' => $user->only('id', 'name', 'email', 'created_at', 'updated_at'),
        ];
        $this->dump($dir.'/login.json', $payload);
    }

    private function writeLeadsFixture(User $user, string $dir): void
    {
        $leads = Lead::where('assigned_to_id', $user->id)
            ->with('lastCall')
            ->orderByDesc('assigned_at')
            ->limit(3)
            ->get();

        $payload = LeadResource::collection($leads)
            ->response(Request::create('/api/leads', 'GET'))
            ->getData(true);

        $this->dump($dir.'/leads.json', $payload);
    }

    private function writeCallLogsFixture(User $user, string $dir): void
    {
        $callLogs = CallLog::query()
            ->with('callbackReminder')
            ->where('user_id', $user->id)
            ->orderByDesc('called_at')
            ->limit(3)
            ->get();

        $payload = CallLogResource::collection($callLogs)
            ->response(Request::create('/api/call-logs', 'GET'))
            ->getData(true);

        $this->dump($dir.'/call-logs-index.json', $payload);
    }

    private function writeCallLogStoreFixture(User $user, string $dir): void
    {
        /** @var CallLog|null $callLog */
        $callLog = CallLog::query()
            ->with('callbackReminder')
            ->where('user_id', $user->id)
            ->whereHas('callbackReminder')
            ->latest('called_at')
            ->first();

        if (! $callLog) {
            $callLog = CallLog::query()
                ->with('callbackReminder')
                ->where('user_id', $user->id)
                ->latest('called_at')
                ->first();
        }

        if (! $callLog) {
            $this->warn('No call log found for user; skipping call-log store fixture.');

            return;
        }

        $payload = (new CallLogResource($callLog))
            ->response(Request::create('/api/call-logs', 'POST'))
            ->getData(true);

        $this->dump($dir.'/call-logs-store.json', $payload);
    }

    private function writeRemindersFixture(User $user, string $dir): void
    {
        $reminders = Reminder::where('user_id', $user->id)
            ->orderByDesc('remind_at')
            ->limit(3)
            ->get();

        $payload = ReminderResource::collection($reminders)
            ->response(Request::create('/api/reminders', 'GET'))
            ->getData(true);

        $this->dump($dir.'/reminders-index.json', $payload);
    }

    private function writeReminderStoreFixture(User $user, string $dir): void
    {
        /** @var Reminder|null $reminder */
        $reminder = Reminder::where('user_id', $user->id)
            ->orderByDesc('remind_at')
            ->first();

        if (! $reminder) {
            $this->warn('No reminder found for user; skipping reminder store fixture.');

            return;
        }

        $payload = (new ReminderResource($reminder))
            ->response(Request::create('/api/reminders', 'POST'))
            ->getData(true);

        $this->dump($dir.'/reminders-store.json', $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function dump(string $path, array $payload): void
    {
        file_put_contents(
            $path,
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).PHP_EOL,
        );

        $this->line("  wrote {$path}");
    }
}
