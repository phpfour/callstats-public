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

const UNASSIGNED = 'none';

export type AssignableUser = { id: number; name: string };

export type LeadInput = {
    name: string;
    phone_number: string;
    email: string;
    study_destination: string;
    source: string;
    ielts_score: string;
    assigned_to_id: number | null;
};

type LeadFormProps = {
    initialValues: LeadInput;
    submit: (form: ReturnType<typeof useForm<LeadInput>>) => void;
    submitLabel: string;
    cancelHref: string;
    assignableUsers: AssignableUser[];
};

export function LeadForm({
    initialValues,
    submit,
    submitLabel,
    cancelHref,
    assignableUsers,
}: LeadFormProps) {
    const form = useForm<LeadInput>(initialValues);

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
                title="Contact"
                description="How agents reach this lead. Phone is the primary identifier."
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
                        placeholder="Aisha Khan"
                        aria-invalid={!!form.errors.name}
                        value={form.data.name}
                        onChange={(event) =>
                            form.setData('name', event.target.value)
                        }
                    />
                </FormField>

                <div className="grid gap-5 md:grid-cols-2">
                    <FormField
                        label="Phone number"
                        htmlFor="phone_number"
                        required
                        error={form.errors.phone_number}
                        description="Stored verbatim — must be unique across leads."
                    >
                        <Input
                            id="phone_number"
                            inputMode="tel"
                            autoComplete="tel"
                            placeholder="+880 1711 000 111"
                            aria-invalid={!!form.errors.phone_number}
                            value={form.data.phone_number}
                            onChange={(event) =>
                                form.setData(
                                    'phone_number',
                                    event.target.value,
                                )
                            }
                        />
                    </FormField>

                    <FormField
                        label="Email"
                        htmlFor="email"
                        error={form.errors.email}
                    >
                        <Input
                            id="email"
                            type="email"
                            autoComplete="email"
                            placeholder="aisha@example.com"
                            aria-invalid={!!form.errors.email}
                            value={form.data.email}
                            onChange={(event) =>
                                form.setData('email', event.target.value)
                            }
                        />
                    </FormField>
                </div>
            </ResourceFormSection>

            <ResourceFormSection
                title="Details"
                description="Optional context that helps with qualification and routing."
            >
                <div className="grid gap-5 md:grid-cols-2">
                    <FormField
                        label="Study destination"
                        htmlFor="study_destination"
                        error={form.errors.study_destination}
                    >
                        <Input
                            id="study_destination"
                            placeholder="Canada, UK, Australia…"
                            aria-invalid={!!form.errors.study_destination}
                            value={form.data.study_destination}
                            onChange={(event) =>
                                form.setData(
                                    'study_destination',
                                    event.target.value,
                                )
                            }
                        />
                    </FormField>

                    <FormField
                        label="Source"
                        htmlFor="source"
                        error={form.errors.source}
                    >
                        <Input
                            id="source"
                            placeholder="Facebook, Walk-in, Referral…"
                            aria-invalid={!!form.errors.source}
                            value={form.data.source}
                            onChange={(event) =>
                                form.setData('source', event.target.value)
                            }
                        />
                    </FormField>
                </div>

                <FormField
                    label="IELTS score"
                    htmlFor="ielts_score"
                    error={form.errors.ielts_score}
                    className="md:max-w-xs"
                >
                    <Input
                        id="ielts_score"
                        placeholder="e.g. 7.0"
                        aria-invalid={!!form.errors.ielts_score}
                        value={form.data.ielts_score}
                        onChange={(event) =>
                            form.setData('ielts_score', event.target.value)
                        }
                    />
                </FormField>
            </ResourceFormSection>

            <ResourceFormSection
                title="Assignment"
                description="Which agent owns the lead. Stamped with assigned_at automatically."
            >
                <FormField
                    label="Assigned to"
                    htmlFor="assigned_to_id"
                    error={form.errors.assigned_to_id}
                    className="md:max-w-md"
                >
                    <Select
                        value={
                            form.data.assigned_to_id?.toString() ?? UNASSIGNED
                        }
                        onValueChange={(value) =>
                            form.setData(
                                'assigned_to_id',
                                value === UNASSIGNED ? null : Number(value),
                            )
                        }
                    >
                        <SelectTrigger
                            id="assigned_to_id"
                            aria-invalid={!!form.errors.assigned_to_id}
                        >
                            <SelectValue placeholder="Unassigned" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={UNASSIGNED}>
                                Unassigned
                            </SelectItem>
                            {assignableUsers.map((user) => (
                                <SelectItem
                                    key={user.id}
                                    value={user.id.toString()}
                                >
                                    {user.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </FormField>
            </ResourceFormSection>
        </ResourceForm>
    );
}
