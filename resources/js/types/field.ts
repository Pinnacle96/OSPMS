import type {Paginated} from '@/types';
export type FieldRecord={public_id:string;reference:string;name:string;status:string;vehicle_type?:string;make?:string|null;model?:string|null;licence_expiry?:string|null;roadworthiness_expiry?:string|null;insurance_expiry?:string|null};
export type FieldAssignment={context:string;driver:FieldRecord;vehicle:FieldRecord;operator:FieldRecord;park:{public_id:string;name:string};status:string;starts_at:string;ends_at:string|null};
export type FieldDetail={record:FieldRecord;assignments:Paginated<FieldAssignment>;compliance:{status:string;checks:{label:string;value:string;state:string}[];note:string};inspection_url:string|null};
export type SafeVerification={ticket_reference:string;amount:string;currency:string;vehicle:string|null;park:string|null;fee_name:string;ticket_status:string;payment_status:string;valid:boolean;result:string;issued_at:string;expires_at:string|null;receipt_number?:string;payment_reference?:string;demo?:boolean};
