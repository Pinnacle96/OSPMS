import {Link} from '@inertiajs/react';
import DataTable from '@/Components/Data/DataTable';
import StatusBadge from '@/Components/Data/StatusBadge';
import MoneyDisplay from '@/Components/Finance/MoneyDisplay';
import {dateTime} from '@/lib/formatters';
import type {TicketPage} from '@/types/ticketing';
export default function TicketTable({records,loading,sort,direction,onSort}:{records:TicketPage;loading?:boolean;sort?:string;direction?:string;onSort?:(column:string)=>void}) {
    return <DataTable rows={records.data} page={records} loading={loading} rowKey={r=>r.public_id} sort={sort} direction={direction} onSort={onSort} columns={[
        {key:'reference',label:'Ticket reference',sort:'ticket_reference',render:r=><Link className="text-link ticket-reference" href={'/tickets/'+r.public_id}>{r.ticket_reference}</Link>},
        {key:'context',label:'Vehicle / park',render:r=><><strong>{r.vehicle??'Not recorded'}</strong><p className="muted small">{r.park}</p></>},
        {key:'fee',label:'Revenue head',render:r=>r.fee_name_snapshot},
        {key:'amount',label:'Amount',sort:'amount',render:r=><MoneyDisplay amount={r.amount} currency={r.currency}/>},
        {key:'status',label:'Ticket / payment',render:r=><div className="ticket-statuses"><StatusBadge status={r.ticket_status}/><StatusBadge status={r.payment_status}/></div>},
        {key:'issued',label:'Issued',sort:'issued_at',render:r=>dateTime(r.issued_at)},
    ]}/>;
}
