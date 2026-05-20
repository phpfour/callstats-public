import { Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import { dashboard } from '@/routes/backoffice';
import { LeadForm } from './lead-form';
import type { AssignableUser, LeadInput } from './lead-form';

type Lead = {
    id: number;
    name: string;
    phone_number: string;
    email: string | null;
    study_destination: string | null;
    source: string | null;
    ielts_score: string | null;
    assigned_to_id: number | null;
};

type EditLeadProps = {
    lead: Lead;
    assignableUsers: AssignableUser[];
};

export default function EditLead({ lead, assignableUsers }: EditLeadProps) {
    const initialValues: LeadInput = {
        name: lead.name,
        phone_number: lead.phone_number,
        email: lead.email ?? '',
        study_destination: lead.study_destination ?? '',
        source: lead.source ?? '',
        ielts_score: lead.ielts_score ?? '',
        assigned_to_id: lead.assigned_to_id,
    };

    return (
        <>
            <Head title={`Edit ${lead.name}`} />

            <div className="max-w-2xl space-y-6">
                <Heading
                    title={`Edit ${lead.name}`}
                    description="Update lead details, source, or assignment."
                />

                <LeadForm
                    initialValues={initialValues}
                    submit={(form) => form.put(`/backoffice/leads/${lead.id}`)}
                    submitLabel="Save changes"
                    cancelHref="/backoffice/leads"
                    assignableUsers={assignableUsers}
                />
            </div>
        </>
    );
}

EditLead.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Leads', href: '/backoffice/leads' },
        { title: 'Edit', href: '#' },
    ],
};
