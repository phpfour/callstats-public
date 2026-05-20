import { Head, router } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { DataTable } from '@/components/data-table';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { dashboard } from '@/routes/backoffice';

type ReportRow = {
    user_id: number;
    name: string;
    not_received: number;
    received: number;
    total: number;
    duration: number | null;
};

type AgentPerformanceProps = {
    rows: ReportRow[];
    range: { from: string; to: string };
};

const formatDuration = (seconds: number | null): string => {
    if (seconds === null) {
        return '—';
    }

    return new Date(seconds * 1000).toISOString().slice(11, 19);
};

const columns: ColumnDef<ReportRow>[] = [
    {
        accessorKey: 'name',
        header: 'Agent',
        cell: ({ row }) => (
            <span className="font-medium">{row.original.name}</span>
        ),
    },
    {
        accessorKey: 'not_received',
        header: 'Not received',
        cell: ({ row }) => (
            <span className="tabular-nums">
                {Number(row.original.not_received)}
            </span>
        ),
    },
    {
        accessorKey: 'received',
        header: 'Received',
        cell: ({ row }) => (
            <span className="tabular-nums">{Number(row.original.received)}</span>
        ),
    },
    {
        accessorKey: 'total',
        header: 'Total',
        cell: ({ row }) => (
            <span className="font-medium tabular-nums">
                {Number(row.original.total)}
            </span>
        ),
    },
    {
        accessorKey: 'duration',
        header: 'Total duration',
        cell: ({ row }) => (
            <span className="tabular-nums">
                {formatDuration(row.original.duration)}
            </span>
        ),
    },
];

export default function AgentPerformanceReport({
    rows,
    range,
}: AgentPerformanceProps) {
    const apply = (next: { from?: string; to?: string }) => {
        router.get(
            '/backoffice/reports/agent-performance',
            {
                from: 'from' in next ? next.from || undefined : range.from,
                to: 'to' in next ? next.to || undefined : range.to,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const exportUrl = `/backoffice/reports/agent-performance/export?from=${range.from}&to=${range.to}`;

    return (
        <>
            <Head title="Agent performance" />

            <div className="flex flex-col gap-4">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        title="Agent performance"
                        description="Per-agent call counts and total time on calls in the chosen window."
                    />
                    <Button variant="outline" asChild>
                        <a href={exportUrl}>Export</a>
                    </Button>
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    <Input
                        type="date"
                        aria-label="From date"
                        className="w-[160px]"
                        value={range.from}
                        onChange={(event) =>
                            apply({ from: event.target.value })
                        }
                    />
                    <span className="text-muted-foreground text-sm">–</span>
                    <Input
                        type="date"
                        aria-label="To date"
                        className="w-[160px]"
                        value={range.to}
                        onChange={(event) =>
                            apply({ to: event.target.value })
                        }
                    />
                </div>

                <DataTable
                    columns={columns}
                    data={rows}
                    empty="No calls logged in this date range."
                />
            </div>
        </>
    );
}

AgentPerformanceReport.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        {
            title: 'Reports',
            href: '/backoffice/reports/agent-performance',
        },
    ],
};
