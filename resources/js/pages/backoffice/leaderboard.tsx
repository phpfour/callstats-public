import { Head, router } from '@inertiajs/react';
import { Award, Medal as MedalIcon, Trophy } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import Heading from '@/components/heading';
import { ShowPanel } from '@/components/show-panel';
import { dashboard } from '@/routes/backoffice';

type LeaderboardRow = {
    agent_id: number;
    name: string;
    days: number;
    total_calls: number;
    connected_calls: number;
    conversions: number;
    conversion_rate: number;
    talk_time_seconds: number;
    interested_days: number;
    flagged_days: number;
};

type Featured = {
    name: string;
    conversions: number;
} | null;

type LeaderboardProps = {
    rows: LeaderboardRow[];
    featured: Featured;
    generatedAt: string;
};

type LiveStats = {
    onlineAgents: number;
    callsInProgress: number;
};

function formatTalkTime(seconds: number): string {
    const h = Math.floor(seconds / 3600);
    const m = Math.floor((seconds % 3600) / 60);

    return `${h}h ${m}m`;
}

// A little medal badge for the top three.
function Medal({ position }: { position: number }) {
    if (position === 0) {
        return <Trophy className="h-4 w-4 text-amber-500" />;
    }

    if (position === 1) {
        return <MedalIcon className="h-4 w-4 text-slate-400" />;
    }

    if (position === 2) {
        return <Award className="h-4 w-4 text-orange-700" />;
    }

    return null;
}

export default function Leaderboard({
    rows,
    featured,
    generatedAt,
}: LeaderboardProps) {
    const [liveStats, setLiveStats] = useState<LiveStats | null>(null);

    // Derived straight from props during render — no effect, no empty first paint.
    const sortedRows = useMemo(
        () => [...rows].sort((a, b) => b.conversions - a.conversions),
        [rows],
    );
    const totalConversions = useMemo(
        () => rows.reduce((sum, row) => sum + row.conversions, 0),
        [rows],
    );

    // Pull the live floor stats once the page is up; abort on unmount.
    useEffect(() => {
        const controller = new AbortController();

        fetch('/backoffice/leaderboard/stats', { signal: controller.signal })
            .then((response) => response.json())
            .then((data: LiveStats) => setLiveStats(data))
            .catch((error: unknown) => {
                if (
                    !(
                        error instanceof DOMException &&
                        error.name === 'AbortError'
                    )
                ) {
                    setLiveStats(null);
                }
            });

        return () => controller.abort();
    }, []);

    return (
        <>
            <Head title="Leaderboard" />

            <Heading
                title="Agent leaderboard"
                description={`Ranked by conversions. Generated ${new Date(generatedAt).toLocaleString()}.`}
            />

            <div className="grid grid-cols-1 gap-3 md:grid-cols-3">
                <div className="rounded-lg border border-l-4 border-l-amber-500 bg-card px-4 py-3 text-card-foreground shadow-sm">
                    <p className="text-xs text-muted-foreground uppercase">
                        Total conversions
                    </p>
                    <p className="mt-1 text-2xl font-semibold tabular-nums">
                        {totalConversions}
                    </p>
                </div>
                <div className="rounded-lg border bg-card px-4 py-3 text-card-foreground shadow-sm">
                    <p className="text-xs text-muted-foreground uppercase">
                        Featured agent
                    </p>
                    <p className="mt-1 text-2xl font-semibold">
                        {featured ? featured.name : '—'}
                    </p>
                </div>
                <div className="rounded-lg border bg-card px-4 py-3 text-card-foreground shadow-sm">
                    <p className="text-xs text-muted-foreground uppercase">
                        Online now
                    </p>
                    <p className="mt-1 text-2xl font-semibold">
                        {liveStats ? liveStats.onlineAgents : '…'}
                    </p>
                </div>
            </div>

            <ShowPanel
                title="Standings"
                description="Every agent ranked by conversions over the selected range."
            >
                <div className="flex items-center justify-between px-1 pb-2 text-xs font-medium tracking-wide text-muted-foreground uppercase">
                    <span>Agent</span>
                    <div className="flex items-center gap-6 tabular-nums">
                        <span className="w-16 text-center">Calls</span>
                        <span className="w-20 text-center">Conversions</span>
                        <span className="w-20 text-center">Conv. rate</span>
                        <span className="w-20 text-center">Talk time</span>
                    </div>
                </div>
                <div className="divide-y">
                    {sortedRows.map((row, index) => (
                        <div
                            key={row.agent_id}
                            className="flex cursor-pointer items-center justify-between px-1 py-3"
                            onClick={() =>
                                router.visit(
                                    `/backoffice/agents/${row.agent_id}`,
                                )
                            }
                        >
                            <div className="flex items-center gap-2">
                                <Medal position={index} />
                                <span className="font-medium">{row.name}</span>
                                <span className="text-xs text-muted-foreground">
                                    #{index + 1} of {sortedRows.length}
                                </span>
                            </div>
                            <div className="flex items-center gap-6 text-sm tabular-nums">
                                <span className="w-16 text-center">
                                    {row.total_calls}
                                </span>
                                <span className="w-20 text-center">
                                    {row.conversions}
                                </span>
                                <span className="w-20 text-center">
                                    {row.conversion_rate}%
                                </span>
                                <span className="w-20 text-center">
                                    {formatTalkTime(row.talk_time_seconds)}
                                </span>
                            </div>
                        </div>
                    ))}
                </div>
            </ShowPanel>
        </>
    );
}

Leaderboard.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Leaderboard', href: '#' },
    ],
};
