import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import ConfirmDialog from '@/Components/Feedback/ConfirmDialog';
import { EmptyState } from '@/Components/Feedback/States';
import type { RegistryRecord, RouteOption } from '@/types/registry';
export default function RouteAssignment({ park, options, selected }: { park:RegistryRecord; options:RouteOption[]; selected:number[] }) {
    const form=useForm({ route_ids:selected });
    const [confirm,setConfirm]=useState(false);
    return <form className="panel route-assignment" onSubmit={event => { event.preventDefault(); setConfirm(true); }}><div className="panel-header"><div><h2>Manage approved routes</h2><p>Select routes available at this park. Removed assignments remain in the history.</p></div></div><div className="panel-body"><fieldset><legend>Active route catalogue</legend>{options.length ? <div className="checkbox-grid">{options.map(route => <label className="checkbox-label" key={route.id}><input type="checkbox" checked={form.data.route_ids.includes(route.id)} onChange={event => form.setData('route_ids',event.target.checked ? [...form.data.route_ids,route.id]:form.data.route_ids.filter(id => id !== route.id))} /><span><strong>{route.origin} → {route.destination}</strong><small>{route.route_code}</small></span></label>)}</div>:<EmptyState compact title="No active routes available" description="Create or activate a route before assigning it to this park." />}</fieldset>{Object.values(form.errors).map((error,index) => <p className="field-error" role="alert" key={index}>{error}</p>)}<div className="form-actions"><button className="button" disabled={form.processing}>{form.processing ? 'Saving…':'Save route assignments'}</button></div></div>
        <ConfirmDialog open={confirm} title="Update park routes?" description="The selected routes will become the approved route assignments for this park." onClose={() => setConfirm(false)} onConfirm={() => { setConfirm(false); form.put(`/parks/${park.public_id}/routes`,{ preserveScroll:true }); }} />
    </form>;
}
