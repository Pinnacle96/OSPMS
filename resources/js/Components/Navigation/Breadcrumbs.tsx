import { Link } from '@inertiajs/react';
import { ChevronRight, Home } from 'lucide-react';
export type Crumb = { label: string; href?: string };
export default function Breadcrumbs({ items }: { items: Crumb[] }) {
    return <nav aria-label="Breadcrumb" className="breadcrumbs"><Link href="/" aria-label="Home"><Home size={14} /></Link>{items.map((item, index) => <span key={`${item.label}-${index}`}><ChevronRight size={13} />{item.href ? <Link href={item.href}>{item.label}</Link> : <span aria-current="page">{item.label}</span>}</span>)}</nav>;
}
