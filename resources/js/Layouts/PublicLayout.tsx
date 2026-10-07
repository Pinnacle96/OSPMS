import { Head, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import Brand from '@/Components/App/Brand';
import type { SharedProps } from '@/types';
import useBrandTokens from '@/hooks/useBrandTokens';
export default function PublicLayout({ title, children }: { title: string; children: ReactNode }) { const { system } = usePage<SharedProps>().props; const style = useBrandTokens(); return <div className="public-shell" style={style}><Head title={title} /><header><Brand /></header><main>{children}</main><footer>{system.branding.powered_by}</footer></div>; }
