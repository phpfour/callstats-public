import { flexRender, getCoreRowModel, useReactTable } from '@tanstack/react-table';
import type {
    ColumnDef,
    OnChangeFn,
    RowSelectionState,
} from '@tanstack/react-table';
import { useMemo } from 'react';
import type { ReactNode } from 'react';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';

type DataTableProps<TData, TValue> = {
    columns: ColumnDef<TData, TValue>[];
    data: TData[];
    empty?: ReactNode;
    /**
     * Required when row selection is enabled. Returns the stable
     * identifier used by the controlled `rowSelection` map.
     */
    getRowId?: (row: TData) => string;
    rowSelection?: RowSelectionState;
    onRowSelectionChange?: OnChangeFn<RowSelectionState>;
};

export function DataTable<TData, TValue>({
    columns,
    data,
    empty = 'No results.',
    getRowId,
    rowSelection,
    onRowSelectionChange,
}: DataTableProps<TData, TValue>) {
    const selectionEnabled = rowSelection !== undefined;

    const fullColumns = useMemo<ColumnDef<TData, TValue>[]>(() => {
        if (!selectionEnabled) {
            return columns;
        }

        const selectColumn: ColumnDef<TData, TValue> = {
            id: '__select__',
            header: ({ table }) => (
                <Checkbox
                    aria-label="Select all rows"
                    checked={
                        table.getIsAllPageRowsSelected() ||
                        (table.getIsSomePageRowsSelected() && 'indeterminate')
                    }
                    onCheckedChange={(value) =>
                        table.toggleAllPageRowsSelected(!!value)
                    }
                />
            ),
            cell: ({ row }) => (
                <Checkbox
                    aria-label="Select row"
                    checked={row.getIsSelected()}
                    onCheckedChange={(value) => row.toggleSelected(!!value)}
                    onClick={(event) => event.stopPropagation()}
                />
            ),
            enableSorting: false,
            size: 40,
        };

        return [selectColumn, ...columns];
    }, [columns, selectionEnabled]);

    const table = useReactTable({
        data,
        columns: fullColumns,
        getCoreRowModel: getCoreRowModel(),
        getRowId,
        enableRowSelection: selectionEnabled,
        state: selectionEnabled ? { rowSelection } : undefined,
        onRowSelectionChange,
    });

    return (
        <div className="rounded-md border">
            <Table>
                <TableHeader>
                    {table.getHeaderGroups().map((group) => (
                        <TableRow key={group.id}>
                            {group.headers.map((header) => (
                                <TableHead key={header.id}>
                                    {header.isPlaceholder
                                        ? null
                                        : flexRender(
                                              header.column.columnDef.header,
                                              header.getContext(),
                                          )}
                                </TableHead>
                            ))}
                        </TableRow>
                    ))}
                </TableHeader>
                <TableBody>
                    {table.getRowModel().rows.length ? (
                        table.getRowModel().rows.map((row) => (
                            <TableRow
                                key={row.id}
                                data-state={
                                    row.getIsSelected() ? 'selected' : undefined
                                }
                            >
                                {row.getVisibleCells().map((cell) => (
                                    <TableCell key={cell.id}>
                                        {flexRender(
                                            cell.column.columnDef.cell,
                                            cell.getContext(),
                                        )}
                                    </TableCell>
                                ))}
                            </TableRow>
                        ))
                    ) : (
                        <TableRow>
                            <TableCell
                                colSpan={fullColumns.length}
                                className="text-muted-foreground h-24 text-center"
                            >
                                {empty}
                            </TableCell>
                        </TableRow>
                    )}
                </TableBody>
            </Table>
        </div>
    );
}
