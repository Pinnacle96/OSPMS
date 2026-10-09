import {Link,router} from '@inertiajs/react';
import {useState} from 'react';
import FieldLayout from '@/Layouts/FieldLayout';
import FormField from '@/Components/Forms/FormField';
import Pagination from '@/Components/Data/Pagination';
import useFieldOnline from '@/hooks/useFieldOnline';
import type {Paginated} from '@/types';
import type {FieldRecord} from '@/types/field';
export type LookupProps={records:Paginated<FieldRecord>;filters:{search?:string}};
export default function LookupSearch({kind,records,filters}:{kind:'drivers'|'vehicles'|'operators'}&LookupProps){
 const [search,setSearch]=useState(filters.search??''),online=useFieldOnline();
 const label=kind[0].toUpperCase()+kind.slice(1);
 return <FieldLayout title={label+' lookup'}><nav className="field-tabs" aria-label="Lookup type">{['drivers','vehicles','operators'].map(k=><Link key={k} href={'/field/'+k} aria-current={kind===k?'page':undefined}>{k[0].toUpperCase()+k.slice(1)}</Link>)}</nav><p className="field-lead">Search names or registry references within your assigned scope.</p><section className="panel"><div className="panel-body"><form onSubmit={e=>{e.preventDefault();router.get('/field/'+kind,{search},{preserveState:true});}} className="payment-form"><FormField label={'Search '+kind} id="field-search"><input id="field-search" type="search" value={search} onChange={e=>setSearch(e.target.value)} minLength={2} maxLength={100} required placeholder={kind==='vehicles'?'Registration number or reference':'Name or reference'}/></FormField><button className="button" disabled={!online}>Search records</button></form></div></section><section aria-label="Search results"><p className="muted small">{!filters.search?'Enter at least two characters to search.':records.total+' matching records'}</p><div className="field-records">{records.data.map(r=><Link className="field-record-card" href={'/field/'+kind+'/'+r.public_id} key={r.public_id}><strong>{r.name}</strong><span>{r.reference}</span><span className="muted small">Status: {r.status}</span><span className="text-link small">Open quick view →</span></Link>)}</div>{filters.search&&records.total===0&&<p className="notice">No matching records in your assigned scope. Check the name or reference.</p>}<Pagination page={records}/></section></FieldLayout>;
}
