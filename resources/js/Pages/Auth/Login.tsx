import { Link, useForm } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import AuthLayout from '@/Layouts/AuthLayout';
import FormField from '@/Components/Forms/FormField';

export default function Login() {
    const form = useForm({ login: '', password: '', remember: false });
    return <AuthLayout title="Sign in to your workspace" description="Enter your assigned credentials to access the Osun State Park Management System.">
        <form onSubmit={e => { e.preventDefault(); form.post('/login', { onFinish: () => form.reset('password') }); }}>
            <FormField label="Email address or username" id="login" error={form.errors.login}><input id="login" value={form.data.login} autoComplete="username" autoFocus required onChange={e => form.setData('login', e.target.value)} aria-invalid={!!form.errors.login} aria-describedby={form.errors.login ? 'login-error' : undefined} /></FormField>
            <FormField label="Password" id="password" error={form.errors.password}><input id="password" type="password" value={form.data.password} autoComplete="current-password" required onChange={e => form.setData('password', e.target.value)} aria-invalid={!!form.errors.password} aria-describedby={form.errors.password ? 'password-error' : undefined} /></FormField>
            <div className="auth-options"><label className="checkbox-label"><input type="checkbox" checked={form.data.remember} onChange={e => form.setData('remember', e.target.checked)} />Remember me</label><Link href="/forgot-password" className="text-link">Forgot password?</Link></div>
            <button disabled={form.processing} className="button full" type="submit">{form.processing ? 'Signing in…' : 'Sign in'}<ArrowRight size={16} /></button>
        </form>
    </AuthLayout>;
}
