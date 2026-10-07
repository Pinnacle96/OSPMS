import { Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import FormField from './FormField';
import ConfirmDialog from '@/Components/Feedback/ConfirmDialog';
import { usePermissions } from '@/hooks/usePermissions';
import type { User } from '@/types';

export default function UserForm({ user, roles }: { user?: User; roles: string[] }) {
    const { can } = usePermissions();
    const [confirm, setConfirm] = useState(false);
    const form = useForm({ name: user?.name ?? '', email: user?.email ?? '', username: user?.username ?? '', phone: user?.phone ?? '', status: user?.status ?? 'active', password: '', password_confirmation: '', must_change_password: user?.must_change_password ?? true, ...(can('manage_roles') ? { roles: user?.roles?.map(role => role.name) ?? [] as string[] } : {}) });
    const submit = () => { setConfirm(false); if (user) form.patch(`/admin/users/${user.public_id}`); else form.post('/admin/users'); };
    return <><form onSubmit={e => { e.preventDefault(); if (user && form.data.status !== user.status && form.data.status !== 'active') setConfirm(true); else submit(); }} className="panel form-panel"><div className="panel-header"><div><h2>Account information</h2><p>Use an email address, a username, or both.</p></div></div><div className="panel-body"><div className="form-grid">
        {(['name', 'email', 'username', 'phone'] as const).map(key => <FormField key={key} label={{ name: 'Full name', email: 'Email address', username: 'Username', phone: 'Telephone' }[key]} id={key} error={form.errors[key]}><input id={key} type={key === 'email' ? 'email' : key === 'phone' ? 'tel' : 'text'} required={key === 'name'} value={form.data[key]} onChange={e => form.setData(key, e.target.value)} aria-invalid={!!form.errors[key]} aria-describedby={form.errors[key] ? `${key}-error` : undefined} /></FormField>)}
        <FormField label="Account status" id="status" error={form.errors.status}><select id="status" value={form.data.status} onChange={e => form.setData('status', e.target.value)}><option value="active">Active</option><option value="inactive">Inactive</option><option value="suspended">Suspended</option></select></FormField>
        <FormField label={user ? 'New password (optional)' : 'Temporary password'} id="password" error={form.errors.password} hint="At least 12 characters, mixed case and a number."><input id="password" type="password" autoComplete="new-password" minLength={12} required={!user} value={form.data.password} onChange={e => form.setData('password', e.target.value)} aria-invalid={!!form.errors.password} aria-describedby={form.errors.password ? 'password-error' : 'password-hint'} /></FormField>
        <FormField label="Confirm password" id="password_confirmation" error={form.errors.password_confirmation}><input id="password_confirmation" type="password" autoComplete="new-password" required={!!form.data.password} value={form.data.password_confirmation} onChange={e => form.setData('password_confirmation', e.target.value)} /></FormField>
        </div><div className="form-section"><label className="checkbox-label"><input type="checkbox" checked={form.data.must_change_password} onChange={e => form.setData('must_change_password', e.target.checked)} />Require password change on next sign in</label></div>
        {can('manage_roles') && <fieldset className="form-section"><legend>Assigned roles</legend><div className="checkbox-grid">{roles.map(role => <label key={role} className="checkbox-label"><input type="checkbox" checked={form.data.roles?.includes(role) ?? false} onChange={e => form.setData('roles', e.target.checked ? [...(form.data.roles ?? []), role] : (form.data.roles ?? []).filter(value => value !== role))} />{role}</label>)}</div>{form.errors.roles && <p role="alert" className="field-error">{form.errors.roles}</p>}</fieldset>}
        <div className="form-actions"><Link href={user ? `/admin/users/${user.public_id}` : '/admin/users'} className="button secondary">Cancel</Link><button type="submit" className="button" disabled={form.processing}>{form.processing ? 'Saving…' : user ? 'Save changes' : 'Create user'}</button></div></div></form>
        <ConfirmDialog open={confirm} title="Change account status?" description="This user will lose access to the system while the account is inactive or suspended." onClose={() => setConfirm(false)} onConfirm={submit} /></>;
}
