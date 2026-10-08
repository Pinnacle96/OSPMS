import type {Paginated} from '@/types';
import type {TicketContext} from '@/types/ticketing';
export type FinancialRow={public_id:string;amount:string;currency:string;status?:string;payment_reference?:string;transaction_reference?:string;ticket_reference?:string;vehicle?:string|null;park?:string|null;provider?:string;channel?:string;direction?:string;transaction_type?:string;initiated_at?:string;occurred_at?:string;paid_at?:string|null};
export type FinancialPage=Paginated<FinancialRow>;
export type ReceiptData={receipt_number:string;payment_reference:string;ticket_reference:string;amount:string;currency:string;channel:string;payment_status:string;paid_at:string|null;issued_at:string;ticket_status:string;vehicle:string|null;park:string|null;fee_name:string;demo:boolean;valid:boolean;result:string};
export type ReceiptProps={receipt:ReceiptData&{public_id:string;ticket_public_id:string;payment_public_id:string;context:TicketContext};verification_url:string;qr_image:string};
