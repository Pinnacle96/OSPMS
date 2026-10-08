import {Link} from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/App/PageHeader';
import TicketSummary from '@/Components/Ticketing/TicketSummary';
import {dateTime} from '@/lib/formatters';
import type {TicketDetailProps} from '@/types/ticketing';
export default function Show(props:TicketDetailProps) {
    const {ticket,can_cancel,activities}=props;
    return <AppLayout title={ticket.ticket_reference} breadcrumbs={[{label:'Tickets',href:'/tickets'},{label:ticket.ticket_reference}]}><PageHeader title={ticket.ticket_reference} eyebrow="ISSUED TICKET" description={ticket.result} actions={<><Link className="button secondary" href={'/tickets/'+ticket.public_id+'/print'}>Print ticket</Link>{can_cancel&&ticket.ticket_status==='pending'&&['unpaid','failed'].includes(ticket.payment_status)&&<Link className="button danger" href={'/tickets/'+ticket.public_id+'/cancel'}>Cancel ticket</Link>}</>}/><section className="panel"><div className="panel-body"><TicketSummary {...props}/></div></section><section className="panel ticket-review"><div className="panel-header"><h2>Ticket activity</h2></div><div className="panel-body">{activities.length?<ul className="activity-list">{activities.map((a,i)=><li key={i}><span className="activity-dot"/><div><strong>{a.description.replaceAll('_',' ')}</strong><p>{dateTime(a.created_at)}</p>{a.properties.reason&&<p>{a.properties.reason}</p>}</div></li>)}</ul>:<p className="muted small">Activity history is available to authorized oversight accounts.</p>}</div></section></AppLayout>;
}
