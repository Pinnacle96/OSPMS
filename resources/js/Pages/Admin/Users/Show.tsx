import { Link } from '@inertiajs/react';
import { Pencil, KeyRound } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/App/PageHeader';
import StatusBadge from '@/Components/Data/StatusBadge';
import AccessSummary from '@/Components/App/AccessSummary';
import ActivityTimeline from '@/Components/Data/ActivityTimeline';
import { dateTime } from '@/lib/formatters';
import type { User, AccessScopes } from '@/types';
export default function Show({ user, scopes, activities }: { user: User; scopes: AccessScopes; activities: { event: string; occurred_at: string; ip_address: string | null }[] }) {
    return <AppLayout title="User Detail" breadcrumbs={[{ label: 'Users', href: '/admin/users' }, { label: user.name }]}><PageHeader title={user.name} description="Account details, access and authentication history." actions={<><Link className="button secondary" href={`/admin/users/${user.public_id}/scopes`}><KeyRound size={15} />Access scopes</Link><Link className="button" href={`/admin/users/${user.public_id}/edit`}><Pencil size={15} />Edit user</Link></>} />
        <div className="detail-grid"><div><section className="panel" style={{ marginBottom: 20 }}><div className="panel-header"><h2>Account overview</h2><StatusBadge status={user.status} /></div><div className="panel-body"><dl className="detail-list"><div><dt>Email address</dt><dd>{user.email ?? 'Not provided'}</dd></div><div><dt>Username</dt><dd>{user.username ?? 'Not provided'}</dd></div><div><dt>Telephone</dt><dd>{user.phone ?? 'Not provided'}</dd></div><div><dt>Last sign in</dt><dd>{dateTime(user.last_login_at)}</dd></div><div><dt>Created</dt><dd>{dateTime(user.created_at)}</dd></div><div><dt>Assigned roles</dt><dd>{user.roles?.map(role => <span className="pill" key={role.id}>{role.name}</span>)}</dd></div></dl></div></section><section className="panel"><div className="panel-header"><h2>Access scopes</h2></div><div className="panel-body"><AccessSummary scopes={scopes} /></div></section></div>
        <section className="panel"><div className="panel-header"><div><h2>Authentication history</h2><p>Latest 15 events</p></div></div><ActivityTimeline activities={activities} /></section></div></AppLayout>;
}
