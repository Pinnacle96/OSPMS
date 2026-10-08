import {Link} from '@inertiajs/react';
import DataTable,{type Column} from '@/Components/Data/DataTable';
import StatusBadge from '@/Components/Data/StatusBadge';
import MoneyDisplay from '@/Components/Finance/MoneyDisplay';
import {dateTime} from '@/lib/formatters';
import type {FinancialPage,FinancialRow} from '@/types/payments';
export default function FinancialTable({records,ledger=false,loading=false,sort,order,onSort}:{records:FinancialPage;ledger?:boolean;loading?:boolean;sort?:string;order?:string;onSort?:(field:string)=>void}) {
 const columns:Column<FinancialRow>[]=[{key:'reference',label:ledger?'Transaction':'Payment',sort:ledger?'transaction_reference':'payment_reference',render:r=><Link className="text-link" href={(ledger?'/finance/ledger/':'/payments/')+r.public_id}>{ledger?r.transaction_reference:r.payment_reference}</Link>},{key:'ticket',label:'Ticket',render:r=>r.ticket_reference??'—'},{key:'amount',label:'Amount',sort:'amount',render:r=><MoneyDisplay amount={r.amount} currency={r.currency}/>},{key:'status',label:ledger?'Direction':'Status',render:r=><StatusBadge status={ledger?r.direction??'':r.status??''}/>},{key:'type',label:ledger?'Type':'Channel',render:r=>ledger?r.transaction_type:r.channel},{key:'date',label:ledger?'Occurred at':'Initiated at',sort:ledger?'occurred_at':'initiated_at',render:r=>dateTime(ledger?r.occurred_at:r.initiated_at)}];
 return <DataTable columns={columns} rows={records.data} page={records} rowKey={r=>r.public_id} loading={loading} sort={sort} direction={order} onSort={onSort}/>;
}
