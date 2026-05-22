import { Head, Link } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { DataTable } from '@/components/data-table';
import Heading from '@/components/heading';
import { KpiProgressBar } from '@/components/kpi-progress-bar';
import { ShowPanel } from '@/components/show-panel';
import { dashboard } from '@/routes/backoffice';

type RecentCall = {
    id: number;
    called_at: string | null;
    duration: number | null;
    duration_hms: string | null;
    outcome: string | null;
    notes: string | null;
    lead: { id: number; name: string; phone_number: string } | null;
};

type AgentDetailProps = {
    agent: {
        id: number;
        name: string;
        code: string | null;
        targets: {
            dailyCalls: number | null;
            conversionRate: number | null;
        };
        today: {
            calls: number;
            conversions: number;
            conversionRate: number | null;
        };
        last30Days: {
            calls: number;
            talkTime: number;
            conversions: number;
            conversionRate: number | null;
            avgDuration: number;
        };
        recentCalls: RecentCall[];
    };
};

function formatHms(seconds: number): string {
    const h = Math.floor(seconds / 3600);
    const m = Math.floor((seconds % 3600) / 60);
    const s = seconds % 60;
    return [h, m, s].map((n) => String(n).padStart(2, '0')).join(':');
}

function formatDate(iso: string | null): string {
    if (!iso) {
        return '—';
    }
    return new Date(iso).toLocaleString();
}

type StatCardProps = { label: string; value: string | number };

function StatCard({ label, value }: StatCardProps) {
    return (
        <div className="bg-card text-card-foreground rounded-lg border px-4 py-3 shadow-sm">
            <p className="text-muted-foreground text-xs tracking-wide uppercase">
                {label}
            </p>
            <p className="mt-1 text-2xl font-semibold tabular-nums">{value}</p>
        </div>
    );
}

export default function AgentShow({ agent }: AgentDetailProps) {
    const { targets, today, last30Days, recentCalls } = agent;
    const hasDailyCallBar = targets.dailyCalls !== null;
    const hasConversionBar = targets.conversionRate !== null;
    const showTargetsPanel = hasDailyCallBar || hasConversionBar;

    const columns: ColumnDef<RecentCall>[] = [
        {
            accessorKey: 'called_at',
            header: 'When',
            cell: ({ row }) => formatDate(row.original.called_at),
        },
        {
            id: 'lead',
            header: 'Lead',
            cell: ({ row }) => {
                const lead = row.original.lead;
                if (!lead) {
                    return '—';
                }
                return (
                    <Link
                        href={`/backoffice/leads/${lead.id}`}
                        className="hover:underline"
                    >
                        {lead.name}
                    </Link>
                );
            },
        },
        {
            accessorKey: 'outcome',
            header: 'Outcome',
            cell: ({ row }) => row.original.outcome ?? '—',
        },
        {
            accessorKey: 'duration_hms',
            header: 'Duration',
            cell: ({ row }) => row.original.duration_hms ?? '—',
        },
    ];

    return (
        <>
            <Head title={agent.name} />

            <Heading
                title={agent.name}
                description={
                    agent.code
                        ? `Agent code ${agent.code}.`
                        : 'Agent performance overview.'
                }
            />

            {showTargetsPanel && (
                <ShowPanel
                    title="Today vs targets"
                    description="Progress against the daily goals configured on this agent."
                >
                    <div className="space-y-4">
                        {hasDailyCallBar && (
                            <KpiProgressBar
                                label="Daily calls"
                                actual={today.calls}
                                target={targets.dailyCalls ?? 0}
                            />
                        )}
                        {hasConversionBar && (
                            <KpiProgressBar
                                label="Conversion rate"
                                actual={today.conversionRate ?? 0}
                                target={targets.conversionRate ?? 0}
                                valueSuffix="%"
                            />
                        )}
                    </div>
                </ShowPanel>
            )}

            <ShowPanel
                title="Last 30 days"
                description="Totals across the previous 30 days (today inclusive)."
            >
                <div className="grid grid-cols-2 gap-3 md:grid-cols-4">
                    <StatCard label="Calls" value={last30Days.calls} />
                    <StatCard
                        label="Talk time"
                        value={formatHms(last30Days.talkTime)}
                    />
                    <StatCard
                        label="Conversion rate"
                        value={
                            last30Days.conversionRate === null
                                ? '—'
                                : `${last30Days.conversionRate}%`
                        }
                    />
                    <StatCard
                        label="Avg duration"
                        value={formatHms(last30Days.avgDuration)}
                    />
                </div>
            </ShowPanel>

            <ShowPanel
                title="Recent calls"
                description="The 25 most recent calls logged by this agent."
            >
                <DataTable
                    columns={columns}
                    data={recentCalls}
                    empty="No calls logged yet."
                />
            </ShowPanel>
        </>
    );
}

AgentShow.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Agents', href: '#' },
    ],
};
