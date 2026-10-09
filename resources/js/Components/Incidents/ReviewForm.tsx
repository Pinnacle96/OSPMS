import {useForm} from '@inertiajs/react';
import {useState} from 'react';
import FormField from '@/Components/Forms/FormField';
import ConfirmDialog from '@/Components/Feedback/ConfirmDialog';
import useFieldOnline from '@/hooks/useFieldOnline';
import type {OperationalRecord} from '@/types/incidents';
export default function ReviewForm({record,idempotency_key,transitions,incident=false}:{record:OperationalRecord;idempotency_key:string;transitions:string[];incident?:boolean}){
 const online=useFieldOnline(),[confirm,setConfirm]=useState(false),f=useForm({expected_status:record.status,status:transitions[0]??'',resolution:'',idempotency_key});
 if(!transitions.length)return <p className="notice">This record is closed. Its history is retained.</p>;
 return <><form className="payment-form" onSubmit={e=>{e.preventDefault();if(online&&!f.processing)setConfirm(true);}}>{incident&&<FormField id="review-status" label="Next status" error={f.errors.status}><select id="review-status" value={f.data.status} onChange={e=>f.setData('status',e.target.value)}>{transitions.map(s=><option key={s} value={s}>{s.replaceAll('_',' ')}</option>)}</select></FormField>}<FormField id="review-resolution" label={incident&&f.data.status!=='resolved'?'Reason for status change':'Resolution'} error={f.errors.resolution}><textarea id="review-resolution" required minLength={10} maxLength={4000} rows={5} value={f.data.resolution} onChange={e=>f.setData('resolution',e.target.value)}/></FormField>{f.errors.idempotency_key&&<p role="alert">{f.errors.idempotency_key}</p>}<button className="button" disabled={!online||f.processing}>{f.processing?'Saving…':incident?'Review status change':'Review resolution'}</button></form><ConfirmDialog open={confirm} title={incident?'Confirm incident status':'Confirm violation resolution'} description="Save this review with your identity and a retained reason? Another review that changes the record will require you to refresh." onClose={()=>setConfirm(false)} onConfirm={()=>{setConfirm(false);f.post('/'+(incident?'incidents':'violations')+'/'+record.public_id+(incident?'/manage':'/resolve'),{preserveScroll:true});}}/></>;
}
