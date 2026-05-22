export type TopAgentRow = {
    name: string;
    calls: number;
    talkTime: string;
};

type TopAgentsListProps = {
    data: TopAgentRow[];
};

export function TopAgentsList({ data }: TopAgentsListProps) {
    if (data.length === 0) {
        return (
            <p className="text-muted-foreground text-sm">
                No agent activity in the last 7 days.
            </p>
        );
    }

    const maxCalls = Math.max(...data.map((row) => row.calls), 1);

    return (
        <ol className="space-y-3">
            {data.map((row, index) => {
                const percentage = Math.round((row.calls / maxCalls) * 100);

                return (
                    <li key={row.name} className="flex items-center gap-3">
                        <span className="text-muted-foreground w-5 text-right text-sm font-medium tabular-nums">
                            {index + 1}
                        </span>
                        <div className="min-w-0 flex-1">
                            <div className="flex items-center justify-between gap-2">
                                <p className="truncate text-sm font-medium">
                                    {row.name}
                                </p>
                                <p className="text-muted-foreground shrink-0 text-xs tabular-nums">
                                    {row.calls} calls · {row.talkTime}
                                </p>
                            </div>
                            <div
                                className="bg-muted mt-1.5 h-2 overflow-hidden rounded-full"
                                role="progressbar"
                                aria-label={`${row.name} call volume`}
                                aria-valuenow={row.calls}
                                aria-valuemin={0}
                                aria-valuemax={maxCalls}
                            >
                                <div
                                    className="bg-primary h-full rounded-full transition-[width]"
                                    style={{ width: `${percentage}%` }}
                                />
                            </div>
                        </div>
                    </li>
                );
            })}
        </ol>
    );
}
