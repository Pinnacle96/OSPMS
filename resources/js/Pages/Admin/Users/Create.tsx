import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/App/PageHeader';
import UserForm from '@/Components/Forms/UserForm';
export default function Create({ roles }: { roles: string[] }) { return <AppLayout title="Create User" breadcrumbs={[{ label: 'Users', href: '/admin/users' }, { label: 'Create user' }]}><PageHeader title="Create user" description="Create an account and assign its initial role." /><UserForm roles={roles} /></AppLayout>; }
