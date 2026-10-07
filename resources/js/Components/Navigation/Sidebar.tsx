import { Link, usePage } from '@inertiajs/react';
import { LayoutDashboard, Users, ShieldCheck, UserRound, KeyRound, ArrowUpRight, LockKeyhole, Map, MapPin, Route } from 'lucide-react';
import Brand from '@/Components/App/Brand';
import type { SharedProps } from '@/types';

const icons = { dashboard: LayoutDashboard, users: Users, shield: ShieldCheck, user: UserRound, key: KeyRound, geography: Map, parks: MapPin, routes: Route };
export default function Sidebar({ close }: { close: () => void }) {
    const { props, url } = usePage<SharedProps>();
    const groups = [...new Set(props.navigation.map(item => item.group))];
    return <aside className="sidebar" aria-label="Main navigation">
        <div className="sidebar-brand"><Brand /><div className="sidebar-divider" /><span className="workspace-label">OPERATIONS WORKSPACE</span></div>
        <nav>{groups.map(group => <div className="nav-group" key={group}><p className="nav-group-label">{group}</p>{props.navigation.filter(item => item.group === group).map(item => {
            const Icon = icons[item.icon as keyof typeof icons] ?? UserRound;
            const active = url.split('?')[0] === item.href || url.startsWith(item.href + '/');
            return <Link key={item.href} href={item.href} className={`nav-link ${active ? 'active' : ''}`} aria-current={active ? 'page' : undefined} onClick={close}><Icon size={18} /><span>{item.label}</span>{active && <ArrowUpRight size={15} />}</Link>;
        })}</div>)}</nav>
        <div className="sidebar-bottom"><LockKeyhole size={17} /><div><strong>Accountable by design</strong><span>Permission-controlled access</span></div></div>
        <div className="provider-credit">{props.system.branding.powered_by}</div>
    </aside>;
}
