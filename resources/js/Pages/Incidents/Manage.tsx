import {Link} from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/App/PageHeader';
import StatusBadge from '@/Components/Data/StatusBadge';
import ReviewForm from '@/Components/Incidents/ReviewForm';
import type {OperationalRecord} from '@/types/incidents';
export default function Manage({record,transitions,idempotency_key}:{record:OperationalRecord;transitions:string[];idempotency_key:string}){return <AppLayout title="Manage incident" retainFormOnError><PageHeader title="Manage incident" description={record.reference}/><section className="panel"><div className="panel-body"><StatusBadge status={record.status}/><p className="incident-text">{record.description}</p><ReviewForm key={idempotency_key} record={record} transitions={transitions} incident idempotency_key={idempotency_key}/></div></section><Link className="text-link" href={'/incidents/'+record.public_id}>Return to incident</Link></AppLayout>;}
