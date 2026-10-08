import {Link} from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/App/PageHeader';
import StatusBadge from '@/Components/Data/StatusBadge';
import type {Assignment} from '@/types/catalog';
import {optionName} from '@/types/catalog';
import {usePermissions} from '@/hooks/usePermissions';
export default function Show({record:r,can_end}:{record:Assignment;can_end:boolean}) {
 const {can}=usePermissions();
 return <AppLayout title="Assignment details" breadcrumbs={[{label:'Assignments',href:'/assignments'},{label:'Assignment details'}]}><PageHeader title="Assignment details" description="Historical relationships remain available after this assignment ends." actions={can_end && <Link className="button secondary" href={'/assignments/'+r.id+'/end'}>End assignment</Link>}/><section className="panel"><div className="panel-body"><StatusBadge status={r.status}/><dl className="detail-list">{(['driver','vehicle','operator','park'] as const).map(key=><div key={key}><dt>{key}</dt><dd>{can('view_'+key)?<Link className="text-link" href={'/'+(key==='driver'?'drivers':key==='vehicle'?'vehicles':key==='operator'?'operators':'parks')+'/'+r[key].public_id}>{optionName(r[key])}</Link>:optionName(r[key])}</dd></div>)}<div><dt>Route</dt><dd>{r.route?optionName(r.route):'Not assigned'}</dd></div><div><dt>Starts at (UTC)</dt><dd>{r.starts_at.replace('T',' ').slice(0,19)}</dd></div><div><dt>Ends at (UTC)</dt><dd>{r.ends_at?.replace('T',' ').slice(0,19)??'Open'}</dd></div><div><dt>Assignment type</dt><dd>{r.is_primary?'Primary':'Secondary'}</dd></div></dl></div></section></AppLayout>;
}
