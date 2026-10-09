import {useForm,router} from '@inertiajs/react';
import {useEffect,useState} from 'react';
import AppLayout from '@/Layouts/AppLayout';
import PublicLayout from '@/Layouts/PublicLayout';
import PageHeader from '@/Components/App/PageHeader';
import FormField from '@/Components/Forms/FormField';
import ConfirmDialog from '@/Components/Feedback/ConfirmDialog';
import useFieldOnline from '@/hooks/useFieldOnline';
import type {Choice} from '@/types/complaints';
import type {FieldAssignment} from '@/types/field';
export type ComplaintFormProps={parks:Choice[];contexts?:FieldAssignment[];idempotency_key:string};
export default function ComplaintForm({parks,contexts=[],idempotency_key,publicForm=false}:ComplaintFormProps&{publicForm?:boolean}){
 const f=useForm({complainant_name:'',complainant_phone:'',complainant_email:'',category:'',park:'',context:'',description:'',file:null as File|null,idempotency_key}),online=useFieldOnline();
 const [confirm,setConfirm]=useState(false),[networkError,setNetworkError]=useState(false);
 useEffect(()=>{if(!publicForm)return;return router.on('exception',e=>{e.preventDefault();setNetworkError(true);});},[publicForm]);
 const title=publicForm?'Submit a complaint':'Record complaint';
 const body=<><PageHeader title={title} description="Tell us what happened. Keep the complaint reference after submission for Help Desk follow-up."/>{networkError&&<p className="notice" role="alert">Connection interrupted. Retry with the same details to recover a possible saved result.</p>}<section className="panel"><div className="panel-body"><form className="payment-form" onSubmit={e=>{e.preventDefault();if(online&&!f.processing)setConfirm(true);}}>
 <FormField id="complainant-name" label="Your name" error={f.errors.complainant_name}><input id="complainant-name" required minLength={2} maxLength={190} autoComplete="name" value={f.data.complainant_name} onChange={e=>f.setData('complainant_name',e.target.value)}/></FormField>
 <FormField id="complainant-phone" label="Phone (optional)" error={f.errors.complainant_phone}><input id="complainant-phone" type="tel" maxLength={30} autoComplete="tel" value={f.data.complainant_phone} onChange={e=>f.setData('complainant_phone',e.target.value)}/></FormField>
 <FormField id="complainant-email" label="Email (optional)" error={f.errors.complainant_email}><input id="complainant-email" type="email" maxLength={190} autoComplete="email" value={f.data.complainant_email} onChange={e=>f.setData('complainant_email',e.target.value)}/></FormField>
 <FormField id="complaint-category" label="Category" error={f.errors.category} hint="Describe the subject, for example service or safety."><input id="complaint-category" required minLength={2} maxLength={80} value={f.data.category} onChange={e=>f.setData('category',e.target.value)}/></FormField>
 <FormField id="complaint-park" label="Park (optional)" error={f.errors.park}><select id="complaint-park" value={f.data.park} onChange={e=>f.setData(d=>({...d,park:e.target.value,context:''}))}><option value="">Park unknown or not listed</option>{parks.map(p=><option key={p.public_id} value={p.public_id}>{p.name}</option>)}</select></FormField>
 {!publicForm&&<FormField id="complaint-context" label="Related operating assignment (optional)" error={f.errors.context}><select id="complaint-context" value={f.data.context} onChange={e=>f.setData('context',e.target.value)}><option value="">No named operating participants</option>{contexts.filter(c=>c.park.public_id===f.data.park).map(c=><option value={c.context} key={c.context}>{c.vehicle.name} · {c.driver.name} · {c.operator.name}</option>)}</select></FormField>}
 <FormField id="complaint-description" label="What happened?" error={f.errors.description}><textarea id="complaint-description" required minLength={10} maxLength={4000} rows={5} value={f.data.description} onChange={e=>f.setData('description',e.target.value)}/></FormField>
 <FormField id="complaint-file" label="Evidence (optional)" error={f.errors.file} hint="JPEG, PNG or PDF up to 5 MB. Stored privately for authorized staff."><input id="complaint-file" type="file" accept="image/jpeg,image/png,application/pdf" onChange={e=>f.setData('file',e.target.files?.[0]??null)}/></FormField>{f.progress&&<progress aria-label="Evidence upload progress" max={100} value={f.progress.percentage}/>}{f.errors.idempotency_key&&<p role="alert">{f.errors.idempotency_key}</p>}{!online&&<p role="status" className="notice">Reconnect before submitting. This form is not saved offline.</p>}<button className="button" disabled={!online||f.processing}>{f.processing?'Submitting…':'Review and submit complaint'}</button>
 </form></div></section><ConfirmDialog open={confirm} title="Confirm complaint submission" description="Submit this report and any selected evidence for Help Desk review?" onClose={()=>setConfirm(false)} onConfirm={()=>{setConfirm(false);if(online&&!f.processing)f.post(publicForm?'/public/complaints':'/complaints',{forceFormData:true,preserveScroll:true,onSuccess:()=>setNetworkError(false)});}}/></>;
 return publicForm?<PublicLayout title={title}>{body}</PublicLayout>:<AppLayout title={title} retainFormOnError>{body}</AppLayout>;
}
