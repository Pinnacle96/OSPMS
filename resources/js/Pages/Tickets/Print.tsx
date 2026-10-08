import {Link} from '@inertiajs/react';
import PublicLayout from '@/Layouts/PublicLayout';
import TicketSummary from '@/Components/Ticketing/TicketSummary';
import type {TicketDetailProps} from '@/types/ticketing';
export default function Print(props:TicketDetailProps) {
    return <PublicLayout title="Print ticket"><div className="print-actions"><Link className="button secondary" href={'/tickets/'+props.ticket.public_id}>Back to ticket</Link><button className="button" onClick={()=>window.print()}>Print ticket</button></div><article className="panel printable-ticket"><div className="panel-header"><div><p className="eyebrow">DIGITAL PARK TICKET</p><h1 className="ticket-reference">{props.ticket.ticket_reference}</h1></div></div><div className="panel-body"><TicketSummary {...props}/></div></article></PublicLayout>;
}
