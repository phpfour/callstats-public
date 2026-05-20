import { CircleAlertIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

type FormFieldProps = {
    label: string;
    htmlFor?: string;
    required?: boolean;
    error?: string;
    description?: string;
    children: ReactNode;
    className?: string;
};

export function FormField({
    label,
    htmlFor,
    required,
    error,
    description,
    children,
    className,
}: FormFieldProps) {
    return (
        <div className={cn('grid gap-2', className)}>
            <Label
                htmlFor={htmlFor}
                className="flex items-center gap-1 text-sm font-medium"
            >
                {label}
                {required ? (
                    <span
                        aria-hidden
                        className="text-muted-foreground/70 text-xs"
                        title="Required"
                    >
                        •
                    </span>
                ) : null}
            </Label>

            {description ? (
                <p className="text-muted-foreground text-xs leading-snug">
                    {description}
                </p>
            ) : null}

            {children}

            {error ? (
                <p
                    role="alert"
                    className="text-destructive flex items-center gap-1.5 text-xs"
                >
                    <CircleAlertIcon className="size-3.5 shrink-0" />
                    {error}
                </p>
            ) : null}
        </div>
    );
}
