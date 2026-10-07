import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/App/PageHeader';
import UserForm from '@/Components/Forms/UserForm';
import type { User } from '@/types';
export default function Edit({ user, roles }: { user: User; roles: string[] }) { return <AppLayout title="Edit User" breadcrumbs={[{ label: 'Users', href: '/admin/users' }, { label: user.name, href: `/admin/users/${user.public_id}` }, { label: 'Edit' }]}><PageHeader title="Edit user" description={`Update the account for ${user.name}.`} /><UserForm user={user} roles={roles} /></AppLayout>; }
