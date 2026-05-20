import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import {
    FormField,
    ResourceForm,
    ResourceFormSection,
} from '@/components/resource-form';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

export type RoleOption = { value: string; label: string };

export type UserFormShape = {
    name: string;
    email: string;
    code: string;
    role: string;
    password: string;
    password_confirmation: string;
};

type UserFormProps = {
    initialValues: UserFormShape;
    submit: (form: ReturnType<typeof useForm<UserFormShape>>) => void;
    submitLabel: string;
    cancelHref: string;
    roles: RoleOption[];
    passwordHint: string;
};

export function UserForm({
    initialValues,
    submit,
    submitLabel,
    cancelHref,
    roles,
    passwordHint,
}: UserFormProps) {
    const form = useForm<UserFormShape>(initialValues);

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        submit(form);
    };

    return (
        <ResourceForm
            onSubmit={handleSubmit}
            processing={form.processing}
            submitLabel={submitLabel}
            cancelHref={cancelHref}
        >
            <ResourceFormSection
                title="Profile"
                description="Identifies the user across the app and to the lead-import file via their code."
            >
                <FormField
                    label="Full name"
                    htmlFor="name"
                    required
                    error={form.errors.name}
                >
                    <Input
                        id="name"
                        autoFocus
                        autoComplete="name"
                        value={form.data.name}
                        aria-invalid={!!form.errors.name}
                        onChange={(event) =>
                            form.setData('name', event.target.value)
                        }
                    />
                </FormField>

                <div className="grid gap-5 md:grid-cols-2">
                    <FormField
                        label="Email"
                        htmlFor="email"
                        required
                        error={form.errors.email}
                    >
                        <Input
                            id="email"
                            type="email"
                            autoComplete="email"
                            value={form.data.email}
                            aria-invalid={!!form.errors.email}
                            onChange={(event) =>
                                form.setData('email', event.target.value)
                            }
                        />
                    </FormField>

                    <FormField
                        label="Agent code"
                        htmlFor="code"
                        error={form.errors.code}
                        description="Used by the lead-import file to assign rows."
                    >
                        <Input
                            id="code"
                            value={form.data.code}
                            placeholder="e.g. A-007"
                            aria-invalid={!!form.errors.code}
                            onChange={(event) =>
                                form.setData('code', event.target.value)
                            }
                        />
                    </FormField>
                </div>
            </ResourceFormSection>

            <ResourceFormSection
                title="Role"
                description="Determines what they can do in the backoffice and the mobile app."
            >
                <FormField
                    label="Role"
                    htmlFor="role"
                    required
                    error={form.errors.role}
                    className="md:max-w-md"
                >
                    <Select
                        value={form.data.role}
                        onValueChange={(value) => form.setData('role', value)}
                    >
                        <SelectTrigger
                            id="role"
                            aria-invalid={!!form.errors.role}
                        >
                            <SelectValue placeholder="Pick a role" />
                        </SelectTrigger>
                        <SelectContent>
                            {roles.map((role) => (
                                <SelectItem key={role.value} value={role.value}>
                                    {role.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </FormField>
            </ResourceFormSection>

            <ResourceFormSection title="Password" description={passwordHint}>
                <div className="grid gap-5 md:grid-cols-2">
                    <FormField
                        label="Password"
                        htmlFor="password"
                        error={form.errors.password}
                    >
                        <Input
                            id="password"
                            type="password"
                            autoComplete="new-password"
                            value={form.data.password}
                            aria-invalid={!!form.errors.password}
                            onChange={(event) =>
                                form.setData('password', event.target.value)
                            }
                        />
                    </FormField>

                    <FormField
                        label="Confirm password"
                        htmlFor="password_confirmation"
                    >
                        <Input
                            id="password_confirmation"
                            type="password"
                            autoComplete="new-password"
                            value={form.data.password_confirmation}
                            onChange={(event) =>
                                form.setData(
                                    'password_confirmation',
                                    event.target.value,
                                )
                            }
                        />
                    </FormField>
                </div>
            </ResourceFormSection>
        </ResourceForm>
    );
}
