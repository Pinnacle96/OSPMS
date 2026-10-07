import { usePage } from '@inertiajs/react';
import { Building2 } from 'lucide-react';
import type { SharedProps } from '@/types';

export default function Brand({ compact = false }: { compact?: boolean }) {
    const { system } = usePage<SharedProps>().props;
    return <div className={`brand ${compact ? 'brand-compact' : ''}`}>
        {system.branding.government_logo ? <img className="brand-logo" src={system.branding.government_logo} alt="Government logo" /> : <span className="brand-mark" aria-hidden="true"><Building2 size={25} strokeWidth={1.5} /></span>}
        <div><strong>OSUN STATE</strong><span>Park Management System</span></div>
    </div>;
}
