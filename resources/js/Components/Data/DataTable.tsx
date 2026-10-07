import type { ReactNode } from 'react';
import { EmptyState, ErrorState, LoadingState } from '@/Components/Feedback/States';
import Pagination from './Pagination';
import type { Paginated } from '@/types';
export type Column<T> = { key: string; label: string; render: (row: T) => ReactNode; sort?: string };
export default function DataTable<T>({ columns, rows, rowKey, page, loading, error, emptyTitle, onSort, sort, direction }: { columns: Column<T>[]; rows: T[]; rowKey: (row: T) => string; page?: Omit<Paginated<T>, 'data'>; loading?: boolean; error?: string; emptyTitle?: string; onSort?: (column: string) => void; sort?: string; direction?: string }) {
    return <div className="data-table" aria-busy={loading}>{error ? <ErrorState message={error} /> : loading ? <LoadingState /> : <div className="table-scroll"><table><thead><tr>{columns.map(column => <th key={column.key} scope="col" aria-sort={column.sort === sort ? direction === 'desc' ? 'descending' : 'ascending' : undefined}>{column.sort && onSort ? <button className="sort-button" onClick={() => onSort(column.sort!)}>{column.label}<span aria-hidden="true">{column.sort === sort ? direction === 'desc' ? ' ↓' : ' ↑' : ' ↕'}</span></button> : column.label}</th>)}</tr></thead><tbody>{rows.map(row => <tr key={rowKey(row)}>{columns.map(column => <td key={column.key}>{column.render(row)}</td>)}</tr>)}</tbody></table>{!rows.length && <EmptyState title={emptyTitle ?? 'No records match your filters'} description="Try another search or adjust the selected filters." />}</div>}{page && <Pagination page={page} />}</div>;
}
