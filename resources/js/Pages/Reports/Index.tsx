import {Link} from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/App/PageHeader';
import type {ReportDefinition} from '@/types/reports';
export default function Index({reports}:{reports:ReportDefinition[]}){
 return <AppLayout title="Reports"><PageHeader title="Reports" description="View source totals and prepare reports within your assigned access." actions={<Link className="button secondary" href="/reports/exports">Saved exports</Link>}/><section className="panel"><div className="table-wrap"><table><thead><tr><th>Report</th><th>Data basis</th><th>Export access</th></tr></thead><tbody>{reports.length?reports.map(r=><tr key={r.type}><td><Link className="text-link" href={'/reports/'+r.type}>{r.title}</Link></td><td>{r.basis}</td><td>{r.can_export?'CSV, Excel and PDF':'View only'}</td></tr>):<tr><td colSpan={3}>No reports are available for your current permissions.</td></tr>}</tbody></table></div></section></AppLayout>;
}
