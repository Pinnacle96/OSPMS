import { useForm, usePage } from '@inertiajs/react';
import { Wallet, ReceiptText, MapPin, Users, BusFront, ContactRound, CircleCheck, CircleAlert, ListChecks, RefreshCw, ShieldCheck, Info, Clock3 } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/App/PageHeader';
import StatCard from '@/Components/Data/StatCard';
import { EmptyState } from '@/Components/Feedback/States';
import DateRangePicker from '@/Components/Forms/DateRangePicker';
import MoneyDisplay from '@/Components/Finance/MoneyDisplay';
import { dateTime } from '@/lib/formatters';
import type { SharedProps } from '@/types';

type Metric = { label: string; value: string | number; money?: boolean; icon: string; note: string };
type Props = { metrics: Metric[]; scope_label: string; lga_count: number; activities: { description: string; created_at: string }[]; filters: { from: string; to: string }; as_of: string };
const icons = { wallet: Wallet, receipt: ReceiptText, park: MapPin, users: Users, vehicle: BusFront, driver: ContactRound, check: CircleCheck, alert: CircleAlert, reconcile: ListChecks };
export default function State({ metrics, scope_label, lga_count, activities, filters, as_of }: Props) {
    const { system } = usePage<SharedProps>().props;
    const form = useForm(filters);
    return <AppLayout title="State Dashboard" breadcrumbs={[{ label: 'Overview' }, { label: 'State Dashboard' }]}>
        <PageHeader eyebrow="STATEWIDE OVERSIGHT" title="State Dashboard" description="A clear view of park operations, collections and financial accountability." actions={<form className="page-actions" onSubmit={e => { e.preventDefault(); form.get('/dashboard', { preserveState: true, preserveScroll: true }); }}><DateRangePicker {...form.data} onChange={(key, value) => form.setData(key, value)} errors={form.errors} /><button className="button secondary" disabled={form.processing} type="submit"><RefreshCw size={15} />Apply</button></form>} />
        <div className="scope-strip"><span><ShieldCheck size={14} />Data scope: <strong>{scope_label}</strong><span>• {lga_count} LGAs registered</span></span><span><Clock3 size={13} />Updated {dateTime(as_of, system.timezone)}</span></div>
        <div className="section-heading"><h2>Operational overview</h2><span>Today • {system.timezone}</span></div>
        <div className="stat-grid">{metrics.map(metric => <StatCard key={metric.label} label={metric.label} value={metric.money ? <MoneyDisplay amount={String(metric.value)} currency={system.currency} /> : Number(metric.value).toLocaleString('en-NG')} note={metric.note} icon={icons[metric.icon as keyof typeof icons]} />)}</div>
        <div className="dashboard-grid"><section className="panel"><div className="panel-header"><div><h2>Revenue trend</h2><p>Recorded collections over the selected period</p></div><span className="panel-tag">{system.currency}</span></div><div className="trend-placeholder"><EmptyState title="No revenue recorded" description="Revenue activity will appear here when ticketing and payments are enabled." /></div></section>
            <section className="panel"><div className="panel-header"><div><h2>Payment status</h2><p>Successful, pending and failed payments</p></div><ReceiptText size={17} className="muted" /></div><EmptyState title="No payment activity" description="There are no payment attempts for this period." /></section></div>
        <div className="dashboard-grid"><section className="panel"><div className="panel-header"><div><h2>Revenue distribution</h2><p>Understand where collections originate</p></div><span className="panel-tag">Selected period</span></div><div className="summary-split"><div><h3>BY LOCAL GOVERNMENT</h3><EmptyState compact title="No LGA collections" description="Collection totals will be grouped by LGA." /></div><div><h3>BY MOTOR PARK</h3><EmptyState compact title="No park collections" description="Collection totals will be grouped by park." /></div></div></section>
            <section className="panel"><div className="panel-header"><div><h2>Recent activity</h2><p>Latest activity visible to your account</p></div><Clock3 size={17} className="muted" /></div>{activities.length ? <ul className="activity-list">{activities.map((activity, i) => <li key={i}><span className="activity-dot" /><div><strong>{activity.description.replaceAll('_', ' ')}</strong><p>{dateTime(activity.created_at, system.timezone)}</p></div></li>)}</ul> : <EmptyState compact title="No activity in this period" description="Recorded activity will appear here." />}</section></div>
        <section className="panel" style={{ marginBottom: 20 }}><div className="panel-header"><div><h2>Recent exceptions</h2><p>Items requiring financial review</p></div><span className="status-badge neutral">0 items</span></div><EmptyState compact title="No reconciliation records" description="Exceptions will appear here when reconciliation is enabled." /></section>
        <div className="foundation-note"><Info size={19} /><div><strong>Registry demonstration</strong><p>LGA, Park and Route records are available. Operator, Driver, Vehicle and financial workflows await their approved milestones. Unavailable measures show zero; no collections or growth figures have been simulated.</p></div></div>
    </AppLayout>;
}
