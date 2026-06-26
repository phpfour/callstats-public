<?php

declare(strict_types=1);

namespace App\Data\FollowUps;

enum FollowUpQueue: string
{
    case All = 'all';
    case Overdue = 'overdue';
    case DueToday = 'due-today';
    case NeedsCall = 'needs-call';

    /**
     * Resolve a queue from a request value, falling back to the default
     * All queue when the value is missing or unrecognized.
     */
    public static function fromRequest(?string $value): self
    {
        return self::tryFrom($value ?? '') ?? self::All;
    }

    public function label(): string
    {
        return match ($this) {
            self::All => 'All',
            self::Overdue => 'Overdue',
            self::DueToday => 'Due today',
            self::NeedsCall => 'Needs call',
        };
    }

    /**
     * The queues to render as tabs, in display order.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $queue): array => [
                'value' => $queue->value,
                'label' => $queue->label(),
            ],
            self::cases(),
        );
    }
}
