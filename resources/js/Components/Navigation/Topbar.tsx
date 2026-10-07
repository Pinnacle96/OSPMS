import { Link, usePage } from '@inertiajs/react';
import { Bell, Menu, ChevronDown, LogOut, Search } from 'lucide-react';
import { useState } from 'react';
import type { SharedProps } from '@/types';

export default function Topbar({ toggle, expanded }: { toggle: () => void; expanded: boolean }) {
    const { auth, system } = usePage<SharedProps>().props;
    const [notifications, setNotifications] = useState(false);
    return <header className="topbar">
        <div className="topbar-left"><button className="icon-button mobile-menu" onClick={toggle} aria-label="Open navigation" aria-expanded={expanded} aria-controls="main-navigation"><Menu size={21} /></button><span className="topbar-title">Government operations portal</span>{system.demo_mode && <span className="demo-badge">DEMO ENVIRONMENT</span>}</div>
        <div className="topbar-right">{auth.permissions.includes('manage_users') && <form className="topbar-search" action="/admin/users" method="get"><label htmlFor="global-search" className="sr-only">Search users</label><Search size={15} /><input id="global-search" name="search" type="search" placeholder="Search users…" /></form>}<div className="notification-wrap"><button className="icon-button" aria-label="Notifications" aria-expanded={notifications} onClick={() => setNotifications(!notifications)}><Bell size={19} /></button>{notifications && <div className="notification-popover" role="status"><strong>Notifications</strong><p>Notifications will be available when operational modules are enabled.</p></div>}</div>
            <div className="topbar-separator" /><Link href="/account/profile" className="user-menu"><span className="avatar">{auth.user?.name.split(' ').map(n => n[0]).slice(0, 2).join('')}</span><span className="user-info"><strong>{auth.user?.name}</strong><small>{auth.roles[0] ?? 'System user'}</small></span><ChevronDown size={14} /></Link>
            <Link href="/logout" method="post" as="button" className="icon-button" aria-label="Sign out"><LogOut size={18} /></Link>
        </div>
    </header>;
}
