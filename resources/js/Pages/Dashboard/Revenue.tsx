import OverviewDashboard from '@/Components/Dashboards/OverviewDashboard';
import type { DashboardProps } from '@/types/dashboards';
export default function Revenue(props: DashboardProps) { return <OverviewDashboard {...props} mode="revenue" />; }
