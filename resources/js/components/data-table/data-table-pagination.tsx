import { router } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { Button } from '@/components/ui/button';

export type PaginatorMeta = {
    current_page: number;
    from: number | null;
    last_page: number;
    per_page: number;
    to: number | null;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};

type DataTablePaginationProps = {
    meta: PaginatorMeta;
};

export function DataTablePagination({ meta }: DataTablePaginationProps) {
    const visit = (url: string | null) => {
        if (!url) {
            return;
        }

        router.visit(url, { preserveState: true, preserveScroll: true });
    };

    const summary =
        meta.total === 0
            ? 'No results'
            : `Showing ${meta.from ?? 0}–${meta.to ?? 0} of ${meta.total}`;

    return (
        <div className="flex items-center justify-between gap-4 px-1 py-2">
            <p className="text-muted-foreground text-sm">{summary}</p>

            <div className="flex items-center gap-2">
                <p className="text-muted-foreground hidden text-sm sm:inline">
                    Page {meta.current_page} of {Math.max(meta.last_page, 1)}
                </p>
                <Button
                    variant="outline"
                    size="sm"
                    disabled={!meta.prev_page_url}
                    onClick={() => visit(meta.prev_page_url)}
                >
                    <ChevronLeft className="size-4" />
                    <span className="sr-only">Previous page</span>
                </Button>
                <Button
                    variant="outline"
                    size="sm"
                    disabled={!meta.next_page_url}
                    onClick={() => visit(meta.next_page_url)}
                >
                    <span className="sr-only">Next page</span>
                    <ChevronRight className="size-4" />
                </Button>
            </div>
        </div>
    );
}
