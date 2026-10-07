export function dateTime(value?: string | null, timezone = 'Africa/Lagos') {
    return value ? new Intl.DateTimeFormat('en-NG', { dateStyle: 'medium', timeStyle: 'short', timeZone: timezone }).format(new Date(value)) : 'Not recorded';
}
export function money(amount: string, currency = 'NGN') {
    // Format a server-provided decimal; no financial arithmetic is performed here.
    const [whole, fraction = '00'] = amount.split('.');
    const sign = currency === 'NGN' ? '₦' : `${currency} `;
    return `${sign}${whole.replace(/\B(?=(\d{3})+(?!\d))/g, ',')}.${fraction.padEnd(2, '0')}`;
}
