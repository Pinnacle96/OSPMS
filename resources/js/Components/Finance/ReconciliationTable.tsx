import {Link} from '@inertiajs/react';
import Pagination from '@/Components/Data/Pagination';
import StatusBadge from '@/Components/Data/StatusBadge';
import {EmptyState} from '@/Components/Feedback/States';
import MoneyDisplay from './MoneyDisplay';
import {label,type ItemPage} from '@/types/reconciliation';
export default function ReconciliationTable({items}:{items:ItemPage}){return <section className="panel">{items.data.length?<div className="table-scroll" tabIndex={0} role="region" aria-label="Reconciliation findings"><table><thead><tr>{['Ticket / source','Expected','Recorded','Difference','Status','Finding'].map(t=><th scope="col" key={t}>{t}</th>)}</tr></thead><tbody>{items.data.map(i=><tr key={i.id}><td><Link className="text-link" href={i.exception_url}>{i.ticket_reference??i.payment_reference??`Ledger finding ${i.id}`}</Link></td><td><MoneyDisplay amount={i.expected_amount}/></td><td><MoneyDisplay amount={i.actual_amount}/></td><td><MoneyDisplay amount={i.difference_amount}/></td><td><StatusBadge status={i.status}/></td><td>{i.exception_type?label(i.exception_type):'All checks matched'}</td></tr>)}</tbody></table></div>:<EmptyState title="No findings match these filters" description="Start a reconciliation run or adjust the filters to inspect retained findings."/>}<Pagination page={items}/></section>;}
