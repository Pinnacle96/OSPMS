import { Link, router, useForm } from '@inertiajs/react';
import { Plus, ShieldCheck } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/App/PageHeader';
import DataTable, { type Column } from '@/Components/Data/DataTable';
import StatusBadge from '@/Components/Data/StatusBadge';
import FilterBar from '@/Components/Forms/FilterBar';
import SearchInput from '@/Components/Forms/SearchInput';
import { registry, recordName, type Kind, type IndexProps, type RegistryRecord } from '@/types/registry';
export default function RegistryIndex({ kind, records, filters, can_create, lgas = [] }: IndexProps & { kind: Kind }) {
    const meta = registry[kind];
    const form = useForm({ search: filters.search ?? '', status: filters.status ?? '', lga_id: filters.lga_id ?? '' });
    const columns: Column<RegistryRecord>[] = [
        { key:'name', label: kind === 'routes' ? 'Transport route' : meta.singular, sort: kind === 'routes' ? 'route_code' : 'name', render: record => <div className="table-person"><Link href={`/${kind}/${record.public_id}`}><strong>{recordName(record)}</strong></Link><span>{record.code ?? record.park_code ?? record.route_code}</span></div> },
        ...(kind === 'parks' ? [{ key:'lga', label:'Local government', render:(record: RegistryRecord) => record.lga?.name ?? '—' }] : []),
        { key:'connections', label:kind === 'parks' ? 'Approved routes' : 'Associated parks', render:record => record.routes_count ?? record.parks_count ?? 0 },
        { key:'status', label:'Status', sort:'status', render:record => <StatusBadge status={record.status} /> },
        { key:'actions', label:'Actions', render:record => <div className="row-actions"><Link className="text-link small" href={`/${kind}/${record.public_id}`}>View</Link>{record.can_update && <Link className="text-link small" href={`/${kind}/${record.public_id}/edit`}>Edit</Link>}</div> },
    ];
    return <AppLayout title={meta.title} breadcrumbs={[{ label:'Operations' },{ label:meta.title }]}><PageHeader eyebrow="REGISTRY ADMINISTRATION" title={meta.title} description={meta.description} actions={can_create && <Link className="button" href={`/${kind}/create`}><Plus size={16} />{kind === 'parks' ? 'Register park' : `Create ${meta.singular}`}</Link>} />
        <div className="scope-strip"><span><ShieldCheck size={14} />Showing records within your assigned access</span><span>{records.total.toLocaleString()} records</span></div>
        <section className="panel"><form onSubmit={event => { event.preventDefault(); form.get(`/${kind}`,{ preserveState:true }); }}><FilterBar>
            <SearchInput value={form.data.search} onChange={value => form.setData('search',value)} placeholder={kind === 'routes' ? 'Search code, origin or destination' : 'Search name or code'} />
            <div><label htmlFor="registry-status">Status</label><select id="registry-status" value={form.data.status} onChange={event => form.setData('status',event.target.value)}><option value="">All statuses</option>{(kind === 'parks' ? ['pending','active','suspended','inactive'] : ['active','inactive']).map(status => <option key={status} value={status}>{status[0].toUpperCase()+status.slice(1)}</option>)}</select></div>
            {kind !== 'lgas' && <div><label htmlFor="registry-lga">Local government</label><select id="registry-lga" value={form.data.lga_id} onChange={event => form.setData('lga_id',event.target.value)}><option value="">All assigned LGAs</option>{lgas.map(lga => <option key={lga.id} value={lga.id}>{lga.name}</option>)}</select></div>}
            <button className="button secondary" disabled={form.processing}>Apply filters</button><Link className="text-link small" href={`/${kind}`}>Clear</Link>
        </FilterBar></form>{Object.values(form.errors).map((error,index) => <p className="field-error panel-body" role="alert" key={index}>{error}</p>)}
        <DataTable columns={columns} rows={records.data} page={records} rowKey={record => record.public_id} loading={form.processing} sort={filters.sort ?? (kind === 'routes' ? 'route_code' : 'name')} direction={filters.direction ?? 'asc'} onSort={sort => router.get(`/${kind}`,{ ...form.data, sort,direction:filters.sort === sort && filters.direction !== 'desc' ? 'desc':'asc' },{ preserveState:true })} />
        </section></AppLayout>;
}
