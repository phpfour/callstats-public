import { Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import { dashboard } from '@/routes/backoffice';
import { LeadForm } from './lead-form';
import type { AssignableUser, LeadInput } from './lead-form';

const blankLead: LeadInput = {
    name: '',
    phone_number: '',
    email: '',
    study_destination: '',
    source: '',
    ielts_score: '',
    assigned_to_id: null,
};

type CreateLeadProps = {
    assignableUsers: AssignableUser[];
};

export default function CreateLead({ assignableUsers }: CreateLeadProps) {
    return (
        <>
            <Head title="New lead" />

            <div className="max-w-2xl space-y-6">
                <Heading
                    title="New lead"
                    description="Add a lead to the pipeline."
                />

                <LeadForm
                    initialValues={blankLead}
                    submit={(form) => form.post('/backoffice/leads')}
                    submitLabel="Create lead"
                    cancelHref="/backoffice/leads"
                    assignableUsers={assignableUsers}
                />
            </div>
        </>
    );
}

CreateLead.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Leads', href: '/backoffice/leads' },
        { title: 'New', href: '/backoffice/leads/create' },
    ],
};
