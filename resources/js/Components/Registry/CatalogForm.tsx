import {Link,useForm} from '@inertiajs/react';
import {useState} from 'react';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/App/PageHeader';
import FormField from '@/Components/Forms/FormField';
import ConfirmDialog from '@/Components/Feedback/ConfirmDialog';
import {usePermissions} from '@/hooks/usePermissions';
import {catalog,fields,optionName,type Kind,type Item,type Options} from '@/types/catalog';
export default function CatalogForm({kind,record,options,selected_parks=[],selected_routes=[]}: {kind:Kind;record?:Item;options:Options;selected_parks?:number[];selected_routes?:string[]}) {
 const meta=catalog[kind]; const initial:Record<string,string|string[]>={status:record?.status??meta.statuses[0]}; const {can}=usePermissions();
 for(const field of fields[kind]) { const value=String(record?.[field.key]??(field.key==='currency'?'NGN':field.key==='priority'?'0':'')); initial[field.key]=field.type==='datetime-local'?value.slice(0,19):value; }
 if(kind==='operators') { initial.park_ids=selected_parks.map(String); initial.route_keys=selected_routes; }
 const form=useForm(initial); const [confirm,setConfirm]=useState(false); const locked=kind==='fee-configurations' && !!record?.effective_from && new Date(record.effective_from)<=new Date();
 const save=()=>{setConfirm(false);if(record)form.patch('/'+kind+'/'+record.public_id);else form.post('/'+kind);};
 const toggle=(key:string,value:string)=>{const list=form.data[key] as string[];form.setData(key,list.includes(value)?list.filter(x=>x!==value):[...list,value]);};
 const title=(record?'Edit':'Create')+' '+meta.singular;
 const statuses=locked?[record!.status,'inactive'].filter((s,i,a)=>a.indexOf(s)===i):meta.statuses.filter(s=>s===record?.status || !(s==='approved' || (s==='active' && !kind.includes('-'))) || can('approve_'+meta.singular));
 const permitted=statuses.filter(s=>s===record?.status || !['suspended','blacklisted'].includes(s) || can('suspend_'+meta.singular));
 return <AppLayout title={title} breadcrumbs={[{label:meta.title,href:'/'+kind},{label:title}]}><PageHeader title={title} description="Required fields and approval permissions are checked by the server." />
 {kind==='fee-configurations' && <p className="notice">Changing fees never changes historical tickets. {locked?'This fee has taken effect. Its terms are locked; create a future configuration to change them.':'Scope specificity takes precedence over priority. Enter effective times in UTC.'}</p>}
 <form className="panel form-panel" onSubmit={e=>{e.preventDefault();if(record && form.data.status!==record.status)setConfirm(true);else save();}}><div className="panel-header"><h2>{meta.singular} information</h2></div><div className="panel-body"><div className="form-grid">
 {fields[kind].map(f=><FormField key={f.key} id={f.key} label={f.label} error={form.errors[f.key]} hint={f.source && !f.required?'Leave blank for all applicable records.':undefined}>
 {f.source || f.options ? <select id={f.key} value={form.data[f.key] as string} required={f.required} disabled={locked} aria-invalid={!!form.errors[f.key]} aria-describedby={form.errors[f.key]?f.key+'-error':undefined} onChange={e=>form.setData(f.key,e.target.value)}><option value="">{f.required?'Select an option':'All / default'}</option>{f.source?options[f.source]?.map(o=><option key={o.id} value={o.id}>{optionName(o)}</option>):f.options?.map(v=><option key={v} value={v}>{v.replaceAll('_',' ')}</option>)}</select>:
 f.type==='textarea'?<textarea id={f.key} value={form.data[f.key] as string} maxLength={f.max} disabled={locked} onChange={e=>form.setData(f.key,e.target.value)} />:
 <input id={f.key} type={f.type??'text'} step={f.type==='datetime-local'?1:undefined} maxLength={f.max} required={f.required} disabled={locked} value={form.data[f.key] as string} aria-invalid={!!form.errors[f.key]} aria-describedby={form.errors[f.key]?f.key+'-error':undefined} onChange={e=>form.setData(f.key,e.target.value)} />}</FormField>)}
 <FormField id="status" label="Operational status" error={form.errors.status}><select id="status" value={form.data.status as string} onChange={e=>form.setData('status',e.target.value)}>{permitted.map(s=><option key={s} value={s}>{s}</option>)}</select></FormField></div>
 {kind==='operators' && <div className="form-grid"><fieldset><legend>Approved parks</legend>{options.parks?.map(p=><label className="checkbox-label" key={p.id}><input type="checkbox" checked={(form.data.park_ids as string[]).includes(String(p.id))} onChange={()=>toggle('park_ids',String(p.id))}/>{p.name} <span className="muted small">{p.status}</span></label>)}{form.errors.park_ids && <p className="field-error" role="alert">{form.errors.park_ids}</p>}</fieldset><fieldset><legend>Approved routes by park</legend>{options.operator_routes?.map(r=><label className="checkbox-label" key={r.id}><input type="checkbox" checked={(form.data.route_keys as string[]).includes(String(r.id))} onChange={()=>toggle('route_keys',String(r.id))}/>{r.name}</label>)}{form.errors.route_keys && <p className="field-error" role="alert">{form.errors.route_keys}</p>}</fieldset></div>}
 {Object.entries(form.errors).filter(([key])=>!fields[kind].some(f=>f.key===key) && !['status','park_ids','route_keys'].includes(key)).map(([key,error])=><p role="alert" className="field-error" key={key}>{error}</p>)}
 <div className="form-actions"><Link className="button secondary" href={record?'/'+kind+'/'+record.public_id:'/'+kind}>Cancel</Link><button className="button" disabled={form.processing}>{form.processing?'Saving…':'Save record'}</button></div></div></form>
 <ConfirmDialog open={confirm} title="Change operational status?" description={'Change this '+meta.singular+' to '+String(form.data.status)+'? The action will be audited.'} onClose={()=>setConfirm(false)} onConfirm={save}/></AppLayout>;
}
