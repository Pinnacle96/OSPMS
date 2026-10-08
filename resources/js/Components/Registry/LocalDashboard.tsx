import { Link, usePage } from '@inertiajs/react';
import { ShieldCheck, Clock3 } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/App/PageHeader';
import StatusBadge from '@/Components/Data/StatusBadge';
import { EmptyState } from '@/Components/Feedback/States';
import DashboardFilters from '@/Components/Dashboards/DashboardFilters';
import MetricGrid from '@/Components/Dashboards/MetricGrid';
import RevenueInsights from '@/Components/Dashboards/RevenueInsights';
import { dateTime } from '@/lib/formatters';
import type { SharedProps } from '@/types';
import type { RegistryRecord } from '@/types/registry';
import type { Metric, DashboardFilters as Filters, FinanceData } from '@/types/dashboards';

export type LocalDashboardProps = { record: RegistryRecord; kind: 'lgas' | 'parks'; metrics: Metric[]; as_of: string; filters: Filters; finance: FinanceData | null };
export default function LocalDashboard({ record, kind, metrics, as_of, filters, finance }: LocalDashboardProps) {
    const { system } = usePage<SharedProps>().props;
    const title = kind === 'lgas' ? 'LGA Dashboard' : 'Park Dashboard';
    const path = `/${kind}/${record.public_id}/dashboard`;
    return <AppLayout title={title} breadcrumbs={[{ label: kind === 'lgas' ? 'LGAs' : 'Parks', href: `/${kind}` }, { label: record.name ?? '', href: `/${kind}/${record.public_id}` }, { label: 'Dashboard' }]}>
        <PageHeader eyebrow="LOCAL OPERATIONS" title={title} description={record.name} actions={<Link className="button secondary" href={`/${kind}/${record.public_id}`}>View profile</Link>} />
        <div className="scope-strip"><span><ShieldCheck size={14} />Data scope: <strong>{record.name}</strong><StatusBadge status={record.status} /></span><span><Clock3 size={14} />Updated {dateTime(as_of, system.timezone)}</span></div>
        {system.demo_mode && <div className="notice demo-payment-banner"><strong>DEMO PAYMENT DATA</strong> — No real funds were charged.</div>}
        <DashboardFilters key={JSON.stringify(filters)} filters={filters} finance={finance} path={path} local={kind} />
        <div className="section-heading"><h2>Operational overview</h2><span>Current registry access</span></div><MetricGrid metrics={metrics} />
        <RevenueInsights finance={finance} />
        {kind === 'lgas' ? <section className="panel panel-body"><Link className="text-link" href={`/parks?lga_id=${record.id ?? ''}`}>Browse parks in this LGA →</Link><p className="notice">Only parks within your assigned access are shown.</p></section> : <section className="panel"><EmptyState title="Compliance and incidents not yet available" description="These workflows remain in their approved later milestones." /></section>}
    </AppLayout>;
}
