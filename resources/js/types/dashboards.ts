export type Metric = { label: string; value: string | number | null; money?: boolean; icon?: string; note: string };
export type DashboardFilters = { from: string; to: string; lga_id?: string; park_id?: string; revenue_head_id?: string; channel?: string };
export type Totals = { gross: string; debits: string; net: string; transactions: number };
export type RevenueGroup = Totals & { id?: number; name: string; date?: string; href?: string | null };
export type CountGroup = { name: string; count: number; href: string | null };
export type FinanceData = {
    summary: Totals & { today: Totals; month: Totals };
    revenue_trend: (Totals & { date: string })[];
    revenue_by_lga: RevenueGroup[]; revenue_by_park: RevenueGroup[]; revenue_by_revenue_head: RevenueGroup[];
    payment_status: CountGroup[]; payment_channels: CountGroup[];
    recent_transactions: { reference: string; direction: string; type: string; amount: string; currency: string; occurred_at: string; href: string | null }[];
    ledger_url: string | null; payments_url: string | null; currency: string; timezone: string; reconciliation_available: boolean; pending_reconciliation:number|null; reconciliation_url:string|null;
    parks: { id: number; name: string }[]; lgas: { id: number; name: string }[]; revenue_heads: { id: number; name: string }[];
};
export type DashboardProps = { metrics: Metric[]; scope_label: string; lga_count: number; activities: { description: string; created_at: string }[]; filters: DashboardFilters; as_of: string; finance: FinanceData | null };
