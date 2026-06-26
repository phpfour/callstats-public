import { Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import { dashboard } from '@/routes/backoffice';
import { UserForm } from './user-form';
import type { RoleOption, UserFormShape } from './user-form';

const blank: UserFormShape = {
    name: '',
    email: '',
    code: '',
    role: '',
    password: '',
    password_confirmation: '',
    daily_call_target: '',
    conversion_rate_target: '',
};

type CreateUserProps = {
    roles: RoleOption[];
};

export default function CreateUser({ roles }: CreateUserProps) {
    return (
        <>
            <Head title="New user" />

            <div className="max-w-2xl space-y-6">
                <Heading
                    title="New user"
                    description="Invite an admin, supervisor, or agent."
                />

                <UserForm
                    initialValues={blank}
                    submit={(form) => form.post('/backoffice/users')}
                    submitLabel="Create user"
                    cancelHref="/backoffice/users"
                    roles={roles}
                    passwordHint="Required. Share it with the user out-of-band; they can change it after sign-in."
                />
            </div>
        </>
    );
}

CreateUser.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Users', href: '/backoffice/users' },
        { title: 'New', href: '/backoffice/users/create' },
    ],
};
