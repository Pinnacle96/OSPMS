import { Head, usePage, router } from '@inertiajs/react';
import { useEffect, useState, type CSSProperties, type ReactNode } from 'react';
import { X } from 'lucide-react';
import Sidebar from '@/Components/Navigation/Sidebar';
import Topbar from '@/Components/Navigation/Topbar';
import Breadcrumbs, { type Crumb } from '@/Components/Navigation/Breadcrumbs';
import Toast from '@/Components/Feedback/Toast';
import { LoadingState, ErrorState } from '@/Components/Feedback/States';
import type { SharedProps } from '@/types';

export default function AppLayout({ title, children, breadcrumbs = [{ label: title }] }: { title: string; children: ReactNode; breadcrumbs?: Crumb[] }) {
    const { system } = usePage<SharedProps>().props;
    const [open, setOpen] = useState(false);
    const [loading, setLoading] = useState(false);
    const [networkError, setNetworkError] = useState(false);
    useEffect(() => {
        const start = router.on('start', () => { setLoading(true); setNetworkError(false); });
        const finish = router.on('finish', () => setLoading(false));
        const exception = router.on('exception', event => { event.preventDefault(); setLoading(false); setNetworkError(true); });
        return () => { start(); finish(); exception(); };
    }, []);
    useEffect(() => {
        if (!open) return;
        const previousFocus = document.activeElement as HTMLElement | null;
        const focusable = Array.from(document.querySelectorAll<HTMLElement>('#main-navigation button, #main-navigation a[href]'));
        focusable[0]?.focus();
        const key = (event: KeyboardEvent) => {
            if (event.key === 'Escape') setOpen(false);
            if (event.key === 'Tab' && focusable.length) {
                const first = focusable[0];
                const last = focusable[focusable.length - 1];
                if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
                else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
            }
        };
        window.addEventListener('keydown', key);
        return () => { window.removeEventListener('keydown', key); if (previousFocus?.isConnected) previousFocus.focus(); };
    }, [open]);
    const style = { '--gov-primary': system.branding.primary, '--gov-primary-dark': system.branding.primary_dark, '--gov-secondary': system.branding.secondary } as CSSProperties;
    return <div className={`app-shell ${open ? 'nav-open' : ''}`} style={style}><Head title={title} /><a className="skip-link" href="#main-content">Skip to content</a>
        {open && <button className="nav-backdrop" aria-label="Close navigation" onClick={() => setOpen(false)} />}
        <div className="sidebar-container" id="main-navigation"><button className="nav-close icon-button" aria-label="Close navigation" onClick={() => setOpen(false)}><X size={20} /></button><Sidebar close={() => setOpen(false)} /></div>
        <div className="main-column"><Topbar toggle={() => setOpen(!open)} expanded={open} /><main id="main-content" className="main-content"><Breadcrumbs items={breadcrumbs} /><Toast />{loading && <div className="page-loading"><LoadingState label="Updating view…" /></div>}{networkError && <ErrorState message="The connection was interrupted. Please try again." retry={() => router.reload()} />}{children}</main>
            <footer className="app-footer"><span>{system.name}</span><span>{system.demo_mode ? 'Demonstration • Synthetic data only' : 'Secure operations workspace'}</span></footer>
        </div></div>;
}
