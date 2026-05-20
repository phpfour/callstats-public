import { Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import { dashboard } from '@/routes/backoffice';
import { UserForm } from './user-form';
import type { RoleOption, UserFormShape } from './user-form';

type EditUser = {
    id: number;
    name: string;
    email: string;
    code: string | null;
    role: string | null;
};

type EditUserProps = {
    user: EditUser;
    roles: RoleOption[];
};

export default function EditUser({ user, roles }: EditUserProps) {
    const initialValues: UserFormShape = {
        name: user.name,
        email: user.email,
        code: user.code ?? '',
        role: user.role ?? '',
        password: '',
        password_confirmation: '',
    };

    return (
        <>
            <Head title={`Edit ${user.name}`} />

            <div className="max-w-2xl space-y-6">
                <Heading
                    title={`Edit ${user.name}`}
                    description="Update profile, role, or set a new password."
                />

                <UserForm
                    initialValues={initialValues}
                    submit={(form) => form.put(`/backoffice/users/${user.id}`)}
                    submitLabel="Save changes"
                    cancelHref="/backoffice/users"
                    roles={roles}
                    passwordHint="Leave blank to keep the existing password."
                />
            </div>
        </>
    );
}

EditUser.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Users', href: '/backoffice/users' },
        { title: 'Edit', href: '#' },
    ],
};
