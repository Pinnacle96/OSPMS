import { Link } from '@inertiajs/react';
import type { Paginated } from '@/types';
export default function Pagination({ page }: { page: Omit<Paginated<never>, 'data'> }) {
    return <div className="pagination"><p>{page.total ? `Showing ${page.from}–${page.to} of ${page.total} records` : '0 records'}</p><nav aria-label="Pagination">{page.links.map((link, i) => {
        const label = link.label.replace(/&laquo;|&raquo;/g, '').replace(/<[^>]+>/g, '').trim();
        return link.url ? <Link preserveState preserveScroll href={link.url} key={i} className={link.active ? 'current' : ''} aria-current={link.active ? 'page' : undefined}>{label}</Link> : <span key={i} aria-disabled="true">{label}</span>;
    })}</nav></div>;
}
