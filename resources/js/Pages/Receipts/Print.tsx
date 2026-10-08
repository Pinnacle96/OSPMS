import {Link} from '@inertiajs/react';
import PublicLayout from '@/Layouts/PublicLayout';
import ReceiptSummary from '@/Components/Finance/ReceiptSummary';
import type {ReceiptProps} from '@/types/payments';
export default function Print(props:ReceiptProps){return <PublicLayout title={'Receipt '+props.receipt.receipt_number}><div className="print-actions"><button className="button" onClick={()=>window.print()}>Print receipt</button><Link className="button secondary" href={'/receipts/'+props.receipt.public_id}>Back to receipt</Link></div><article className="panel printable-ticket"><div className="panel-body"><h1>Digital payment receipt</h1><ReceiptSummary {...props}/></div></article></PublicLayout>;}
