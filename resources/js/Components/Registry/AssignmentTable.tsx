import {Link} from '@inertiajs/react';
import DataTable from '@/Components/Data/DataTable';
import StatusBadge from '@/Components/Data/StatusBadge';
import type {Assignment} from '@/types/catalog';
import type {Paginated} from '@/types';
export default function AssignmentTable({records,onSort,sort,direction,loading}:{records:Paginated<Assignment>;onSort?:(column:string)=>void;sort?:string;direction?:string;loading?:boolean}) { return <DataTable onSort={onSort} sort={sort} direction={direction} loading={loading} rows={records.data} page={records} rowKey={r=>String(r.id)} columns={[
 {key:'driver',label:'Driver',render:r=>r.driver.first_name+' '+r.driver.last_name},
 {key:'vehicle',label:'Vehicle',render:r=>r.vehicle.registration_number},
 {key:'operator',label:'Operator / park',render:r=><>{r.operator.name}<span className="cell-secondary">{r.park.name}</span></>},
 {key:'status',label:'Status',sort:'status',render:r=><StatusBadge status={r.status}/>},
 {key:'time',label:'Period (UTC)',sort:'starts_at',render:r=><>{r.starts_at.replace('T',' ').slice(0,16)}<span className="cell-secondary">{r.ends_at?.replace('T',' ').slice(0,16)??'Open'} · {r.is_primary?'Primary':'Secondary'}</span></>},
 {key:'action',label:'Action',render:r=><Link className="text-link" href={'/assignments/'+r.id}>View assignment</Link>},
 ]}/>; }
