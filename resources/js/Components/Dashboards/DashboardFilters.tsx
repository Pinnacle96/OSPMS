import { Link, useForm } from '@inertiajs/react';
import { RefreshCw } from 'lucide-react';
import DateRangePicker from '@/Components/Forms/DateRangePicker';
import FormField from '@/Components/Forms/FormField';
import type { DashboardFilters as Filters, FinanceData } from '@/types/dashboards';

export default function DashboardFilters({ filters, finance, path, local }: { filters: Filters; finance: FinanceData | null; path: string; local?: 'lgas' | 'parks' }) {
    const form = useForm<Filters>(filters);
    return <section className="panel dashboard-filters"><form className="filter-bar" aria-label="Dashboard filters" onSubmit={e => { e.preventDefault(); form.get(path, { preserveState: true, preserveScroll: true, replace: true }); }}>
        <DateRangePicker from={form.data.from} to={form.data.to} onChange={(key, value) => form.setData(key, value)} errors={form.errors} />
        {finance && <>
            {([['lga_id', 'LGA', finance.lgas], ['park_id', 'Park', finance.parks], ['revenue_head_id', 'Revenue head', finance.revenue_heads]] as const).filter(([key]) => !(local === 'lgas' && key === 'lga_id') && !(local === 'parks' && ['park_id', 'lga_id'].includes(key))).map(([key, label, options]) => <FormField key={key} id={`dashboard-${key}`} label={label} error={form.errors[key]}><select id={`dashboard-${key}`} value={form.data[key] ?? ''} onChange={e => form.setData(key, e.target.value)}><option value="">All accessible records</option>{options.map(option => <option key={option.id} value={option.id}>{option.name}</option>)}</select></FormField>)}
            <FormField id="dashboard-channel" label="Payment channel" error={form.errors.channel}><select id="dashboard-channel" value={form.data.channel ?? ''} onChange={e => form.setData('channel', e.target.value)}><option value="">All channels</option>{['demo', 'cashless_pos', 'transfer', 'ussd', 'gateway', 'other'].map(channel => <option key={channel} value={channel}>{channel.replaceAll('_', ' ')}</option>)}</select></FormField>
        </>}
        <button className="button secondary" disabled={form.processing} type="submit"><RefreshCw size={15} />{form.processing ? 'Updating…' : 'Apply filters'}</button><Link className="text-link small" href={path}>Reset filters</Link>
    </form><p className="dashboard-filter-note">Financial dates use {finance?.timezone ?? 'the configured local timezone'}. Registry counts show current access and are independent of financial filters.</p></section>;
}
