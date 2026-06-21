import { Head, Link } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { Badge } from '@/components/ui/badge';
import { DataTable, DataTablePagination } from '@/components/data-table';
import type { PaginatorMeta } from '@/components/data-table';
import Heading from '@/components/heading';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes/backoffice';

type FollowUpRow = {
    lead_id: number;
    name: string;
    phone_number: string;
    assigned_agent: string | null;
    last_outcome: string | null;
    last_called_at: string | null;
    next_reminder_at: string | null;
    last_call_id: number | null;
};

type QueueOption = {
    value: string;
    label: string;
};

type PaginatedFollowUps = PaginatorMeta & { data: FollowUpRow[] };

type FollowUpsIndexProps = {
    leads: PaginatedFollowUps;
    activeQueue: string;
    queues: QueueOption[];
    counts: Record<string, number>;
};

const queueHref = (value: string): string =>
    value === 'all'
        ? '/backoffice/follow-ups'
        : `/backoffice/follow-ups?queue=${value}`;

const formatDate = (value: string | null): string => {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleDateString(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
};

const rowHref = (row: FollowUpRow): string =>
    row.last_call_id
        ? `/backoffice/call-logs/${row.last_call_id}`
        : `/backoffice/leads/${row.lead_id}`;

const columns: ColumnDef<FollowUpRow>[] = [
    {
        accessorKey: 'name',
        header: 'Lead',
        cell: ({ row }) => (
            <Link
                href={rowHref(row.original)}
                className="font-medium hover:underline"
            >
                {row.original.name}
            </Link>
        ),
    },
    { accessorKey: 'phone_number', header: 'Phone' },
    {
        id: 'assigned_agent',
        header: 'Assigned agent',
        cell: ({ row }) => row.original.assigned_agent ?? '—',
    },
    {
        id: 'last_call',
        header: 'Last call',
        cell: ({ row }) => {
            const { last_outcome, last_called_at } = row.original;

            if (!last_outcome && !last_called_at) {
                return '—';
            }

            return (
                <span>
                    {last_outcome ?? '—'}
                    <span className="text-muted-foreground">
                        {' · '}
                        {formatDate(last_called_at)}
                    </span>
                </span>
            );
        },
    },
    {
        id: 'next_reminder',
        header: 'Next reminder',
        cell: ({ row }) => formatDate(row.original.next_reminder_at),
    },
];

export default function FollowUpsIndex({
    leads,
    activeQueue,
    queues,
    counts,
}: FollowUpsIndexProps) {
    return (
        <>
            <Head title="Follow-ups" />

            <div className="flex flex-col gap-4">
                <Heading
                    title="Follow-ups"
                    description="Leads needing attention: a missed or unanswered last call, or a callback reminder due today or earlier."
                />

                <nav className="flex flex-wrap gap-1 border-b">
                    {queues.map((queue) => {
                        const isActive = queue.value === activeQueue;

                        return (
                            <Link
                                key={queue.value}
                                href={queueHref(queue.value)}
                                className={cn(
                                    '-mb-px flex items-center gap-2 border-b-2 px-3 py-2 text-sm font-medium transition-colors',
                                    isActive
                                        ? 'border-primary text-foreground'
                                        : 'border-transparent text-muted-foreground hover:text-foreground',
                                )}
                            >
                                {queue.label}
                                <Badge
                                    variant={isActive ? 'default' : 'secondary'}
                                >
                                    {counts[queue.value] ?? 0}
                                </Badge>
                            </Link>
                        );
                    })}
                </nav>

                <DataTable
                    columns={columns}
                    data={leads.data}
                    empty="No leads need follow-up right now."
                />

                <DataTablePagination meta={leads} />
            </div>
        </>
    );
}

FollowUpsIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Follow-ups', href: '/backoffice/follow-ups' },
    ],
};
