import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';

type BulkAction = {
    label: string;
    icon?: ReactNode;
    onClick: () => void;
    variant?: 'default' | 'secondary' | 'destructive' | 'outline' | 'ghost';
    disabled?: boolean;
};

type BulkActionBarProps = {
    selectedCount: number;
    onClear: () => void;
    actions: BulkAction[];
    label?: (count: number) => string;
};

export function BulkActionBar({
    selectedCount,
    onClear,
    actions,
    label = (count) =>
        count === 1 ? '1 row selected' : `${count} rows selected`,
}: BulkActionBarProps) {
    if (selectedCount === 0) {
        return null;
    }

    return (
        <div className="bg-card flex items-center justify-between gap-3 rounded-md border px-3 py-2 shadow-sm">
            <p className="text-sm font-medium">{label(selectedCount)}</p>
            <div className="flex items-center gap-2">
                {actions.map((action) => (
                    <Button
                        key={action.label}
                        size="sm"
                        variant={action.variant ?? 'default'}
                        disabled={action.disabled}
                        onClick={action.onClick}
                    >
                        {action.icon}
                        {action.label}
                    </Button>
                ))}
                <Button size="sm" variant="ghost" onClick={onClear}>
                    Clear
                </Button>
            </div>
        </div>
    );
}
