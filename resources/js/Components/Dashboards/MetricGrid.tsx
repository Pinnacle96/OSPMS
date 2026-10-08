import { Wallet, ReceiptText, MapPin, Users, BusFront, ContactRound, CircleCheck, CircleAlert, ListChecks } from 'lucide-react';
import StatCard from '@/Components/Data/StatCard';
import MoneyDisplay from '@/Components/Finance/MoneyDisplay';
import type { Metric } from '@/types/dashboards';

const icons = { wallet: Wallet, receipt: ReceiptText, park: MapPin, users: Users, vehicle: BusFront, driver: ContactRound, check: CircleCheck, alert: CircleAlert, reconcile: ListChecks };
export default function MetricGrid({ metrics }: { metrics: Metric[] }) {
    return <div className="stat-grid">{metrics.map(metric => <StatCard key={metric.label} label={metric.label} value={metric.value === null ? <span className="metric-unavailable">Unavailable</span> : metric.money ? <MoneyDisplay amount={String(metric.value)} /> : Number(metric.value).toLocaleString('en-NG')} note={metric.note} icon={icons[metric.icon as keyof typeof icons] ?? (metric.money ? Wallet : ReceiptText)} />)}</div>;
}
