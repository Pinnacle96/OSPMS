import {Link} from '@inertiajs/react';
import StatusBadge from '@/Components/Data/StatusBadge';
import MoneyDisplay from '@/Components/Finance/MoneyDisplay';
import QRDisplay from '@/Components/Verification/QRDisplay';
import {dateTime} from '@/lib/formatters';
import type {TicketDetailProps} from '@/types/ticketing';
export default function TicketSummary({ticket,links,verification_url,qr_image}:Pick<TicketDetailProps,'ticket'|'links'|'verification_url'|'qr_image'>) {
    const context=ticket.context;
    const fields:[string,string,string][]=[
        ['Revenue head',ticket.fee_name_snapshot+' · '+ticket.fee_code,'revenueHead'],
        ['LGA',context.lga.name,'lga'], ['Park',context.park.name,'park'],
        ['Operator',context.operator.name,'operator'],['Driver',context.driver.name+' · '+context.driver.reference,'driver'],
        ['Vehicle',context.vehicle.registration,'vehicle'],
        ['Route',context.route?context.route.origin+' → '+context.route.destination:'No route','route'],
        ['Issued by',ticket.issuer,''],['Issued at',dateTime(ticket.issued_at),''],['Expires at',ticket.expires_at?dateTime(ticket.expires_at):'No expiry configured',''],
    ];
    return <div className="ticket-summary"><div><div className="ticket-total"><span className="eyebrow">ISSUED AMOUNT</span><strong><MoneyDisplay amount={ticket.amount} currency={ticket.currency}/></strong><div className="ticket-statuses"><span>Ticket <StatusBadge status={ticket.ticket_status}/></span><span>Payment <StatusBadge status={ticket.payment_status}/></span></div></div><dl className="detail-list">{fields.map(([label,value,key])=><div key={label}><dt>{label}</dt><dd>{links[key]?<Link className="text-link" href={links[key]}>{value}</Link>:value}</dd></div>)}</dl><p className="notice">This summary is a ticket, not a payment receipt. It retains the fee and operating context recorded at issuance.</p></div><QRDisplay image={qr_image} url={verification_url}/></div>;
}
