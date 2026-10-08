import {router} from '@inertiajs/react';
import {useEffect} from 'react';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/App/PageHeader';
import StatusBadge from '@/Components/Data/StatusBadge';
import ReconciliationSummary from '@/Components/Finance/ReconciliationSummary';
import ReconciliationTable from '@/Components/Finance/ReconciliationTable';
import ReconciliationFilters from '@/Components/Finance/ReconciliationFilters';
import {dateTime} from '@/lib/formatters';
import type {Run,ItemPage,Filters,Options} from '@/types/reconciliation';
export default function Show({run,items,filters,basis,lgas,parks}:{run:Run;items:ItemPage;filters:Filters;basis:string|null}&Options){useEffect(()=>{if(!['queued','running'].includes(run.status))return;const timer=setInterval(()=>{if(document.visibilityState==='visible')router.reload({only:['run','items','basis']});},5000);return ()=>clearInterval(timer);},[run.status]);return <AppLayout title={run.reconciliation_reference} breadcrumbs={[{label:'Reconciliation',href:'/finance/reconciliation'},{label:'Runs',href:'/finance/reconciliation/runs'},{label:run.reconciliation_reference}]}><PageHeader title={run.reconciliation_reference} description={`${dateTime(run.period_start)} to ${dateTime(run.period_end)} (exclusive)`} actions={<StatusBadge status={run.status}/>}/>{run.scoped&&<p className="notice">Only findings within your assigned access are included in these totals.</p>}{['queued','running'].includes(run.status)&&<p className="notice" role="status">Processing this run. This page refreshes while visible.</p>}{run.status==='failed'&&<p className="notice" role="alert">Processing failed. No partial findings were committed. Finance staff can start a new run; the failed request remains retained.</p>}<ReconciliationSummary summary={run.summary}/>{basis&&<p className="notice">{basis}</p>}<ReconciliationFilters key={JSON.stringify(filters)} filters={filters} lgas={lgas} parks={parks} url={'/finance/reconciliation/runs/'+run.public_id}/><ReconciliationTable items={items}/></AppLayout>;}
