import type { LucideIcon } from 'lucide-react';
export default function StatCard({ label, value, note, icon: Icon }: { label: string; value: React.ReactNode; note: string; icon: LucideIcon }) {
    return <section className="stat-card"><div className="stat-top"><h2>{label}</h2><span className="stat-icon"><Icon size={19} strokeWidth={1.65} /></span></div><div className="stat-value">{value}</div><p>{note}</p></section>;
}
