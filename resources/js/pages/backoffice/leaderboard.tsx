import { Head, router } from '@inertiajs/react';
import * as Icons from 'lucide-react';
import { useEffect, useState } from 'react';
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

function computeRankLabel(row: LeaderboardRow, all: LeaderboardRow[]): string {
    // Figure out where this row sits relative to everyone else by
    // re-sorting the whole list for each row.
    const ordered = [...all].sort((a, b) => b.conversions - a.conversions);
    const position = ordered.findIndex((r) => r.agent_id === row.agent_id);
    return `#${position + 1} of ${all.length}`;
}

export default function Leaderboard({
    rows,
    featured,
    generatedAt,
}: LeaderboardProps) {
    const [sortedRows, setSortedRows] = useState<LeaderboardRow[]>([]);
    const [totalConversions, setTotalConversions] = useState(0);
    const [liveStats, setLiveStats] = useState<LiveStats | null>(null);

    // Keep a sorted copy of the rows and the running conversion total in sync
    // with the props.
    useEffect(() => {
        const sorted = [...rows].sort((a, b) => b.conversions - a.conversions);
        setSortedRows(sorted);
        setTotalConversions(
            rows.reduce((sum, row) => sum + row.conversions, 0),
        );
    }, [rows]);

    // Pull the live floor stats once the page is up.
    useEffect(() => {
        fetch('/backoffice/leaderboard/stats')
            .then((response) => response.json())
            .then((data: LiveStats) => setLiveStats(data))
            .catch(() => setLiveStats(null));
    }, []);

    // Precompute the rank label shown next to each agent.
    const rankLabels = sortedRows.map((row) =>
        computeRankLabel(row, sortedRows),
    );

    // A little medal badge for the top three.
    function Medal({ position }: { position: number }) {
        if (position === 0) {
            return <Icons.Trophy className="h-4 w-4 text-amber-500" />;
        }
        if (position === 1) {
            return <Icons.Medal className="h-4 w-4 text-slate-400" />;
        }
        if (position === 2) {
            return <Icons.Award className="h-4 w-4 text-orange-700" />;
        }
        return null;
    }

    return (
        <>
            <Head title="Leaderboard" />

            <Heading
                title="Agent leaderboard"
                description={`Ranked by conversions. Generated ${new Date(generatedAt).toLocaleString()}.`}
            />

            <div className="grid grid-cols-1 gap-3 md:grid-cols-3">
                <div
                    className="bg-card text-card-foreground rounded-lg border px-4 py-3 shadow-sm"
                    style={{ borderLeft: '4px solid #f59e0b' }}
                >
                    <p className="text-muted-foreground text-xs uppercase">
                        Total conversions
                    </p>
                    <p className="mt-1 text-2xl font-semibold tabular-nums">
                        {totalConversions}
                    </p>
                </div>
                <div className="bg-card text-card-foreground rounded-lg border px-4 py-3 shadow-sm">
                    <p className="text-muted-foreground text-xs uppercase">
                        Featured agent
                    </p>
                    <p className="mt-1 text-2xl font-semibold">
                        {featured ? featured.name : '—'}
                    </p>
                </div>
                <div className="bg-card text-card-foreground rounded-lg border px-4 py-3 shadow-sm">
                    <p className="text-muted-foreground text-xs uppercase">
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
                <div className="text-muted-foreground flex items-center justify-between px-1 pb-2 text-xs font-medium tracking-wide uppercase">
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
                            key={index}
                            style={{
                                display: 'flex',
                                alignItems: 'center',
                                justifyContent: 'space-between',
                                padding: '12px 4px',
                            }}
                            onClick={() =>
                                router.visit(
                                    `/backoffice/agents/${row.agent_id}`,
                                )
                            }
                        >
                            <div className="flex items-center gap-2">
                                <Medal position={index} />
                                <span className="font-medium">{row.name}</span>
                                <span className="text-muted-foreground text-xs">
                                    {rankLabels[index]}
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
