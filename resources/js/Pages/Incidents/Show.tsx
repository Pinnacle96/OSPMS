import {Link} from '@inertiajs/react';
import RecordDetail from '@/Components/Incidents/RecordDetail';
import type {OperationalRecord} from '@/types/incidents';
export default function Show({record,can_manage,field,idempotency_key}:{record:OperationalRecord;can_manage:boolean;field:boolean;idempotency_key:string}){return <RecordDetail record={record} kind="incidents" field={field} idempotency_key={idempotency_key} actions={can_manage&&record.status!=='closed'&&<Link className="button" href={'/incidents/'+record.public_id+'/manage'}>Manage incident</Link>}/>;}
