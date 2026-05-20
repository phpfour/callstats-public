import { useForm } from '@inertiajs/react';
import type { ChangeEvent, FormEvent, ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

type ImportDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: string;
    description?: string;
    endpoint: string;
    accept?: string;
    fieldName?: string;
    submitLabel?: string;
    children?: ReactNode;
};

type ImportFormShape = {
    file: File | null;
};

export function ImportDialog({
    open,
    onOpenChange,
    title,
    description,
    endpoint,
    accept = '.xlsx,.xls,.csv',
    fieldName = 'file',
    submitLabel = 'Import',
    children,
}: ImportDialogProps) {
    const form = useForm<ImportFormShape>({ file: null });

    const handleFileChange = (event: ChangeEvent<HTMLInputElement>) => {
        const file = event.target.files?.[0] ?? null;
        form.setData('file', file);
    };

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.post(endpoint, {
            forceFormData: true,
            onSuccess: () => {
                form.reset();
                onOpenChange(false);
            },
        });
    };

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                if (!next) {
                    form.reset();
                    form.clearErrors();
                }

                onOpenChange(next);
            }}
        >
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    {description ? (
                        <DialogDescription>{description}</DialogDescription>
                    ) : null}
                </DialogHeader>

                <form onSubmit={handleSubmit} className="space-y-4">
                    <div className="grid gap-1.5">
                        <Label htmlFor="import-file">File</Label>
                        <Input
                            id="import-file"
                            type="file"
                            accept={accept}
                            onChange={handleFileChange}
                        />
                        {form.errors[fieldName as keyof ImportFormShape] ? (
                            <p className="text-destructive text-sm">
                                {form.errors[fieldName as keyof ImportFormShape]}
                            </p>
                        ) : null}
                    </div>

                    {children}

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="ghost"
                            onClick={() => onOpenChange(false)}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            disabled={form.processing || !form.data.file}
                        >
                            {form.processing ? (
                                <Spinner className="size-4" />
                            ) : null}
                            {submitLabel}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
