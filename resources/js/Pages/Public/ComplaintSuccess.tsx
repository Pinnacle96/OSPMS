import {Link} from '@inertiajs/react';
import PublicLayout from '@/Layouts/PublicLayout';
import PageHeader from '@/Components/App/PageHeader';
export default function ComplaintSuccess({reference}:{reference:string}){return <PublicLayout title="Complaint submitted"><PageHeader title="Complaint submitted" description="Your report has been saved for review."/><section className="panel"><div className="panel-body"><h2>Your complaint reference</h2><p className="complaint-reference">{reference}</p><p>Keep this reference when contacting the Help Desk. Contact details and evidence are shared only with authorized staff.</p><Link className="button secondary" href="/public/complaints">Submit another complaint</Link></div></section></PublicLayout>;}
