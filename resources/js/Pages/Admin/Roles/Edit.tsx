import { Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/App/PageHeader';
import ConfirmDialog from '@/Components/Feedback/ConfirmDialog';
import type { Role } from '@/types';
export default function Edit({ role, permissions }: { role: Role; permissions: { id: number; name: string }[] }) {
    const form = useForm({ permissions: role.permissions.map(p => p.name) });
    const [confirm, setConfirm] = useState(false);
    return <AppLayout title="Role Permissions" breadcrumbs={[{ label: 'Roles', href: '/admin/roles' }, { label: role.name }]}><PageHeader title={role.name} description="Changes apply to every account assigned this role." /><form className="panel form-panel" onSubmit={e => { e.preventDefault(); setConfirm(true); }}><div className="panel-header"><h2>Granted permissions</h2><span className="panel-tag">{form.data.permissions.length} selected</span></div><div className="panel-body"><div className="checkbox-grid">{permissions.map(permission => <label className="checkbox-label" key={permission.id}><input type="checkbox" checked={form.data.permissions.includes(permission.name)} onChange={e => form.setData('permissions', e.target.checked ? [...form.data.permissions, permission.name] : form.data.permissions.filter(name => name !== permission.name))} />{permission.name.replaceAll('_', ' ')}</label>)}</div>{Object.values(form.errors).map((error, i) => <p className="field-error" role="alert" key={i}>{error}</p>)}<div className="form-actions"><Link className="button secondary" href="/admin/roles">Cancel</Link><button className="button" disabled={form.processing}>Save permissions</button></div></div></form><ConfirmDialog open={confirm} title="Update role permissions?" description="Review the selected permissions carefully. This change affects all users assigned to this role." onClose={() => setConfirm(false)} onConfirm={() => { setConfirm(false); form.put(`/admin/roles/${role.id}`); }} /></AppLayout>;
}
