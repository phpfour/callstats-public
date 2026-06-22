import { Cell, Legend, Pie, PieChart, ResponsiveContainer, Tooltip } from 'recharts';

export type OutcomeSlice = {
    outcome: string;
    calls: number;
};

type OutcomeBreakdownChartProps = {
    data: OutcomeSlice[];
    height?: number;
};

const COLORS = [
    'var(--chart-1)',
    'var(--chart-2)',
    'var(--chart-3)',
    'var(--chart-4)',
    'var(--chart-5)',
];

export function OutcomeBreakdownChart({
    data,
    height = 240,
}: OutcomeBreakdownChartProps) {
    if (data.length === 0) {
        return (
            <div
                className="text-muted-foreground flex items-center justify-center text-sm"
                style={{ height }}
            >
                No calls logged today yet.
            </div>
        );
    }

    return (
        <ResponsiveContainer width="100%" height={height}>
            <PieChart>
                <Pie
                    data={data}
                    dataKey="calls"
                    nameKey="outcome"
                    innerRadius={56}
                    outerRadius={88}
                    paddingAngle={2}
                    stroke="var(--card)"
                >
                    {data.map((_, index) => (
                        <Cell
                            key={index}
                            fill={COLORS[index % COLORS.length]}
                        />
                    ))}
                </Pie>
                <Tooltip
                    contentStyle={{
                        borderRadius: 8,
                        border: '1px solid var(--border)',
                        background: 'var(--card)',
                        color: 'var(--card-foreground)',
                        fontSize: 12,
                    }}
                />
                <Legend
                    verticalAlign="bottom"
                    iconType="circle"
                    wrapperStyle={{ fontSize: 12 }}
                />
            </PieChart>
        </ResponsiveContainer>
    );
}
