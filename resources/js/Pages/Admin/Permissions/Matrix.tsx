import { Link } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/App/PageHeader';
import type { Role } from '@/types';
export default function Matrix({ roles, permissions }: { roles: Role[]; permissions: { id: number; name: string }[] }) {
    return <AppLayout title="Permission Matrix" breadcrumbs={[{ label: 'Roles', href: '/admin/roles' }, { label: 'Permission matrix' }]}><PageHeader title="Permission matrix" description="Compare the permissions assigned to each Phase 1 role." /><section className="panel"><div className="table-scroll"><table className="matrix"><thead><tr><th scope="col">Permission</th>{roles.map(role => <th scope="col" key={role.id}><Link className="text-link" href={`/admin/roles/${role.id}`}>{role.name}</Link></th>)}</tr></thead><tbody>{permissions.map(permission => <tr key={permission.id}><th scope="row">{permission.name.replaceAll('_', ' ')}</th>{roles.map(role => <td key={role.id}>{role.permissions.some(p => p.name === permission.name) ? <span className="matrix-check" aria-label="Granted">✓</span> : <span className="muted" aria-label="Not granted">—</span>}</td>)}</tr>)}</tbody></table></div></section></AppLayout>;
}
