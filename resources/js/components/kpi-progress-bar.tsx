type KpiProgressBarProps = {
    label: string;
    actual: number;
    target: number;
    valueSuffix?: string;
};

export function KpiProgressBar({
    label,
    actual,
    target,
    valueSuffix = '',
}: KpiProgressBarProps) {
    const safeTarget = Math.max(target, 1);
    const percentage = Math.min(
        100,
        Math.max(0, Math.round((actual / safeTarget) * 100)),
    );
    const reached = actual >= target;

    return (
        <div>
            <div className="flex items-center justify-between gap-2">
                <p className="text-sm font-medium">{label}</p>
                <p className="text-muted-foreground text-xs tabular-nums">
                    {actual}
                    {valueSuffix} / {target}
                    {valueSuffix}{' '}
                    <span className="text-foreground/70 ml-1">
                        ({percentage}%)
                    </span>
                </p>
            </div>
            <div
                className="bg-muted mt-1.5 h-2 overflow-hidden rounded-full"
                role="progressbar"
                aria-label={`${label} progress`}
                aria-valuenow={actual}
                aria-valuemin={0}
                aria-valuemax={target}
            >
                <div
                    className={`h-full rounded-full transition-[width] ${
                        reached ? 'bg-emerald-500' : 'bg-primary'
                    }`}
                    style={{ width: `${percentage}%` }}
                />
            </div>
        </div>
    );
}
