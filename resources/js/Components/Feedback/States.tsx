import { CircleAlert, Inbox, LoaderCircle } from 'lucide-react';
export function EmptyState({ title = 'No records yet', description = 'Records will appear here when available.', compact = false }: { title?: string; description?: string; compact?: boolean }) {
    return <div className={`empty-state ${compact ? 'compact' : ''}`}><span className="empty-icon"><Inbox size={23} strokeWidth={1.5} /></span><strong>{title}</strong><p>{description}</p></div>;
}
export function LoadingState({ label = 'Loading records…' }: { label?: string }) { return <div className="loading-state" role="status"><LoaderCircle className="spinner" size={20} />{label}</div>; }
export function ErrorState({ message = 'Unable to load this information.', retry }: { message?: string; retry?: () => void }) { return <div className="error-state" role="alert"><CircleAlert size={20} /><span>{message}</span>{retry && <button className="button secondary" onClick={retry}>Try again</button>}</div>; }
