import {Link,router,usePage} from '@inertiajs/react';
import {useState} from 'react';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/App/PageHeader';
import FormField from '@/Components/Forms/FormField';
import TicketTable from '@/Components/Ticketing/TicketTable';
import {ticketStatuses,paymentStatuses,type TicketPage} from '@/types/ticketing';
type Option={id:number;name:string};
export default function Index({records,filters,can_create,parks,lgas,revenue_heads}:{records:TicketPage;filters:Record<string,string>;can_create:boolean;parks:Option[];lgas:Option[];revenue_heads:Option[]}) {
    const errors=usePage().props.errors;
    const [values,setValues]=useState(filters); const [loading,setLoading]=useState(false);
    const apply=(extra:Record<string,string>={})=>router.get('/tickets',{...values,...extra},{preserveState:true,replace:true,onStart:()=>setLoading(true),onFinish:()=>setLoading(false)});
    return <AppLayout title="Tickets" breadcrumbs={[{label:'Ticketing'},{label:'Tickets'}]}><PageHeader title="Tickets" description="Issued obligations, original fees and current ticket status within your access." actions={can_create&&<Link className="button" href="/tickets/create">Issue ticket</Link>}/>{Object.values(errors).length>0&&<div className="notice field-error" role="alert">{Object.entries(errors).map(([key,message])=><p key={key}>{message}</p>)}</div>}<section className="panel"><form className="filter-bar" onSubmit={e=>{e.preventDefault();apply({page:'1'});}}>
        <FormField id="ticket-search" label="Search tickets"><input id="ticket-search" value={values.search??''} maxLength={190} placeholder="Reference, vehicle, driver or operator" onChange={e=>setValues({...values,search:e.target.value})}/></FormField>
        {([['ticket_status','Ticket status',ticketStatuses],['payment_status','Payment status',paymentStatuses]] as const).map(([key,label,list])=><FormField key={key} id={key} label={label}><select id={key} value={values[key]??''} onChange={e=>setValues({...values,[key]:e.target.value})}><option value="">All statuses</option>{list.map(s=><option key={s} value={s}>{s}</option>)}</select></FormField>)}
        {([['lga_id','LGA',lgas],['park_id','Park',parks],['revenue_head_id','Revenue head',revenue_heads]] as const).map(([key,label,list])=><FormField key={key} id={key} label={label}><select id={key} value={values[key]??''} onChange={e=>setValues({...values,[key]:e.target.value})}><option value="">All accessible records</option>{list.map(o=><option key={o.id} value={o.id}>{o.name}</option>)}</select></FormField>)}
        {(['from','to'] as const).map(key=><FormField key={key} id={key} label={(key==='from'?'From':'To')+' (UTC date)'}><input id={key} type="date" value={values[key]??''} onChange={e=>setValues({...values,[key]:e.target.value})}/></FormField>)}<button className="button secondary">Apply filters</button><Link className="text-link small" href="/tickets">Clear</Link>
        </form><TicketTable records={records} loading={loading} sort={filters.sort} direction={filters.direction} onSort={column=>apply({sort:column,direction:filters.sort===column&&filters.direction==='asc'?'desc':'asc'})}/></section></AppLayout>;
}
