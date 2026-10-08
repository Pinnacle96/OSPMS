import type {Paginated} from '@/types';
export type Correction={public_id:string;reference:string;status:string;amount:string;currency:string;reason:string;direction:string;requested_at:string;processed_at?:string|null;approved_at?:string|null;provider_reference?:string|null};
export type CorrectionDetail={record:Correction;source:{reference:string;url:string|null};ledger_url:string|null;can_review:boolean;can_process:boolean;history:Paginated<{id:number;event:string;actor:string;reason:string;occurred_at:string}>;idempotency_key:string};
export type CorrectionFilters={search?:string;status?:string;from?:string;to?:string;sort?:string;order?:string};
