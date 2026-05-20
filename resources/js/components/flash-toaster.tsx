import { usePage } from '@inertiajs/react';
import { useEffect } from 'react';
import { toast } from 'sonner';

type ImportSummary = {
    imported: number;
    skipped: Array<{ row: number; reason: string }>;
};

type FlashShape = {
    success?: string | null;
    error?: string | null;
    importSummary?: ImportSummary | null;
};

type SharedProps = {
    flash?: FlashShape;
};

export function FlashToaster() {
    const { flash } = usePage<SharedProps>().props;

    useEffect(() => {
        if (!flash) {
            return;
        }

        if (flash.error) {
            toast.error(flash.error);
        }

        if (flash.success) {
            const skippedDetail =
                flash.importSummary && flash.importSummary.skipped.length > 0
                    ? flash.importSummary.skipped
                          .slice(0, 5)
                          .map((entry) => `Row ${entry.row}: ${entry.reason}`)
                          .join('\n') +
                      (flash.importSummary.skipped.length > 5
                          ? `\n…and ${flash.importSummary.skipped.length - 5} more`
                          : '')
                    : undefined;

            toast.success(flash.success, { description: skippedDetail });
        }
    }, [flash]);

    return null;
}
