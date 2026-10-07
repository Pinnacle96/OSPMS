import { Link } from '@inertiajs/react';
import { Grid2X2 } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/App/PageHeader';
import DataTable from '@/Components/Data/DataTable';
import type { Role } from '@/types';
export default function Index({ roles }: { roles: Role[] }) { return <AppLayout title="Roles & Permissions" breadcrumbs={[{ label: 'Administration' }, { label: 'Roles & permissions' }]}><PageHeader title="Roles & permissions" description="Control the capabilities granted to each system role." actions={<Link className="button secondary" href="/admin/permissions"><Grid2X2 size={15} />Permission matrix</Link>} /><section className="panel"><DataTable rows={roles} rowKey={role => String(role.id)} columns={[
    { key: 'name', label: 'Role', render: role => <strong>{role.name}</strong> }, { key: 'users', label: 'Assigned users', render: role => role.users_count }, { key: 'permissions', label: 'Permissions', render: role => role.permissions.length }, { key: 'actions', label: 'Actions', render: role => <Link className="text-link" href={`/admin/roles/${role.id}`}>Review permissions</Link> },
]} /></section></AppLayout>; }
