import { Link, useForm } from '@inertiajs/react';
import AuthLayout from '@/Layouts/AuthLayout';
import FormField from '@/Components/Forms/FormField';
export default function ForgotPassword() {
    const form = useForm({ email: '' });
    return <AuthLayout title="Reset your password" description="Enter your account email address. We’ll send you a secure link to choose a new password."><form onSubmit={e => { e.preventDefault(); form.post('/forgot-password'); }}><FormField label="Email address" id="email" error={form.errors.email}><input id="email" type="email" autoComplete="email" autoFocus required value={form.data.email} onChange={e => form.setData('email', e.target.value)} aria-invalid={!!form.errors.email} aria-describedby={form.errors.email ? 'email-error' : undefined} /></FormField><button type="submit" className="button full" disabled={form.processing}>{form.processing ? 'Sending…' : 'Send reset link'}</button><Link href="/login" className="text-link small">Back to sign in</Link></form></AuthLayout>;
}
