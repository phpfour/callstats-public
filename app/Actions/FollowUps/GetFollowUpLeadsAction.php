<?php

declare(strict_types=1);

namespace App\Actions\FollowUps;

use App\Data\FollowUps\FollowUpQueue;
use App\Data\FollowUps\FollowUpRow;
use App\Models\Lead;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class GetFollowUpLeadsAction
{
    /**
     * Leads need follow-up when their latest call was missed/unanswered,
     * or they have a callback reminder due on or before today.
     */
    private const ATTENTION_OUTCOMES = ['Missed', 'No Answer'];

    public function execute(FollowUpQueue $queue = FollowUpQueue::All, int $perPage = 25): LengthAwarePaginator
    {
        return $this->queueQuery($queue)
            ->with(['assignedTo:id,name', 'lastCall', 'nextReminder'])
            ->withMin('reminders as next_reminder_at', 'remind_at')
            ->orderByRaw('next_reminder_at is null')
            ->orderBy('next_reminder_at')
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (Lead $lead): array => FollowUpRow::fromLead($lead)->toArray());
    }

    /**
     * Count the leads in each queue, independent of the active queue and pagination.
     *
     * @return array<string, int>
     */
    public function counts(): array
    {
        $counts = [];

        foreach (FollowUpQueue::cases() as $queue) {
            $counts[$queue->value] = $this->queueQuery($queue)->count();
        }

        return $counts;
    }

    /**
     * The base "needs follow-up" query, narrowed to the given queue.
     */
    private function queueQuery(FollowUpQueue $queue): Builder
    {
        $query = Lead::query()->where(function (Builder $query): void {
            $query
                ->whereHas('lastCall', function (Builder $call): void {
                    $call->whereIn('outcome', self::ATTENTION_OUTCOMES);
                })
                ->orWhereHas('reminders', function (Builder $reminder): void {
                    $reminder
                        ->where('type', 'callback')
                        ->whereDate('remind_at', '<=', today());
                });
        });

        return $this->applyQueueFilter($query, $queue);
    }

    /**
     * Narrow the base query to the leads belonging to the given queue.
     */
    private function applyQueueFilter(Builder $query, FollowUpQueue $queue): Builder
    {
        return match ($queue) {
            FollowUpQueue::All => $query,
            FollowUpQueue::Overdue => $query->whereHas('reminders', function (Builder $reminder): void {
                $reminder
                    ->where('type', 'callback')
                    ->whereDate('remind_at', '<', today());
            }),
            FollowUpQueue::DueToday => $query->whereHas('reminders', function (Builder $reminder): void {
                $reminder
                    ->where('type', 'callback')
                    ->whereDate('remind_at', '=', today());
            }),
            FollowUpQueue::NeedsCall => $query->whereHas('lastCall', function (Builder $call): void {
                $call->whereIn('outcome', self::ATTENTION_OUTCOMES);
            }),
        };
    }
}
