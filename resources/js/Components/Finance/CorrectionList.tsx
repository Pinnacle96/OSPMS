import {Link,useForm} from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/App/PageHeader';
import FormField from '@/Components/Forms/FormField';
import FinanceListControls from '@/Components/Finance/FinanceListControls';
import MoneyDisplay from '@/Components/Finance/MoneyDisplay';
import StatusBadge from '@/Components/Data/StatusBadge';
import Pagination from '@/Components/Data/Pagination';
import {EmptyState} from '@/Components/Feedback/States';
import {dateTime} from '@/lib/formatters';
import type {Paginated} from '@/types';
import type {Correction,CorrectionFilters} from '@/types/corrections';
export default function CorrectionList({records,filters,refund,can_create=false}:{records:Paginated<Correction>;filters:CorrectionFilters;refund:boolean;can_create?:boolean}) {
 const title=refund?'Refund requests':'Financial adjustments';const base=refund?'/finance/refunds':'/finance/adjustments';
 const form=useForm({search:filters.search??'',status:filters.status??'',from:filters.from??'',to:filters.to??'',sort:filters.sort??'requested_at',order:filters.order??'desc'});
 return <AppLayout title={title} breadcrumbs={[{label:title}]}><PageHeader title={title} description="Retained requests, independent approval and complete correction history." actions={can_create&&<Link className="button" href={base+'/create'}>Request adjustment</Link>}/>{refund&&<p className="notice">Open an authorized successful payment to request a refund.</p>}<form className="filter-bar" onSubmit={e=>{e.preventDefault();form.get(base);}}><FormField id="correction-search" label="Reference"><input id="correction-search" maxLength={190} value={form.data.search} onChange={e=>form.setData('search',e.target.value)}/></FormField><FormField id="correction-status" label="Status"><select id="correction-status" value={form.data.status} onChange={e=>form.setData('status',e.target.value)}><option value="">All statuses</option>{(refund?['requested','approved','rejected','processing','successful','failed']:['requested','approved','rejected']).map(s=><option key={s}>{s}</option>)}</select></FormField><FinanceListControls values={form.data} onChange={(k,v)=>form.setData(k,v)} sorts={[[refund?'refund_reference':'adjustment_reference','Reference'],['requested_at','Requested date'],['amount','Amount']]} dateLabel="Requested" errors={form.errors}/><button className="button" disabled={form.processing}>{form.processing?'Applying…':'Apply filters'}</button><Link className="button secondary" href={base}>Reset</Link></form><section className="panel">{records.data.length?<div className="table-scroll" tabIndex={0} role="region" aria-label={title}><table><thead><tr>{['Reference','Direction','Amount','Status','Requested at'].map(s=><th scope="col" key={s}>{s}</th>)}</tr></thead><tbody>{records.data.map(r=><tr key={r.public_id}><td><Link className="text-link" href={base+'/'+r.public_id}>{r.reference}</Link></td><td>{r.direction}</td><td><MoneyDisplay amount={r.amount} currency={r.currency}/></td><td><StatusBadge status={r.status}/></td><td>{dateTime(r.requested_at)}</td></tr>)}</tbody></table></div>:<EmptyState title={'No '+(refund?'refund requests':'adjustments')} description="Change the filters or submit an authorized financial request."/>}<Pagination page={records}/></section></AppLayout>;
}
