import { useForm } from '@inertiajs/react';
import AuthLayout from '@/Layouts/AuthLayout';
import FormField from '@/Components/Forms/FormField';
export default function ResetPassword({ token, email }: { token: string; email: string }) {
    const form = useForm({ token, email, password: '', password_confirmation: '' });
    return <AuthLayout title="Choose a new password" description="Use at least 12 characters, including uppercase and lowercase letters and a number."><form onSubmit={e => { e.preventDefault(); form.post('/reset-password', { onFinish: () => form.reset('password', 'password_confirmation') }); }}>
        <FormField label="Email address" id="email" error={form.errors.email}><input id="email" type="email" required value={form.data.email} onChange={e => form.setData('email', e.target.value)} aria-invalid={!!form.errors.email} aria-describedby={form.errors.email ? 'email-error' : undefined} /></FormField>
        <FormField label="New password" id="password" error={form.errors.password}><input id="password" type="password" autoComplete="new-password" minLength={12} required value={form.data.password} onChange={e => form.setData('password', e.target.value)} aria-invalid={!!form.errors.password} aria-describedby={form.errors.password ? 'password-error' : undefined} /></FormField>
        <FormField label="Confirm password" id="password_confirmation" error={form.errors.password_confirmation}><input id="password_confirmation" type="password" autoComplete="new-password" required value={form.data.password_confirmation} onChange={e => form.setData('password_confirmation', e.target.value)} /></FormField><button className="button full" disabled={form.processing}>Reset password</button></form></AuthLayout>;
}
