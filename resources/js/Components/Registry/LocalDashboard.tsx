import { Link,usePage } from '@inertiajs/react';
import { ShieldCheck,Clock3,MapPin,Route,Users,BusFront,ContactRound,Wallet,ReceiptText,ListChecks } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/App/PageHeader';
import StatCard from '@/Components/Data/StatCard';
import { EmptyState } from '@/Components/Feedback/States';
import StatusBadge from '@/Components/Data/StatusBadge';
import MoneyDisplay from '@/Components/Finance/MoneyDisplay';
import { dateTime } from '@/lib/formatters';
import type { SharedProps } from '@/types';
import type { RegistryRecord } from '@/types/registry';
export type LocalDashboardProps={record:RegistryRecord;kind:'lgas'|'parks';metrics:{label:string;value:number|string;money?:boolean;note:string}[];as_of:string};
export default function LocalDashboard({record,kind,metrics,as_of}:LocalDashboardProps) {
    const {system}=usePage<SharedProps>().props;
    const title=kind === 'lgas' ? 'LGA Dashboard':'Park Dashboard';
    return <AppLayout title={title} breadcrumbs={[{label:kind === 'lgas' ? 'LGAs':'Parks',href:`/${kind}`},{label:record.name ?? '',href:`/${kind}/${record.public_id}`},{label:'Dashboard'}]}>
        <PageHeader eyebrow="LOCAL OPERATIONS" title={title} description={record.name} actions={<Link className="button secondary" href={`/${kind}/${record.public_id}`}>View profile</Link>} />
        <div className="scope-strip"><span><ShieldCheck size={14} />Data scope: <strong>{record.name}</strong><StatusBadge status={record.status} /></span><span><Clock3 size={14} />Updated {dateTime(as_of,system.timezone)}</span></div>
        <div className="section-heading"><h2>Operational overview</h2><span>Today • {system.timezone}</span></div><div className="stat-grid">{metrics.map(metric => <StatCard key={metric.label} label={metric.label} value={metric.money ? <MoneyDisplay amount={String(metric.value)} currency={system.currency} />:Number(metric.value).toLocaleString('en-NG')} note={metric.note} icon={metric.money ? Wallet:metric.label.includes('route') ? Route:metric.label.includes('operator') ? Users:metric.label.includes('vehicle') ? BusFront:metric.label.includes('driver') ? ContactRound:metric.label.includes('reconciliation') ? ListChecks:['Tickets','Transactions'].includes(metric.label) ? ReceiptText:MapPin} />)}</div>
        <div className="dashboard-grid"><section className="panel"><div className="panel-header"><div><h2>{kind === 'lgas' ? 'Revenue activity':'Collection and ticket activity'}</h2><p>Recorded transactions within this scope</p></div></div><EmptyState title="No financial activity" description="Ticketing, payments and reconciliation services are not available yet." /></section><section className="panel"><div className="panel-header"><div><h2>{kind === 'parks' ? 'Compliance and incidents':'Registry activity'}</h2><p>Operational records and exceptions</p></div></div>{kind === 'lgas' ? <div className="panel-body"><Link className="text-link" href={`/parks?lga_id=${record.id ?? ''}`}>Browse parks in this LGA →</Link><p className="notice">Only parks within your assigned access are shown.</p></div>:<EmptyState title="No compliance alerts or incidents" description="Compliance and incident services are not available yet." />}</section></div>
        <p className="foundation-note">Registry counts reflect stored records. Unavailable workflows show zero; no financial activity has been simulated.</p>
    </AppLayout>;
}
