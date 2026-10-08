import {Link} from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/App/PageHeader';
import ReceiptSummary from '@/Components/Finance/ReceiptSummary';
import type {ReceiptProps} from '@/types/payments';
export default function Show(props:ReceiptProps){const r=props.receipt;return <AppLayout title={r.receipt_number} breadcrumbs={[{label:'Payments',href:'/payments'},{label:r.receipt_number}]}><PageHeader title={r.receipt_number} description="Canonical receipt with current verification status." actions={<><Link className="button secondary" href={'/receipts/'+r.public_id+'/print'}>Print receipt</Link><a className="button" href={'/receipts/'+r.public_id+'/pdf'}>Download PDF</a></>}/><section className="panel"><div className="panel-body"><ReceiptSummary {...props}/><div className="form-actions"><Link className="text-link" href={'/payments/'+r.payment_public_id}>View payment</Link><Link className="text-link" href={'/tickets/'+r.ticket_public_id}>View ticket</Link></div></div></section></AppLayout>;}
