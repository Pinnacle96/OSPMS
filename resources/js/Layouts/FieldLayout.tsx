import { Head, Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import Brand from '@/Components/App/Brand';
import useBrandTokens from '@/hooks/useBrandTokens';
export default function FieldLayout({ title, children }: { title: string; children: ReactNode }) { const style = useBrandTokens(); return <div className="field-shell" style={style}><Head title={title} /><header><Brand compact /></header><main><h1>{title}</h1>{children}</main><footer><Link href="/account/access">My access</Link></footer></div>; }
