import { money } from '@/lib/formatters';
export default function MoneyDisplay({ amount, currency = 'NGN' }: { amount: string; currency?: string }) { return <span className="numeric">{money(amount, currency)}</span>; }
