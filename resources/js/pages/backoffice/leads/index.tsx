import { Head, Link, router } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { MoreHorizontal } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { BulkActionBar } from '@/components/bulk-action-bar';
import { DataTable, DataTablePagination } from '@/components/data-table';
import type {
    PaginatorMeta,
    RowSelectionState,
} from '@/components/data-table';
import Heading from '@/components/heading';
import { ImportDialog } from '@/components/import-dialog';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { dashboard } from '@/routes/backoffice';

type Lead = {
    id: number;
    name: string;
    phone_number: string;
    email: string | null;
    study_destination: string | null;
    source: string | null;
    ielts_score: string | null;
    assigned_at: string | null;
    assigned_to: { id: number; name: string } | null;
    last_call: { id: number; called_at: string; outcome: string | null } | null;
};

type PaginatedLeads = PaginatorMeta & { data: Lead[] };

type Filters = {
    search: string | null;
    assigned_to_id: number | null;
    source: string | null;
    assigned_from: string | null;
    assigned_to: string | null;
};

type AssignableUser = { id: number; name: string };

type LeadsIndexProps = {
    leads: PaginatedLeads;
    filters: Filters;
    sort: { column: string | null; direction: string };
    assignableUsers: AssignableUser[];
};

const formatDate = (value: string | null): string => {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleDateString(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
};

export default function LeadsIndex({
    leads,
    filters,
    assignableUsers,
}: LeadsIndexProps) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [rowSelection, setRowSelection] = useState<RowSelectionState>({});
    const [leadToDelete, setLeadToDelete] = useState<Lead | null>(null);
    const [bulkAssignOpen, setBulkAssignOpen] = useState(false);
    const [bulkAssignTo, setBulkAssignTo] = useState<string>('');
    const [importOpen, setImportOpen] = useState(false);

    const selectedIds = Object.keys(rowSelection)
        .filter((id) => rowSelection[id])
        .map(Number);

    const submitSearch = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        router.get(
            '/backoffice/leads',
            { search: search || undefined },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const confirmDelete = () => {
        if (!leadToDelete) {
            return;
        }

        router.delete(`/backoffice/leads/${leadToDelete.id}`, {
            onSuccess: () => setLeadToDelete(null),
        });
    };

    const submitBulkAssign = () => {
        if (!bulkAssignTo) {
            return;
        }

        router.post(
            '/backoffice/leads/bulk-assign',
            {
                lead_ids: selectedIds,
                assigned_to_id: Number(bulkAssignTo),
            },
            {
                onSuccess: () => {
                    setBulkAssignOpen(false);
                    setBulkAssignTo('');
                    setRowSelection({});
                },
            },
        );
    };

    const columns: ColumnDef<Lead>[] = [
        {
            accessorKey: 'name',
            header: 'Name',
            cell: ({ row }) => (
                <Link
                    href={`/backoffice/leads/${row.original.id}`}
                    className="font-medium hover:underline"
                >
                    {row.original.name}
                </Link>
            ),
        },
        { accessorKey: 'phone_number', header: 'Phone' },
        {
            accessorKey: 'study_destination',
            header: 'Destination',
            cell: ({ row }) => row.original.study_destination ?? '—',
        },
        {
            accessorKey: 'source',
            header: 'Source',
            cell: ({ row }) => row.original.source ?? '—',
        },
        {
            accessorKey: 'ielts_score',
            header: 'IELTS',
            cell: ({ row }) => row.original.ielts_score ?? '—',
        },
        {
            id: 'assigned_to',
            header: 'Assigned to',
            cell: ({ row }) => row.original.assigned_to?.name ?? '—',
        },
        {
            accessorKey: 'assigned_at',
            header: 'Assigned',
            cell: ({ row }) => formatDate(row.original.assigned_at),
        },
        {
            id: 'last_call',
            header: 'Last call',
            cell: ({ row }) =>
                row.original.last_call
                    ? formatDate(row.original.last_call.called_at)
                    : '—',
        },
        {
            id: 'actions',
            header: '',
            cell: ({ row }) => (
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button
                            variant="ghost"
                            size="sm"
                            aria-label="Lead actions"
                        >
                            <MoreHorizontal className="size-4" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                        <DropdownMenuItem asChild>
                            <Link
                                href={`/backoffice/leads/${row.original.id}`}
                            >
                                View
                            </Link>
                        </DropdownMenuItem>
                        <DropdownMenuItem asChild>
                            <Link
                                href={`/backoffice/leads/${row.original.id}/edit`}
                            >
                                Edit
                            </Link>
                        </DropdownMenuItem>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem
                            variant="destructive"
                            onSelect={(event) => {
                                event.preventDefault();
                                setLeadToDelete(row.original);
                            }}
                        >
                            Delete
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            ),
        },
    ];

    return (
        <>
            <Head title="Leads" />

            <div className="flex flex-col gap-4">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        title="Leads"
                        description="All leads in the pipeline. Search by name or phone, filter by assignee or source."
                    />
                    <div className="flex items-center gap-2">
                        <Button
                            variant="outline"
                            onClick={() => setImportOpen(true)}
                        >
                            Import
                        </Button>
                        <Button asChild>
                            <Link href="/backoffice/leads/create">New lead</Link>
                        </Button>
                    </div>
                </div>

                <form
                    onSubmit={submitSearch}
                    className="flex max-w-md items-center gap-2"
                >
                    <Input
                        type="search"
                        placeholder="Search name or phone…"
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                    />
                    <Button type="submit" variant="secondary">
                        Search
                    </Button>
                </form>

                <BulkActionBar
                    selectedCount={selectedIds.length}
                    onClear={() => setRowSelection({})}
                    actions={[
                        {
                            label: 'Assign to…',
                            onClick: () => setBulkAssignOpen(true),
                        },
                    ]}
                />

                <DataTable
                    columns={columns}
                    data={leads.data}
                    getRowId={(lead) => lead.id.toString()}
                    rowSelection={rowSelection}
                    onRowSelectionChange={setRowSelection}
                />

                <DataTablePagination meta={leads} />
            </div>

            <Dialog
                open={leadToDelete !== null}
                onOpenChange={(open) => !open && setLeadToDelete(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Delete lead?</DialogTitle>
                        <DialogDescription>
                            This permanently removes &ldquo;{leadToDelete?.name}
                            &rdquo; and all its call logs and reminders.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="ghost"
                            onClick={() => setLeadToDelete(null)}
                        >
                            Cancel
                        </Button>
                        <Button variant="destructive" onClick={confirmDelete}>
                            Delete
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog open={bulkAssignOpen} onOpenChange={setBulkAssignOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            Assign {selectedIds.length}{' '}
                            {selectedIds.length === 1 ? 'lead' : 'leads'}
                        </DialogTitle>
                        <DialogDescription>
                            Pick an agent to take ownership.
                        </DialogDescription>
                    </DialogHeader>

                    <Select
                        value={bulkAssignTo}
                        onValueChange={setBulkAssignTo}
                    >
                        <SelectTrigger>
                            <SelectValue placeholder="Pick an agent" />
                        </SelectTrigger>
                        <SelectContent>
                            {assignableUsers.map((user) => (
                                <SelectItem
                                    key={user.id}
                                    value={user.id.toString()}
                                >
                                    {user.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    <DialogFooter>
                        <Button
                            variant="ghost"
                            onClick={() => setBulkAssignOpen(false)}
                        >
                            Cancel
                        </Button>
                        <Button
                            disabled={!bulkAssignTo}
                            onClick={submitBulkAssign}
                        >
                            Assign
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <ImportDialog
                open={importOpen}
                onOpenChange={setImportOpen}
                title="Import leads"
                description="Upload an Excel file (xlsx). Columns: name, phone, email, destination, source, IELTS, agent code."
                endpoint="/backoffice/leads/import"
                accept=".xlsx,.xls"
            />
        </>
    );
}

LeadsIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Leads', href: '/backoffice/leads' },
    ],
};
