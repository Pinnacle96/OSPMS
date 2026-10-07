import { Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/App/PageHeader';
import FormField from '@/Components/Forms/FormField';
import ConfirmDialog from '@/Components/Feedback/ConfirmDialog';
import { registry, recordName, type Kind, type RegistryRecord, type Option } from '@/types/registry';
type Field = { key:string; label:string; max:number; required?:boolean; type?:string };
const fields: Record<Kind,Field[]> = {
    lgas:[{ key:'code',label:'LGA code',max:30,required:true },{ key:'name',label:'LGA name',max:150,required:true },{ key:'administrative_contact_name',label:'Administrative contact name',max:150 },{ key:'administrative_contact_phone',label:'Administrative contact phone',max:30,type:'tel' },{ key:'administrative_contact_email',label:'Administrative contact email',max:190,type:'email' }],
    parks:[{ key:'park_code',label:'Park code',max:50,required:true },{ key:'name',label:'Park name',max:190,required:true },{ key:'address',label:'Address',max:3000,required:true,type:'textarea' },{ key:'category',label:'Park category',max:50 },{ key:'contact_phone',label:'Contact phone',max:30,type:'tel' },{ key:'latitude',label:'Latitude',max:20,type:'number' },{ key:'longitude',label:'Longitude',max:20,type:'number' }],
    routes:[{ key:'route_code',label:'Route code',max:50,required:true },{ key:'origin',label:'Origin',max:190,required:true },{ key:'destination',label:'Destination',max:190,required:true },{ key:'description',label:'Description',max:3000,type:'textarea' }],
};
export default function RegistryForm({ kind,record,lgas=[] }: { kind:Kind; record?:RegistryRecord; lgas?:Option[] }) {
    const meta=registry[kind];
    const initial:Record<string,string>={ status:record?.status ?? (kind === 'parks' ? 'pending':'active') };
    for (const field of fields[kind]) initial[field.key]=String(record?.[field.key as keyof RegistryRecord] ?? '');
    if (kind === 'parks') initial.lga_id=String(record?.lga_id ?? '');
    const form=useForm(initial);
    const [confirm,setConfirm]=useState(false);
    const save=() => { setConfirm(false); if (record) form.patch(`/${kind}/${record.public_id}`); else form.post(`/${kind}`); };
    const title=record ? `Edit ${meta.singular}` : kind === 'parks' ? 'Register park' : `Create ${meta.singular}`;
    return <AppLayout title={title} breadcrumbs={[{ label:'Operations' },{ label:meta.title,href:`/${kind}` },{ label:record ? recordName(record):title }]}><PageHeader title={title} description="Maintain accurate registry details. Changes are recorded in the audit history." /><form className="panel form-panel" onSubmit={event => { event.preventDefault(); if (record && form.data.status !== record.status) setConfirm(true); else save(); }}>
        <div className="panel-header"><div><h2>{meta.singular} information</h2><p>Complete the required fields before saving.</p></div></div><div className="panel-body"><div className="form-grid">
        {kind === 'parks' && <FormField id="lga_id" label="Local government" error={form.errors.lga_id}><select id="lga_id" required value={form.data.lga_id} onChange={event => form.setData('lga_id',event.target.value)} aria-invalid={!!form.errors.lga_id} aria-describedby={form.errors.lga_id ? 'lga_id-error':undefined}><option value="">Select an LGA</option>{lgas.map(lga => <option key={lga.id} value={lga.id}>{lga.name}{lga.status === 'inactive' ? ' (inactive)':''}</option>)}</select></FormField>}
        {fields[kind].map(field => <FormField key={field.key} id={field.key} label={field.label} error={form.errors[field.key]} hint={field.key === 'latitude' ? 'Optional, between −90 and 90.':field.key === 'longitude' ? 'Optional, between −180 and 180.':undefined}>
            {field.type === 'textarea' ? <textarea id={field.key} required={field.required} maxLength={field.max} rows={3} value={form.data[field.key]} onChange={event => form.setData(field.key,event.target.value)} aria-invalid={!!form.errors[field.key]} aria-describedby={form.errors[field.key] ? `${field.key}-error`:undefined} /> :
            <input id={field.key} type={field.type ?? 'text'} required={field.required} maxLength={field.max} step={field.type === 'number' ? '0.0000001':undefined} min={field.key === 'latitude' ? -90:field.key === 'longitude' ? -180:undefined} max={field.key === 'latitude' ? 90:field.key === 'longitude' ? 180:undefined} value={form.data[field.key]} onChange={event => form.setData(field.key,event.target.value)} aria-invalid={!!form.errors[field.key]} aria-describedby={form.errors[field.key] ? `${field.key}-error`:field.type === 'number' ? `${field.key}-hint`:undefined} />}
        </FormField>)}
        <FormField id="status" label="Operational status" error={form.errors.status}><select id="status" value={form.data.status} onChange={event => form.setData('status',event.target.value)} aria-invalid={!!form.errors.status} aria-describedby={form.errors.status ? 'status-error':undefined}>{(kind === 'parks' ? ['pending','active','suspended','inactive']:['active','inactive']).map(status => <option value={status} key={status}>{status[0].toUpperCase()+status.slice(1)}</option>)}</select></FormField>
        </div>{kind === 'parks' && !lgas.length && <p className="notice">Create an LGA before registering a park.</p>}<div className="form-actions"><Link className="button secondary" href={record ? `/${kind}/${record.public_id}`:`/${kind}`}>Cancel</Link><button className="button" disabled={form.processing || (kind === 'parks' && !lgas.length)}>{form.processing ? 'Saving…':record ? 'Save changes':title}</button></div></div>
    </form><ConfirmDialog open={confirm} title="Change operational status?" description={`The ${meta.singular} status will change to ${form.data.status}. This change will be recorded in the audit history.`} onClose={() => setConfirm(false)} onConfirm={save} /></AppLayout>;
}
