import { Head, Link, router } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { MoreHorizontal } from 'lucide-react';
import { useState } from 'react';
import { DataTable, DataTablePagination } from '@/components/data-table';
import type { PaginatorMeta } from '@/components/data-table';
import Heading from '@/components/heading';
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

type Role = { id: number; name: string };

type AppUser = {
    id: number;
    name: string;
    email: string;
    code: string | null;
    created_at: string;
    roles: Role[];
};

type RoleOption = { value: string; label: string };

type PaginatedUsers = PaginatorMeta & { data: AppUser[] };

type UsersIndexProps = {
    users: PaginatedUsers;
    filters: { search: string | null; role: string | null };
    roles: RoleOption[];
};

const ALL = 'all';

export default function UsersIndex({ users, filters, roles }: UsersIndexProps) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [userToDelete, setUserToDelete] = useState<AppUser | null>(null);

    const apply = (next: { search?: string; role?: string }) => {
        router.get(
            '/backoffice/users',
            {
                search:
                    'search' in next
                        ? next.search || undefined
                        : (filters.search ?? undefined),
                role:
                    'role' in next
                        ? next.role
                        : (filters.role ?? undefined),
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const submitSearch = () => apply({ search });

    const confirmDelete = () => {
        if (!userToDelete) {
            return;
        }

        router.delete(`/backoffice/users/${userToDelete.id}`, {
            onFinish: () => setUserToDelete(null),
        });
    };

    const columns: ColumnDef<AppUser>[] = [
        {
            accessorKey: 'name',
            header: 'Name',
            cell: ({ row }) => (
                <Link
                    href={`/backoffice/users/${row.original.id}/edit`}
                    className="font-medium hover:underline"
                >
                    {row.original.name}
                </Link>
            ),
        },
        { accessorKey: 'email', header: 'Email' },
        {
            accessorKey: 'code',
            header: 'Code',
            cell: ({ row }) => row.original.code ?? '—',
        },
        {
            id: 'role',
            header: 'Role',
            cell: ({ row }) => row.original.roles[0]?.name ?? '—',
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
                            aria-label="User actions"
                        >
                            <MoreHorizontal className="size-4" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                        <DropdownMenuItem asChild>
                            <Link
                                href={`/backoffice/users/${row.original.id}/edit`}
                            >
                                Edit
                            </Link>
                        </DropdownMenuItem>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem
                            variant="destructive"
                            onSelect={(event) => {
                                event.preventDefault();
                                setUserToDelete(row.original);
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
            <Head title="Users" />

            <div className="flex flex-col gap-4">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        title="Users"
                        description="Admins, supervisors, and agents. Search by name, email, or agent code."
                    />
                    <Button asChild>
                        <Link href="/backoffice/users/create">New user</Link>
                    </Button>
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    <form
                        onSubmit={(event) => {
                            event.preventDefault();
                            submitSearch();
                        }}
                        className="flex items-center gap-2"
                    >
                        <Input
                            type="search"
                            placeholder="Search…"
                            className="w-[260px]"
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                        />
                        <Button type="submit" variant="secondary" size="sm">
                            Search
                        </Button>
                    </form>

                    <Select
                        value={filters.role ?? ALL}
                        onValueChange={(value) =>
                            apply({ role: value === ALL ? undefined : value })
                        }
                    >
                        <SelectTrigger className="w-[180px]">
                            <SelectValue placeholder="All roles" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ALL}>All roles</SelectItem>
                            {roles.map((role) => (
                                <SelectItem key={role.value} value={role.value}>
                                    {role.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                <DataTable columns={columns} data={users.data} />
                <DataTablePagination meta={users} />
            </div>

            <Dialog
                open={userToDelete !== null}
                onOpenChange={(open) => !open && setUserToDelete(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Delete user?</DialogTitle>
                        <DialogDescription>
                            This permanently removes &ldquo;
                            {userToDelete?.name}&rdquo;. Their leads stay
                            assigned and need reassignment by hand.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="ghost"
                            onClick={() => setUserToDelete(null)}
                        >
                            Cancel
                        </Button>
                        <Button variant="destructive" onClick={confirmDelete}>
                            Delete
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

UsersIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Users', href: '/backoffice/users' },
    ],
};
