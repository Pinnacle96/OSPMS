import { usePage } from '@inertiajs/react';
import { ShieldCheck, Clock3 } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/App/PageHeader';
import { EmptyState } from '@/Components/Feedback/States';
import DashboardFilters from './DashboardFilters';
import MetricGrid from './MetricGrid';
import RevenueInsights from './RevenueInsights';
import { dateTime } from '@/lib/formatters';
import type { SharedProps } from '@/types';
import type { DashboardProps } from '@/types/dashboards';

export default function OverviewDashboard({ mode = 'state', ...props }: DashboardProps & { mode?: 'state' | 'executive' | 'revenue' }) {
    const { system } = usePage<SharedProps>().props;
    const title = mode === 'state' ? 'State Dashboard' : mode === 'executive' ? 'Executive Dashboard' : 'Revenue Dashboard';
    const path = mode === 'state' ? '/dashboard' : mode === 'executive' ? '/executive/dashboard' : '/finance/dashboard';
    return <AppLayout title={title} breadcrumbs={[{ label: 'Overview' }, { label: title }]}>
        <PageHeader eyebrow={mode === 'revenue' ? 'FINANCIAL OVERSIGHT' : 'STATEWIDE OVERSIGHT'} title={title} description={mode === 'executive' ? 'Read-only oversight of operations and recorded collections.' : 'Park operations and revenue from committed financial records.'} />
        <div className="scope-strip"><span><ShieldCheck size={14} />Data scope: <strong>{props.scope_label}</strong><span>• {props.lga_count} accessible LGAs</span></span><span><Clock3 size={13} />Updated {dateTime(props.as_of, system.timezone)}</span></div>
        {system.demo_mode && <div className="notice demo-payment-banner"><strong>DEMO PAYMENT DATA</strong> — No real funds were charged.</div>}
        <DashboardFilters key={JSON.stringify(props.filters)} filters={props.filters} finance={props.finance} path={path} />
        <div className="section-heading"><h2>Operational overview</h2><span>Today and current registry access</span></div><MetricGrid metrics={props.metrics} />
        <RevenueInsights finance={props.finance} />
        {mode !== 'revenue' && <section className="panel revenue-panel"><div className="panel-header"><div><h2>Recent activity</h2><p>Activity visible to your account in selected period</p></div></div>{props.activities.length ? <ul className="activity-list">{props.activities.map((activity, index) => <li key={index}><span className="activity-dot" /><div><strong>{activity.description.replaceAll('_', ' ')}</strong><p>{dateTime(activity.created_at, system.timezone)}</p></div></li>)}</ul> : <EmptyState title="No activity in this period" description="Adjust the date range to view recorded activity." />}</section>}
    </AppLayout>;
}
