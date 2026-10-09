import {Link,router,usePage} from '@inertiajs/react';
import {useEffect} from 'react';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/App/PageHeader';
import Pagination from '@/Components/Data/Pagination';
import {dateTime} from '@/lib/formatters';
import type {Paginated,SharedProps} from '@/types';
import type {SavedExport} from '@/types/reports';
export default function Exports({records}:{records:Paginated<SavedExport>}){
 const {errors}=usePage<SharedProps>().props;
 const pending=records.data.some(e=>e.status==='queued');useEffect(()=>{if(!pending)return;const timer=window.setInterval(()=>router.reload({only:['records']}),5000);return()=>window.clearInterval(timer);},[pending]);
 return <AppLayout title="Saved exports"><PageHeader title="Saved exports" description="Your private report files and background export requests." actions={<Link className="button secondary" href="/reports">Reports center</Link>}/><section className="panel"><div className="panel-body">{Object.entries(errors).map(([key,message])=><p key={key} className="field-error" role="alert">{String(message)}</p>)}<p>Queued requests update automatically. Download access is checked against your current permissions and the records included in each file.</p><button className="button secondary" onClick={()=>router.reload({only:['records']})}>Refresh status</button></div><div className="table-wrap"><table><thead><tr><th>Report</th><th>Format</th><th>Requested</th><th>Status</th><th>File</th></tr></thead><tbody>{records.data.length?records.data.map(e=><tr key={e.public_id}><td><Link className="text-link" href={'/reports/'+e.type}>{e.title}</Link></td><td>{e.format==='xlsx'?'Excel':e.format.toUpperCase()}</td><td>{dateTime(e.requested_at)}</td><td>{e.status==='queued'?'Queued / processing':e.status==='completed'?'Ready':'Failed'}{e.error&&<p className="field-hint">{e.error}</p>}</td><td>{e.can_download?<a className="text-link" href={'/reports/exports/'+e.public_id+'/download'} aria-label={'Download '+e.title+' '+e.format}>Download</a>:e.status==='completed'?<span>Current access required</span>:null}{e.can_retry&&<button className="button secondary" onClick={()=>router.post('/reports/exports/'+e.public_id+'/retry')}>Retry export</button>}</td></tr>):<tr><td colSpan={5}>No saved exports yet. Open a report to generate a file.</td></tr>}</tbody></table></div><Pagination page={records}/></section></AppLayout>;
}
