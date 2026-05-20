import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

type ShowPanelProps = {
    title: string;
    description?: string;
    action?: ReactNode;
    children: ReactNode;
    className?: string;
    bodyClassName?: string;
};

export function ShowPanel({
    title,
    description,
    action,
    children,
    className,
    bodyClassName,
}: ShowPanelProps) {
    return (
        <section
            className={cn(
                'bg-card text-card-foreground rounded-lg border shadow-sm',
                className,
            )}
        >
            <header className="flex items-start justify-between gap-4 border-b px-5 py-4">
                <div className="space-y-1">
                    <h2 className="text-base font-semibold">{title}</h2>
                    {description ? (
                        <p className="text-muted-foreground text-sm">
                            {description}
                        </p>
                    ) : null}
                </div>
                {action ? <div className="shrink-0">{action}</div> : null}
            </header>
            <div className={cn('px-5 py-5', bodyClassName)}>{children}</div>
        </section>
    );
}

type ShowFieldProps = {
    label: string;
    value?: ReactNode;
    children?: ReactNode;
    className?: string;
};

export function ShowField({ label, value, children, className }: ShowFieldProps) {
    return (
        <div className={cn('grid gap-1', className)}>
            <dt className="text-muted-foreground text-sm">{label}</dt>
            <dd className="text-sm font-medium">{children ?? value ?? '—'}</dd>
        </div>
    );
}
