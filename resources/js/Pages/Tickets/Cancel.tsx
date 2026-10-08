import {Link,useForm} from '@inertiajs/react';
import {useState} from 'react';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/App/PageHeader';
import FormField from '@/Components/Forms/FormField';
import ConfirmDialog from '@/Components/Feedback/ConfirmDialog';
import type {TicketDetailProps} from '@/types/ticketing';
export default function Cancel({ticket}:TicketDetailProps) {
    const form=useForm({reason:''}); const [confirm,setConfirm]=useState(false);
    return <AppLayout title="Cancel ticket" breadcrumbs={[{label:'Tickets',href:'/tickets'},{label:ticket.ticket_reference,href:'/tickets/'+ticket.public_id},{label:'Cancel'}]}><PageHeader title="Cancel ticket" description={ticket.ticket_reference}/><form className="panel form-panel" onSubmit={e=>{e.preventDefault();setConfirm(true);}}><div className="panel-body"><p className="notice">Only an eligible unpaid ticket may be cancelled. Its fee, original context and cancellation reason remain in history.</p><FormField id="reason" label="Cancellation reason" error={form.errors.reason}><textarea id="reason" required maxLength={2000} value={form.data.reason} onChange={e=>form.setData('reason',e.target.value)}/></FormField><div className="form-actions"><Link className="button secondary" href={'/tickets/'+ticket.public_id}>Keep ticket</Link><button className="button danger" disabled={form.processing}>Cancel ticket</button></div></div></form><ConfirmDialog open={confirm} title="Confirm ticket cancellation?" description="This ticket will no longer be valid for use. The reason and your identity will be audited." onClose={()=>setConfirm(false)} onConfirm={()=>{setConfirm(false);form.patch('/tickets/'+ticket.public_id+'/cancel');}}/></AppLayout>;
}
