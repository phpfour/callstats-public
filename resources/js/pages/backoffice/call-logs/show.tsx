import { Head, Link } from '@inertiajs/react';
import Heading from '@/components/heading';
import { ShowField, ShowPanel } from '@/components/show-panel';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes/backoffice';

type RelatedUser = { id: number; name: string };
type RelatedLead = { id: number; name: string; phone_number: string };

type CallLog = {
    id: number;
    called_at: string;
    duration: number | null;
    outcome: string | null;
    notes: string | null;
    lead: RelatedLead | null;
    user: RelatedUser | null;
};

type ShowCallLogProps = {
    callLog: CallLog;
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

export default function ShowCallLog({ callLog }: ShowCallLogProps) {
    return (
        <>
            <Head title={`Call · ${formatDateTime(callLog.called_at)}`} />

            <div className="flex items-start justify-between gap-4">
                <Heading
                    title="Call log"
                    description={formatDateTime(callLog.called_at)}
                />
                <Button asChild>
                    <Link href={`/backoffice/call-logs/${callLog.id}/edit`}>
                        Edit
                    </Link>
                </Button>
            </div>

            <ShowPanel title="Call details">
                <dl className="grid grid-cols-1 gap-4 sm:grid-cols-2 md:grid-cols-3">
                    <ShowField
                        label="Lead"
                        value={
                            callLog.lead ? (
                                <Link
                                    href={`/backoffice/leads/${callLog.lead.id}`}
                                    className="hover:underline"
                                >
                                    {callLog.lead.name}
                                </Link>
                            ) : null
                        }
                    />
                    <ShowField label="Phone" value={callLog.lead?.phone_number} />
                    <ShowField label="Agent" value={callLog.user?.name} />
                    <ShowField
                        label="Called at"
                        value={formatDateTime(callLog.called_at)}
                    />
                    <ShowField
                        label="Duration"
                        value={formatDuration(callLog.duration)}
                    />
                    <ShowField label="Outcome" value={callLog.outcome} />
                </dl>
            </ShowPanel>

            <ShowPanel title="Notes">
                {callLog.notes ? (
                    <p className="text-sm whitespace-pre-wrap">
                        {callLog.notes}
                    </p>
                ) : (
                    <p className="text-muted-foreground text-sm">
                        No notes recorded.
                    </p>
                )}
            </ShowPanel>
        </>
    );
}

ShowCallLog.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Call logs', href: '/backoffice/call-logs' },
        { title: 'Call log', href: '#' },
    ],
};
