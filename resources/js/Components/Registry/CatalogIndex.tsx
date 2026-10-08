import {Link,router} from '@inertiajs/react';
import {useState} from 'react';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/App/PageHeader';
import DataTable from '@/Components/Data/DataTable';
import StatusBadge from '@/Components/Data/StatusBadge';
import FormField from '@/Components/Forms/FormField';
import {catalog,itemName,vehicleTypes,type Kind,type Item,type Options} from '@/types/catalog';
import type {Paginated} from '@/types';
export default function CatalogIndex({kind,records,filters,can_create,options}: {kind:Kind;records:Paginated<Item>;filters:Record<string,string>;can_create:boolean;options:Options}) {
 const meta=catalog[kind]; const [search,setSearch]=useState(filters.search??''); const [status,setStatus]=useState(filters.status??''); const [vehicle,setVehicle]=useState(filters.vehicle_type??''); const [park,setPark]=useState(filters.park_id??''); const [loading,setLoading]=useState(false);
 const apply=(extra:Record<string,string>={})=>router.get('/'+kind,{search,status,vehicle_type:vehicle,park_id:park,...extra},{preserveState:true,replace:true,onStart:()=>setLoading(true),onFinish:()=>setLoading(false)});
 return <AppLayout title={meta.title} breadcrumbs={[{label:kind.includes('-')?'Finance':'Operations'},{label:meta.title}]}><PageHeader title={meta.title} description="Search authoritative registry records within your assigned access." actions={can_create && <Link className="button" href={'/'+kind+'/create'}>Create {meta.singular}</Link>} />
 <section className="panel"><form className="filter-bar" onSubmit={e=>{e.preventDefault();apply();}}><FormField id="catalog-search" label="Search registry"><input id="catalog-search" maxLength={190} value={search} onChange={e=>setSearch(e.target.value)} placeholder="Name, reference or registration" /></FormField><FormField id="catalog-status" label="Status"><select id="catalog-status" value={status} onChange={e=>setStatus(e.target.value)}><option value="">All statuses</option>{meta.statuses.map(s=><option key={s}>{s}</option>)}</select></FormField>
 {kind==='vehicles' && <FormField id="vehicle-type-filter" label="Vehicle type"><select id="vehicle-type-filter" value={vehicle} onChange={e=>setVehicle(e.target.value)}><option value="">All types</option>{vehicleTypes.map(s=><option key={s}>{s}</option>)}</select></FormField>}
 {!kind.includes('-') && <FormField id="park-filter" label="Park"><select id="park-filter" value={park} onChange={e=>setPark(e.target.value)}><option value="">All accessible parks</option>{options.parks?.map(p=><option key={p.id} value={p.id}>{p.name}</option>)}</select></FormField>}
 <button className="button secondary">Apply filters</button><Link className="text-link small" href={'/'+kind}>Clear</Link></form>
 <DataTable rows={records.data} page={records} rowKey={r=>r.public_id} loading={loading} sort={filters.sort} direction={filters.direction} onSort={column=>apply({sort:column,direction:filters.sort===column && filters.direction!=='asc'?'asc':'desc'})} columns={[
 {key:'name',label:kind==='fee-configurations'?'Fee':meta.singular,sort:kind==='drivers'?'first_name':kind==='vehicles'?'registration_number':kind==='fee-configurations'?'amount':'name',render:r=><Link className="text-link" href={'/'+kind+'/'+r.public_id}>{itemName(r)}</Link>},
 {key:'reference',label:kind==='fee-configurations'?'Effective from (UTC)':'Reference',render:r=>r.operator_number??r.driver_number??r.vehicle_number??r.code??r.effective_from?.replace('T',' ').slice(0,16)},
 {key:'status',label:'Status',sort:'status',render:r=><StatusBadge status={r.status} />},
 {key:'action',label:'Action',render:r=><Link className="text-link small" href={'/'+kind+'/'+r.public_id}>View details</Link>},
 ]} /></section></AppLayout>;
}
