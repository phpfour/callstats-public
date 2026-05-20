import { Head, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import Heading from '@/components/heading';
import {
    FormField,
    ResourceForm,
    ResourceFormSection,
} from '@/components/resource-form';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
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

type EditCallLogProps = {
    callLog: CallLog;
    outcomes: string[];
};

const NONE = 'none';

const formatDateTime = (value: string): string =>
    new Date(value).toLocaleString(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });

type CallLogFormShape = {
    outcome: string;
    notes: string;
};

export default function EditCallLog({ callLog, outcomes }: EditCallLogProps) {
    const form = useForm<CallLogFormShape>({
        outcome: callLog.outcome ?? '',
        notes: callLog.notes ?? '',
    });

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.put(`/backoffice/call-logs/${callLog.id}`);
    };

    return (
        <>
            <Head title="Edit call log" />

            <div className="max-w-2xl space-y-6">
                <Heading
                    title="Edit call log"
                    description={`${callLog.lead?.name ?? 'Unknown lead'} · ${formatDateTime(callLog.called_at)}`}
                />

                <ResourceForm
                    onSubmit={handleSubmit}
                    processing={form.processing}
                    submitLabel="Save changes"
                    cancelHref={`/backoffice/call-logs/${callLog.id}`}
                >
                    <ResourceFormSection
                        title="Outcome & notes"
                        description="Only outcome and notes can be corrected. Lead, agent, time, and duration are recorded by the mobile app and read-only."
                    >
                        <FormField
                            label="Outcome"
                            htmlFor="outcome"
                            error={form.errors.outcome}
                        >
                            <Select
                                value={form.data.outcome || NONE}
                                onValueChange={(value) =>
                                    form.setData(
                                        'outcome',
                                        value === NONE ? '' : value,
                                    )
                                }
                            >
                                <SelectTrigger
                                    id="outcome"
                                    aria-invalid={!!form.errors.outcome}
                                >
                                    <SelectValue placeholder="No outcome" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={NONE}>
                                        No outcome
                                    </SelectItem>
                                    {outcomes.map((outcome) => (
                                        <SelectItem
                                            key={outcome}
                                            value={outcome}
                                        >
                                            {outcome}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </FormField>

                        <FormField
                            label="Notes"
                            htmlFor="notes"
                            error={form.errors.notes}
                        >
                            <Textarea
                                id="notes"
                                rows={5}
                                placeholder="What happened on this call?"
                                aria-invalid={!!form.errors.notes}
                                value={form.data.notes}
                                onChange={(event) =>
                                    form.setData('notes', event.target.value)
                                }
                            />
                        </FormField>
                    </ResourceFormSection>
                </ResourceForm>
            </div>
        </>
    );
}

EditCallLog.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Call logs', href: '/backoffice/call-logs' },
        { title: 'Edit', href: '#' },
    ],
};
