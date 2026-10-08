import {Link,router} from '@inertiajs/react';
import {useState} from 'react';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/App/PageHeader';
import FormField from '@/Components/Forms/FormField';
import AssignmentTable from '@/Components/Registry/AssignmentTable';
import type {Assignment} from '@/types/catalog';
import type {Paginated} from '@/types';
export default function Index({records,filters,can_create}:{records:Paginated<Assignment>;filters:Record<string,string>;can_create:boolean}) {
 const [search,setSearch]=useState(filters.search??''); const [status,setStatus]=useState(filters.status??''); const [loading,setLoading]=useState(false); const apply=(extra:Record<string,string>={})=>router.get('/assignments',{search,status,...extra},{preserveState:true,replace:true,onStart:()=>setLoading(true),onFinish:()=>setLoading(false)});
 return <AppLayout title="Assignments" breadcrumbs={[{label:'Operations'},{label:'Assignments'}]}><PageHeader title="Assignments" description="Current operating relationships and retained assignment history." actions={can_create && <Link className="button" href="/assignments/create">Create assignment</Link>}/><section className="panel"><form className="filter-bar" onSubmit={e=>{e.preventDefault();apply();}}><FormField id="assignment-search" label="Search assignments"><input id="assignment-search" value={search} maxLength={190} onChange={e=>setSearch(e.target.value)}/></FormField><FormField id="assignment-status" label="Status"><select id="assignment-status" value={status} onChange={e=>setStatus(e.target.value)}><option value="">All statuses</option>{['active','ended','suspended'].map(s=><option key={s}>{s}</option>)}</select></FormField><button className="button secondary">Apply filters</button><Link className="text-link" href="/assignments">Clear</Link></form><AssignmentTable records={records} loading={loading} sort={filters.sort} direction={filters.direction} onSort={column=>apply({sort:column,direction:filters.sort===column && filters.direction!=='asc'?'asc':'desc'})}/></section></AppLayout>;
}
