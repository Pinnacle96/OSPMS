import type {Paginated} from '@/types';
export type Summary={expected:string;actual:string;difference:string;currency:string;total:number;matched:number;exceptions:number;under_review:number;reconciled:number;open:number};
export type Run={public_id:string;reconciliation_reference:string;period_start:string;period_end:string;provider:string|null;status:string;started_at:string;completed_at:string|null;summary:Summary;scoped:boolean};
export type Item={id:number;expected_amount:string;actual_amount:string;difference_amount:string;status:string;exception_type:string|null;resolution_note:string|null;resolved_at:string|null;ticket_reference:string|null;ticket_url:string|null;payment_reference:string|null;payment_url:string|null;ledger_url:string|null;settlement_url:string|null;exception_url:string};
export type Filters={from?:string;to?:string;sort?:string;order?:string;search?:string;status?:string;exception_type?:string;lga_id?:string;park_id?:string};
export type Options={lgas:{id:number;name:string}[];parks:{id:number;name:string}[]};
export type ItemPage=Paginated<Item>;
export const exceptionTypes=['payment_without_ticket','ticket_without_payment','duplicate_provider_reference','amount_mismatch','missing_ledger_entry','missing_settlement','reversal_exception','unknown'];
export const label=(value:string)=>value.replaceAll('_',' ').replace(/^./,c=>c.toUpperCase());
