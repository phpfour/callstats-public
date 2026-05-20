import { Head } from '@inertiajs/react';
import {
    DailyCallVolumeChart,
    WeeklyCallVolumeChart,
} from '@/components/charts';
import type { DailyCallPoint, WeeklyCallPoint } from '@/components/charts';
import Heading from '@/components/heading';
import { ShowPanel } from '@/components/show-panel';
import { dashboard } from '@/routes/backoffice';

type Counters = {
    totalCalls: number;
    availableLeads: number;
    availableAgents: number;
    averageDuration: string;
    callsToday: number;
};

type DashboardProps = {
    counters: Counters;
    charts: {
        daily: DailyCallPoint[];
        weekly: WeeklyCallPoint[];
    };
};

type CounterCardProps = {
    label: string;
    value: number | string;
};

function CounterCard({ label, value }: CounterCardProps) {
    return (
        <div className="bg-card text-card-foreground rounded-lg border px-4 py-3 shadow-sm">
            <p className="text-muted-foreground text-xs tracking-wide uppercase">
                {label}
            </p>
            <p className="mt-1 text-2xl font-semibold tabular-nums">{value}</p>
        </div>
    );
}

export default function BackofficeDashboard({
    counters,
    charts,
}: DashboardProps) {
    return (
        <>
            <Head title="Dashboard" />

            <Heading
                title="Dashboard"
                description="Today's call activity and the past week at a glance."
            />

            <div className="grid grid-cols-2 gap-3 md:grid-cols-3 lg:grid-cols-5">
                <CounterCard label="Total calls" value={counters.totalCalls} />
                <CounterCard
                    label="Assigned leads"
                    value={counters.availableLeads}
                />
                <CounterCard label="Agents" value={counters.availableAgents} />
                <CounterCard
                    label="Avg duration"
                    value={counters.averageDuration}
                />
                <CounterCard label="Calls today" value={counters.callsToday} />
            </div>

            <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
                <ShowPanel
                    title="Weekly call volume"
                    description="Total calls per day for the last 7 days."
                >
                    <WeeklyCallVolumeChart data={charts.weekly} />
                </ShowPanel>

                <ShowPanel
                    title="Daily call volume"
                    description="Calls logged today, broken down by agent."
                >
                    <DailyCallVolumeChart data={charts.daily} />
                </ShowPanel>
            </div>
        </>
    );
}

BackofficeDashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
