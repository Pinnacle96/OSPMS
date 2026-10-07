import { usePage } from '@inertiajs/react';
import { CheckCircle2, CircleAlert, Info } from 'lucide-react';
import type { SharedProps } from '@/types';
export default function Toast() {
    const { flash } = usePage<SharedProps>().props;
    return <>{flash.success && <div role="status" className="notice success"><CheckCircle2 size={18} />{flash.success}</div>}{flash.error && <div role="alert" className="notice danger"><CircleAlert size={18} />{flash.error}</div>}{flash.status && <div role="status" className="notice info"><Info size={18} />{flash.status}</div>}</>;
}
