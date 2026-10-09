import RecordDetail from '@/Components/Incidents/RecordDetail';
import ReviewForm from '@/Components/Incidents/ReviewForm';
import type {OperationalRecord} from '@/types/incidents';
export default function Show({record,can_resolve,idempotency_key}:{record:OperationalRecord;can_resolve:boolean;idempotency_key:string}){return <RecordDetail record={record} kind="violations" idempotency_key={idempotency_key}>{can_resolve&&<><h2>Resolve violation</h2><ReviewForm key={idempotency_key} record={record} idempotency_key={idempotency_key} transitions={['resolved']}/></>}</RecordDetail>;}
