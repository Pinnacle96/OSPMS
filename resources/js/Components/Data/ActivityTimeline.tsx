import { dateTime } from '@/lib/formatters';
import { EmptyState } from '@/Components/Feedback/States';
export default function ActivityTimeline({ activities }: { activities: { event: string; occurred_at: string; ip_address?: string | null }[] }) {
    return activities.length ? <ul className="activity-list">{activities.map((activity, index) => <li key={index}><span className="activity-dot" /><div><strong>{activity.event.replaceAll('_', ' ')}</strong><p>{dateTime(activity.occurred_at)}{activity.ip_address ? ` · ${activity.ip_address}` : ''}</p></div></li>)}</ul> : <EmptyState compact title="No sign in history" description="Authentication activity will appear here." />;
}
