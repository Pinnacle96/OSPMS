import {Link,useForm} from '@inertiajs/react';
import {useState} from 'react';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/App/PageHeader';
import FormField from '@/Components/Forms/FormField';
import ConfirmDialog from '@/Components/Feedback/ConfirmDialog';
import type {Assignment} from '@/types/catalog';
export default function End({record}:{record:Assignment}) {
 const form=useForm({ends_at:new Date().toISOString().slice(0,19)}); const [confirm,setConfirm]=useState(false);
 return <AppLayout title="End assignment" breadcrumbs={[{label:'Assignments',href:'/assignments'},{label:'End assignment'}]}><PageHeader title="End assignment" description="Close the current operating relationship. The original assignment and its history will be retained."/><form className="panel form-panel" onSubmit={e=>{e.preventDefault();setConfirm(true);}}><div className="panel-body"><FormField id="ends_at" label="Ends at (UTC)" error={form.errors.ends_at}><input id="ends_at" required type="datetime-local" step="1" value={form.data.ends_at} onChange={e=>form.setData('ends_at',e.target.value)}/></FormField><div className="form-actions"><Link className="button secondary" href={'/assignments/'+record.id}>Cancel</Link><button className="button" disabled={form.processing}>End assignment</button></div></div></form><ConfirmDialog open={confirm} title="End this assignment?" description="The relationship will be closed and retained in assignment history." onClose={()=>setConfirm(false)} onConfirm={()=>{setConfirm(false);form.patch('/assignments/'+record.id+'/end');}}/></AppLayout>;
}
