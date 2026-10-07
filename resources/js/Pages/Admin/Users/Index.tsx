import { Link, useForm, router } from '@inertiajs/react';
import { UserPlus } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/App/PageHeader';
import DataTable from '@/Components/Data/DataTable';
import StatusBadge from '@/Components/Data/StatusBadge';
import SearchInput from '@/Components/Forms/SearchInput';
import FilterBar from '@/Components/Forms/FilterBar';
import { dateTime } from '@/lib/formatters';
import type { Paginated, User } from '@/types';

type Filters = { search?: string; status?: string; role?: string; sort?: string; direction?: string };
export default function Index({ users, filters, roles }: { users: Paginated<User>; filters: Filters; roles: string[] }) {
    const form = useForm({ search: filters.search ?? '', status: filters.status ?? '', role: filters.role ?? '' });
    return <AppLayout title="Users" breadcrumbs={[{ label: 'Administration' }, { label: 'Users' }]}><PageHeader title="Users" description="Manage system accounts, assigned roles and access." actions={<Link href="/admin/users/create" className="button"><UserPlus size={16} />Create user</Link>} />
        <section className="panel"><form onSubmit={e => { e.preventDefault(); form.get('/admin/users', { preserveState: true }); }}><FilterBar><SearchInput value={form.data.search} onChange={value => form.setData('search', value)} placeholder="Search name, email or username" /><div><label htmlFor="status-filter">Status</label><select id="status-filter" value={form.data.status} onChange={e => form.setData('status', e.target.value)}><option value="">All statuses</option>{['active', 'inactive', 'suspended'].map(status => <option value={status} key={status}>{status[0].toUpperCase() + status.slice(1)}</option>)}</select></div><div><label htmlFor="role-filter">Role</label><select id="role-filter" value={form.data.role} onChange={e => form.setData('role', e.target.value)}><option value="">All roles</option>{roles.map(role => <option key={role}>{role}</option>)}</select></div><button className="button secondary" disabled={form.processing}>Apply filters</button><Link href="/admin/users" className="text-link small">Clear</Link></FilterBar></form>
        <DataTable rows={users.data} page={users} rowKey={user => user.public_id} loading={form.processing} sort={filters.sort ?? 'name'} direction={filters.direction ?? 'asc'} onSort={sort => router.get('/admin/users', { ...form.data, sort, direction: filters.sort === sort && filters.direction !== 'desc' ? 'desc' : 'asc' }, { preserveState: true })} columns={[
            { key: 'name', label: 'User', sort: 'name', render: user => <div className="table-person"><Link href={`/admin/users/${user.public_id}`}><strong>{user.name}</strong></Link><span>{user.email ?? user.username}</span></div> },
            { key: 'roles', label: 'Assigned roles', render: user => user.roles?.map(role => <span key={role.id} className="pill">{role.name}</span>) },
            { key: 'status', label: 'Status', sort: 'status', render: user => <StatusBadge status={user.status} /> },
            { key: 'login', label: 'Last sign in', render: user => <span className="muted small">{dateTime(user.last_login_at)}</span> },
            { key: 'actions', label: 'Actions', render: user => <div className="row-actions"><Link className="text-link small" href={`/admin/users/${user.public_id}`}>View</Link><Link className="text-link small" href={`/admin/users/${user.public_id}/edit`}>Edit</Link></div> },
        ]} /></section></AppLayout>;
}
