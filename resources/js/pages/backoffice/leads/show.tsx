import { Head, Link } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { DataTable } from '@/components/data-table';
import Heading from '@/components/heading';
import { ShowField, ShowPanel } from '@/components/show-panel';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes/backoffice';

type RelatedUser = { id: number; name: string };

type CallLog = {
    id: number;
    called_at: string;
    duration: number | null;
    outcome: string | null;
    notes: string | null;
    user: RelatedUser | null;
};

type Reminder = {
    id: number;
    remind_at: string;
    type: string | null;
    notes: string | null;
    user: RelatedUser | null;
};

type Lead = {
    id: number;
    name: string;
    phone_number: string;
    email: string | null;
    study_destination: string | null;
    source: string | null;
    ielts_score: string | null;
    assigned_at: string | null;
    assigned_to: RelatedUser | null;
    call_logs: CallLog[];
    reminders: Reminder[];
};

const formatDateTime = (value: string | null): string => {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleString(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const formatDuration = (seconds: number | null): string => {
    if (seconds === null) {
        return '—';
    }

    return new Date(seconds * 1000).toISOString().slice(11, 19);
};

const callLogColumns: ColumnDef<CallLog>[] = [
    {
        accessorKey: 'called_at',
        header: 'Called at',
        cell: ({ row }) => formatDateTime(row.original.called_at),
    },
    {
        accessorKey: 'outcome',
        header: 'Outcome',
        cell: ({ row }) => row.original.outcome ?? '—',
    },
    {
        accessorKey: 'duration',
        header: 'Duration',
        cell: ({ row }) => formatDuration(row.original.duration),
    },
    {
        id: 'user',
        header: 'Agent',
        cell: ({ row }) => row.original.user?.name ?? '—',
    },
    {
        accessorKey: 'notes',
        header: 'Notes',
        cell: ({ row }) =>
            row.original.notes ? (
                <span className="line-clamp-1">{row.original.notes}</span>
            ) : (
                '—'
            ),
    },
];

const reminderColumns: ColumnDef<Reminder>[] = [
    {
        accessorKey: 'remind_at',
        header: 'Remind at',
        cell: ({ row }) => formatDateTime(row.original.remind_at),
    },
    {
        accessorKey: 'type',
        header: 'Type',
        cell: ({ row }) => row.original.type ?? '—',
    },
    {
        id: 'user',
        header: 'For agent',
        cell: ({ row }) => row.original.user?.name ?? '—',
    },
    {
        accessorKey: 'notes',
        header: 'Notes',
        cell: ({ row }) =>
            row.original.notes ? (
                <span className="line-clamp-1">{row.original.notes}</span>
            ) : (
                '—'
            ),
    },
];

type ShowLeadProps = {
    lead: Lead;
};

export default function ShowLead({ lead }: ShowLeadProps) {
    return (
        <>
            <Head title={lead.name} />

            <div className="flex items-start justify-between gap-4">
                <Heading title={lead.name} description={lead.phone_number} />
                <Button asChild>
                    <Link href={`/backoffice/leads/${lead.id}/edit`}>Edit</Link>
                </Button>
            </div>

            <ShowPanel title="Lead details">
                <dl className="grid grid-cols-1 gap-4 sm:grid-cols-2 md:grid-cols-3">
                    <ShowField label="Phone" value={lead.phone_number} />
                    <ShowField label="Email" value={lead.email} />
                    <ShowField
                        label="Study destination"
                        value={lead.study_destination}
                    />
                    <ShowField label="Source" value={lead.source} />
                    <ShowField label="IELTS score" value={lead.ielts_score} />
                    <ShowField
                        label="Assigned to"
                        value={lead.assigned_to?.name}
                    />
                    <ShowField
                        label="Assigned at"
                        value={formatDateTime(lead.assigned_at)}
                    />
                </dl>
            </ShowPanel>

            <ShowPanel
                title="Call logs"
                description={`${lead.call_logs.length} total — most recent first`}
            >
                <DataTable
                    columns={callLogColumns}
                    data={lead.call_logs}
                    empty="No calls logged for this lead yet."
                />
            </ShowPanel>

            <ShowPanel
                title="Reminders"
                description={`${lead.reminders.length} total — most recent first`}
            >
                <DataTable
                    columns={reminderColumns}
                    data={lead.reminders}
                    empty="No reminders set for this lead."
                />
            </ShowPanel>
        </>
    );
}

ShowLead.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Leads', href: '/backoffice/leads' },
        { title: 'Lead', href: '#' },
    ],
};
