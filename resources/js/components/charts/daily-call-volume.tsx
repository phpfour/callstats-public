import {
    Bar,
    BarChart,
    CartesianGrid,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';

export type DailyCallPoint = {
    agent: string;
    calls: number;
};

type DailyCallVolumeChartProps = {
    data: DailyCallPoint[];
    height?: number;
};

export function DailyCallVolumeChart({
    data,
    height = 240,
}: DailyCallVolumeChartProps) {
    return (
        <ResponsiveContainer width="100%" height={height}>
            <BarChart
                data={data}
                margin={{ top: 8, right: 8, bottom: 8, left: 0 }}
            >
                <CartesianGrid
                    strokeDasharray="3 3"
                    vertical={false}
                    stroke="currentColor"
                    className="text-border"
                />
                <XAxis
                    dataKey="agent"
                    tickLine={false}
                    axisLine={false}
                    interval={0}
                    angle={-25}
                    height={48}
                    textAnchor="end"
                    className="fill-muted-foreground text-xs"
                />
                <YAxis
                    allowDecimals={false}
                    tickLine={false}
                    axisLine={false}
                    width={32}
                    className="fill-muted-foreground text-xs"
                />
                <Tooltip
                    cursor={{ fillOpacity: 0.06 }}
                    contentStyle={{
                        borderRadius: 8,
                        border: '1px solid var(--border)',
                        background: 'var(--card)',
                        color: 'var(--card-foreground)',
                        fontSize: 12,
                    }}
                />
                <Bar
                    dataKey="calls"
                    fill="var(--primary)"
                    radius={[4, 4, 0, 0]}
                    maxBarSize={48}
                />
            </BarChart>
        </ResponsiveContainer>
    );
}
