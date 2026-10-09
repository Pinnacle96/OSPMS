import type {Paginated} from '@/types';
export type Choice={public_id:string;name:string};
export type OperationalRecord={public_id:string;reference:string;category:string;status:string;occurred_at:string;description:string|null;park:Choice|null;operator:Choice|null;driver:Choice|null;vehicle:Choice|null;resolution:string|null;resolved_at:string|null;can_upload:boolean;evidence:{public_id:string;name:string;mime_type:string;size_bytes:number;created_at:string;href:string}[];history:Paginated<{event:string;actor:string;at:string;from:string|null;to:string|null;reason:string|null}>;violations?:{public_id:string;reference:string;status:string}[];inspection?:{public_id:string;reference:string}|null};
export type RecordKind='incidents'|'inspections'|'violations';
export type RecordListProps={records:Paginated<OperationalRecord>;filters:{search?:string;status?:string;category?:string;park?:string;from?:string;to?:string;order?:string};parks:Choice[];statuses:string[];can_create?:boolean};
