import { Link, useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/App/PageHeader';
import type { User, AccessScopes, Scope } from '@/types';
type ScopeType = 'lgas' | 'parks' | 'operators';
export default function Scopes({ user, scopes, options }: { user: User; scopes: AccessScopes; options: Record<ScopeType, { id: number; name: string }[]> }) {
    const form = useForm({ lgas: scopes.lgas.map(({ id, access_level }) => ({ id, access_level })), parks: scopes.parks.map(({ id, access_level }) => ({ id, access_level })), operators: scopes.operators.map(({ id, access_level }) => ({ id, access_level })) });
    const change = (type: ScopeType, id: number, value: Scope['access_level'] | null) => form.setData(type, value ? [...form.data[type].filter(item => item.id !== id), { id, access_level: value }] : form.data[type].filter(item => item.id !== id));
    return <AppLayout title="User Access Scopes" breadcrumbs={[{ label: 'Users', href: '/admin/users' }, { label: user.name, href: `/admin/users/${user.public_id}` }, { label: 'Access scopes' }]}><PageHeader title="User access scopes" description={`Assign the records ${user.name} can view or manage.`} />
        <form className="panel form-panel" onSubmit={e => { e.preventDefault(); form.put(`/admin/users/${user.public_id}/scopes`); }}><div className="panel-body">{scopes.statewide && <div className="notice info">This account has statewide access through its role permissions.</div>}
        {(['lgas', 'parks', 'operators'] as const).map(type => <fieldset className="scope-section" key={type}><legend>{{ lgas: 'Local government areas', parks: 'Parks', operators: 'Operators' }[type]}</legend>{options[type].length ? options[type].map(option => {
            const assigned = form.data[type].find(item => item.id === option.id);
            return <div className="scope-row" key={option.id}><label className="checkbox-label"><input type="checkbox" checked={!!assigned} onChange={e => change(type, option.id, e.target.checked ? 'view' : null)} />{option.name}</label>{assigned && <select aria-label={`Access level for ${option.name}`} value={assigned.access_level} onChange={e => change(type, option.id, e.target.value as Scope['access_level'])}><option value="view">View</option><option value="manage">Manage</option></select>}</div>;
        }) : <p className="muted small">No {type} have been registered yet. Scope options will become available with their registry module.</p>}</fieldset>)}
        {Object.values(form.errors).map((error, i) => <p role="alert" className="field-error" key={i}>{error}</p>)}<div className="form-actions"><Link href={`/admin/users/${user.public_id}`} className="button secondary">Back to user</Link><button className="button" disabled={form.processing}>{form.processing ? 'Saving…' : 'Save scopes'}</button></div></div></form></AppLayout>;
}
