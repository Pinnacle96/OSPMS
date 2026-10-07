import { Head, usePage } from '@inertiajs/react';
import { LockKeyhole, ShieldCheck, Network } from 'lucide-react';
import type { CSSProperties, ReactNode } from 'react';
import Brand from '@/Components/App/Brand';
import Toast from '@/Components/Feedback/Toast';
import type { SharedProps } from '@/types';

export default function AuthLayout({ title, description, children }: { title: string; description: string; children: ReactNode }) {
    const { system } = usePage<SharedProps>().props;
    return <div className="auth-shell" style={{ '--gov-primary': system.branding.primary, '--gov-primary-dark': system.branding.primary_dark, '--gov-secondary': system.branding.secondary } as CSSProperties}><Head title={title} />
        <aside className="auth-aside"><Brand /><div className="auth-message"><p className="eyebrow">A CONNECTED OPERATIONS PLATFORM</p><h1>Clear oversight.<br />Accountable operations.</h1><p>A central workspace for park administration, transport operations and revenue oversight.</p><div className="auth-principles"><span><Network size={20} /> Connected records</span><span><ShieldCheck size={20} /> Traceable activity</span><span><LockKeyhole size={20} /> Controlled access</span></div></div><p className="auth-provider">{system.branding.powered_by}</p></aside>
        <main className="auth-main"><div className="auth-card">{system.demo_mode && <span className="demo-badge">DEMO ENVIRONMENT</span>}<h2>{title}</h2><p className="auth-description">{description}</p><Toast />{children}<div className="auth-footnote"><LockKeyhole size={14} /> Authorized personnel only</div></div><p className="auth-copyright">{system.name}</p></main>
    </div>;
}
