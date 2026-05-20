import { Head, Link, router } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { DataTable, DataTablePagination } from '@/components/data-table';
import type { PaginatorMeta } from '@/components/data-table';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { dashboard } from '@/routes/backoffice';

type RelatedUser = { id: number; name: string };
type RelatedLead = { id: number; name: string; phone_number: string };

type CallLog = {
    id: number;
    called_at: string;
    duration: number | null;
    outcome: string | null;
    notes: string | null;
    lead: RelatedLead | null;
    user: RelatedUser | null;
};

type PaginatedCallLogs = PaginatorMeta & { data: CallLog[] };

type Filters = {
    lead_id: number | null;
    user_id: number | null;
    outcome: string | null;
    called_from: string | null;
    called_to: string | null;
};

type CallLogsIndexProps = {
    callLogs: PaginatedCallLogs;
    filters: Filters;
    sort: { column: string | null; direction: string };
    agents: RelatedUser[];
    outcomes: string[];
};

const ALL = 'all';

const formatDateTime = (value: string | null): string => {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleString(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const formatDuration = (seconds: number | null): string => {
    if (seconds === null) {
        return '—';
    }

    return new Date(seconds * 1000).toISOString().slice(11, 19);
};

const columns: ColumnDef<CallLog>[] = [
    {
        accessorKey: 'called_at',
        header: 'Called at',
        cell: ({ row }) => (
            <Link
                href={`/backoffice/call-logs/${row.original.id}`}
                className="font-medium hover:underline"
            >
                {formatDateTime(row.original.called_at)}
            </Link>
        ),
    },
    {
        id: 'lead',
        header: 'Lead',
        cell: ({ row }) =>
            row.original.lead ? (
                <Link
                    href={`/backoffice/leads/${row.original.lead.id}`}
                    className="hover:underline"
                >
                    {row.original.lead.name}
                </Link>
            ) : (
                '—'
            ),
    },
    {
        id: 'user',
        header: 'Agent',
        cell: ({ row }) => row.original.user?.name ?? '—',
    },
    {
        accessorKey: 'outcome',
        header: 'Outcome',
        cell: ({ row }) => row.original.outcome ?? '—',
    },
    {
        accessorKey: 'duration',
        header: 'Duration',
        cell: ({ row }) => formatDuration(row.original.duration),
    },
];

export default function CallLogsIndex({
    callLogs,
    filters,
    agents,
    outcomes,
}: CallLogsIndexProps) {
    const hasActiveFilters =
        filters.user_id !== null ||
        filters.outcome !== null ||
        filters.called_from !== null ||
        filters.called_to !== null;

    const apply = (next: Partial<Record<keyof Filters, string | undefined>>) => {
        router.get(
            '/backoffice/call-logs',
            {
                user_id:
                    'user_id' in next
                        ? next.user_id
                        : (filters.user_id?.toString() ?? undefined),
                outcome:
                    'outcome' in next
                        ? next.outcome
                        : (filters.outcome ?? undefined),
                called_from:
                    'called_from' in next
                        ? next.called_from
                        : (filters.called_from ?? undefined),
                called_to:
                    'called_to' in next
                        ? next.called_to
                        : (filters.called_to ?? undefined),
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const reset = () => {
        router.get(
            '/backoffice/call-logs',
            {},
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const exportUrl = (() => {
        const params = new URLSearchParams();

        if (filters.user_id) {
            params.set('user_id', filters.user_id.toString());
        }

        if (filters.outcome) {
            params.set('outcome', filters.outcome);
        }

        if (filters.called_from) {
            params.set('called_from', filters.called_from);
        }

        if (filters.called_to) {
            params.set('called_to', filters.called_to);
        }

        const qs = params.toString();

        return qs
            ? `/backoffice/call-logs/export?${qs}`
            : '/backoffice/call-logs/export';
    })();

    return (
        <>
            <Head title="Call logs" />

            <div className="flex flex-col gap-4">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        title="Call logs"
                        description="Every logged call. Filter by agent, outcome, or date range."
                    />
                    <Button variant="outline" asChild>
                        <a href={exportUrl}>Export</a>
                    </Button>
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    <Select
                        value={filters.user_id?.toString() ?? ALL}
                        onValueChange={(value) =>
                            apply({
                                user_id: value === ALL ? undefined : value,
                            })
                        }
                    >
                        <SelectTrigger className="w-[180px]">
                            <SelectValue placeholder="All agents" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ALL}>All agents</SelectItem>
                            {agents.map((agent) => (
                                <SelectItem
                                    key={agent.id}
                                    value={agent.id.toString()}
                                >
                                    {agent.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    <Select
                        value={filters.outcome ?? ALL}
                        onValueChange={(value) =>
                            apply({
                                outcome: value === ALL ? undefined : value,
                            })
                        }
                    >
                        <SelectTrigger className="w-[200px]">
                            <SelectValue placeholder="All outcomes" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ALL}>All outcomes</SelectItem>
                            {outcomes.map((outcome) => (
                                <SelectItem key={outcome} value={outcome}>
                                    {outcome}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    <Input
                        type="date"
                        aria-label="From date"
                        className="w-[160px]"
                        value={filters.called_from ?? ''}
                        onChange={(event) =>
                            apply({
                                called_from: event.target.value || undefined,
                            })
                        }
                    />
                    <span className="text-muted-foreground text-sm">–</span>
                    <Input
                        type="date"
                        aria-label="To date"
                        className="w-[160px]"
                        value={filters.called_to ?? ''}
                        onChange={(event) =>
                            apply({
                                called_to: event.target.value || undefined,
                            })
                        }
                    />

                    {hasActiveFilters ? (
                        <Button variant="ghost" size="sm" onClick={reset}>
                            Reset
                        </Button>
                    ) : null}
                </div>

                <DataTable columns={columns} data={callLogs.data} />
                <DataTablePagination meta={callLogs} />
            </div>
        </>
    );
}

CallLogsIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Call logs', href: '/backoffice/call-logs' },
    ],
};
