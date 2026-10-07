import { Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { Pencil, LayoutDashboard, Archive } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/App/PageHeader';
import DataTable from '@/Components/Data/DataTable';
import StatusBadge from '@/Components/Data/StatusBadge';
import ConfirmDialog from '@/Components/Feedback/ConfirmDialog';
import { EmptyState } from '@/Components/Feedback/States';
import RouteAssignment from './RouteAssignment';
import { dateTime } from '@/lib/formatters';
import { usePermissions } from '@/hooks/usePermissions';
import type { SharedProps } from '@/types';
import { registry,recordName,type Kind,type DetailProps } from '@/types/registry';
const tabs:Record<Kind,string[]> = {
    lgas:['Overview','Parks','Operators','Drivers','Vehicles','Revenue','Recent Activity'],
    parks:['Overview','Operators','Drivers','Vehicles','Routes','Tickets','Revenue','Incidents','Activity'],
    routes:['Overview','Parks','Operators','Activity'],
};
export default function RegistryDetail({ kind,record,parks,assigned_routes,route_options=[],selected_route_ids=[],can_assign_routes=false,can_update,can_archive,activities,can_view_lga=false,managers=[] }: DetailProps & {kind:Kind;can_view_lga?:boolean;managers?:{name:string}[]}) {
    const [tab,setTab]=useState('Overview');
    const [confirm,setConfirm]=useState(false);
    const [archiveError,setArchiveError]=useState('');
    const { can }=usePermissions();
    const { system }=usePage<SharedProps>().props;
    const meta=registry[kind];
    const fields: [string,string | undefined | null][] = kind === 'lgas' ? [
        ['LGA code',record.code],['Administrative contact',record.administrative_contact_name],['Contact phone',record.administrative_contact_phone],['Contact email',record.administrative_contact_email],
    ] : kind === 'parks' ? [
        ['Park code',record.park_code],['Local government',record.lga?.name],['Address',record.address],['Park category',record.category],['Contact phone',record.contact_phone],
        ['Coordinates',record.latitude != null && record.longitude != null ? `${record.latitude}, ${record.longitude}`:'Not recorded'],
        ['First activated',record.activated_at ? dateTime(record.activated_at,system.timezone):'Not activated'],['Assigned managers',managers.length ? managers.map(manager => manager.name).join(', '):'No manager assigned'],
    ] : [['Route code',record.route_code],['Origin',record.origin],['Destination',record.destination],['Description',record.description]];
    const activityTab=tab === 'Activity' || tab === 'Recent Activity';
    return <AppLayout title={recordName(record)} breadcrumbs={[{label:'Operations'},{label:meta.title,href:`/${kind}`},{label:recordName(record)}]}>
        <PageHeader eyebrow={`${meta.singular.toUpperCase()} PROFILE`} title={recordName(record)} description={record.code ?? record.park_code ?? record.route_code} actions={<>{kind !== 'routes' && <Link className="button secondary" href={`/${kind}/${record.public_id}/dashboard`}><LayoutDashboard size={16} />Dashboard</Link>}{can_update && <Link className="button" href={`/${kind}/${record.public_id}/edit`}><Pencil size={15} />Edit {meta.singular}</Link>}</>} />
        <div className="scope-strip"><span>Operational status <StatusBadge status={record.status} /></span><span>Registered {dateTime(record.created_at,system.timezone)}</span></div>
        <div className="registry-tabs" role="tablist" aria-label={`${meta.singular} sections`} onKeyDown={event => {
            if (!['ArrowRight','ArrowLeft','Home','End'].includes(event.key)) return;
            event.preventDefault();
            const index=tabs[kind].indexOf(tab);
            const next=event.key === 'Home' ? 0:event.key === 'End' ? tabs[kind].length-1:(index+(event.key === 'ArrowRight' ? 1:-1)+tabs[kind].length)%tabs[kind].length;
            setTab(tabs[kind][next]);
            document.getElementById(`registry-tab-${next}`)?.focus();
        }}>{tabs[kind].map((name,index) => <button key={name} id={`registry-tab-${index}`} role="tab" type="button" aria-selected={tab === name} aria-controls="registry-tab-panel" tabIndex={tab === name ? 0:-1} onClick={() => setTab(name)}>{name}</button>)}</div>
        <div role="tabpanel" id="registry-tab-panel" aria-labelledby={`registry-tab-${tabs[kind].indexOf(tab)}`} tabIndex={0}>
        {tab === 'Overview' ? <div className="detail-grid"><section className="panel"><div className="panel-header"><div><h2>{meta.singular} details</h2><p>Core registration and administrative information</p></div></div><div className="panel-body"><dl className="detail-list">{fields.map(([label,value]) => <div key={label}><dt>{label}</dt><dd>{value || 'Not recorded'}</dd></div>)}</dl>{kind === 'parks' && can_view_lga && record.lga?.public_id && <p className="registry-related-link"><Link className="text-link small" href={`/lgas/${record.lga.public_id}`}>View local government profile →</Link></p>}</div></section>
            <section className="panel"><div className="panel-header"><div><h2>Registry connections</h2><p>Related records and operational context</p></div></div><div className="panel-body"><p className="muted small">{kind === 'parks' ? 'Approved transport routes are managed in the Routes tab.':`${parks?.total ?? 0} associated parks are available to your account.`}</p><p className="notice">Operator, Driver, Vehicle and financial services are not available yet. Revenue and collection measures remain zero.</p>{can_archive && <><button className="button secondary" type="button" onClick={() => setConfirm(true)}><Archive size={15} />Archive {meta.singular}</button><p className="field-hint">Only records without linked history can be archived. Otherwise use inactive status.</p></>}</div></section></div> :
        tab === 'Parks' ? <section className="panel"><div className="panel-header"><div><h2>Associated parks</h2><p>Only parks within your access scope are shown</p></div></div>{parks ? <DataTable rows={parks.data} page={parks} rowKey={park => park.public_id} columns={[
            { key:'name',label:'Park',render:park => <Link className="text-link" href={`/parks/${park.public_id}`}>{park.name}</Link> },
            { key:'code',label:'Park code',render:park => park.park_code },
            { key:'status',label:'Status',render:park => <StatusBadge status={park.status} /> },
        ]} />:<EmptyState title="Park records unavailable" description="Your account does not have permission to view this registry." />}</section> :
        tab === 'Routes' ? <><section className="panel"><div className="panel-header"><div><h2>Route assignments</h2><p>Current assignments and retained route history</p></div></div>{assigned_routes && <DataTable rows={assigned_routes.data} page={assigned_routes} rowKey={route => route.public_id} columns={[
            { key:'route',label:'Route',render:route => can('view_route') ? <Link className="text-link" href={`/routes/${route.public_id}`}>{recordName(route)}</Link>:recordName(route) },
            { key:'code',label:'Code',render:route => route.route_code },
            { key:'status',label:'Route status',render:route => <StatusBadge status={route.status} /> },
            { key:'assignment',label:'Assignment',render:route => <StatusBadge status={route.pivot?.status ?? 'inactive'} /> },
        ]} />}</section>{can_assign_routes && <RouteAssignment key={JSON.stringify(selected_route_ids)} park={record} options={route_options} selected={selected_route_ids} />}</> :
        activityTab ? <section className="panel"><div className="panel-header"><div><h2>Recent activity</h2><p>Latest audited changes to this record</p></div></div>{activities.length ? <ul className="activity-list">{activities.map((activity,index) => <li key={index}><span className="activity-dot" /><div><strong>{activity.description.replaceAll('_',' ')}</strong><p>{dateTime(activity.created_at,system.timezone)}</p></div></li>)}</ul>:<EmptyState title={can('view_audit_log') ? 'No activity recorded':'Audit history restricted'} description={can('view_audit_log') ? 'Changes to this record will appear here.':'Audit history is available to authorized oversight accounts.'} />}</section> :
        <section className="panel"><div className="panel-header"><div><h2>{tab}</h2><p>{meta.singular} {tab.toLowerCase()} overview</p></div></div><EmptyState title={tab === 'Revenue' ? 'No collections recorded':`No ${tab.toLowerCase()} available`} description={tab === 'Revenue' ? 'Revenue measures will become available when ticketing and payment services are enabled.':`${tab} services are not available yet. No records have been simulated.`} /></section>}
        </div>{archiveError && <p className="field-error notice" role="alert">{archiveError}</p>}
        <ConfirmDialog open={confirm} title={`Archive ${meta.singular}?`} description="This record will be removed from active registry views. Linked records prevent archiving; audit history is retained." onClose={() => setConfirm(false)} onConfirm={() => { setConfirm(false); setArchiveError(''); router.delete(`/${kind}/${record.public_id}`,{ onError:errors => setArchiveError(errors.archive ?? 'This record could not be archived.') }); }} />
    </AppLayout>;
}
