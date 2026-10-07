import type { AccessScopes } from '@/types';
import { Link } from '@inertiajs/react';
import { usePermissions } from '@/hooks/usePermissions';
export default function AccessSummary({ scopes }: { scopes: AccessScopes }) {
    const { can } = usePermissions();
    return <>{scopes.statewide && <div className="notice info">Statewide access is granted by permission. Entity actions still require their own permissions.</div>}{(['lgas', 'parks', 'operators'] as const).map(type => <div className="scope-section" key={type}><h3>{{ lgas: 'Local government areas', parks: 'Parks', operators: 'Operators' }[type]}</h3>{scopes[type].length ? scopes[type].map(scope => <div className="scope-row" key={scope.id}>{type !== 'operators' && scope.public_id && can(type === 'lgas' ? 'view_lga' : 'view_park') ? <Link className="text-link" href={`/${type}/${scope.public_id}/dashboard`}>{scope.name}</Link> : <span>{scope.name}</span>}<span className="pill">{scope.access_level}</span></div>) : <p className="muted small">No explicit {type === 'lgas' ? 'LGA' : type.slice(0, -1)} scopes assigned.</p>}</div>)}</>;
}
