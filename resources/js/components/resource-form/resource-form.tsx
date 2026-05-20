import { Link } from '@inertiajs/react';
import type { FormEvent, ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';

type ResourceFormProps = {
    onSubmit: (event: FormEvent<HTMLFormElement>) => void;
    processing: boolean;
    submitLabel?: string;
    cancelHref?: string;
    children: ReactNode;
    className?: string;
};

export function ResourceForm({
    onSubmit,
    processing,
    submitLabel = 'Save',
    cancelHref,
    children,
    className,
}: ResourceFormProps) {
    return (
        <form onSubmit={onSubmit} className={cn('space-y-8', className)}>
            <div className="space-y-8">{children}</div>

            <div className="flex items-center justify-end gap-2 border-t pt-4">
                {cancelHref ? (
                    <Button type="button" variant="ghost" asChild>
                        <Link href={cancelHref}>Cancel</Link>
                    </Button>
                ) : null}
                <Button type="submit" disabled={processing}>
                    {processing ? <Spinner className="size-4" /> : null}
                    {submitLabel}
                </Button>
            </div>
        </form>
    );
}

type ResourceFormSectionProps = {
    title: string;
    description?: string;
    children: ReactNode;
    className?: string;
};

/**
 * A titled group of related fields. Use to break long forms into scannable
 * blocks — e.g., Contact / Details / Assignment on the Lead form.
 */
export function ResourceFormSection({
    title,
    description,
    children,
    className,
}: ResourceFormSectionProps) {
    return (
        <section className={cn('space-y-4', className)}>
            <header className="space-y-1">
                <h3 className="text-sm font-semibold tracking-tight">
                    {title}
                </h3>
                {description ? (
                    <p className="text-muted-foreground text-xs leading-relaxed">
                        {description}
                    </p>
                ) : null}
            </header>
            <div className="grid gap-5">{children}</div>
        </section>
    );
}
