import { Link } from '@inertiajs/react';
import PublicLayout from '@/Layouts/PublicLayout';
export default function ErrorPage({ status, title, description }: { status: number; title: string; description: string }) { return <PublicLayout title={title}><div className="panel error-page"><div className="error-code">{status}</div><h1>{title}</h1><p>{description}</p><Link href={status === 419 ? '/login' : '/'} className="button">{status === 419 ? 'Return to sign in' : 'Return to workspace'}</Link></div></PublicLayout>; }
