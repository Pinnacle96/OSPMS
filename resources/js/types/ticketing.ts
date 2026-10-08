import type {Paginated} from '@/types';
export type TicketRow = {
    public_id: string; ticket_reference: string; fee_name_snapshot: string;
    amount: string; currency: string; ticket_status: string; payment_status: string;
    issued_at: string; expires_at: string | null; vehicle: string | null; park: string | null;
};
export type TicketContext = {
    assignment_id: number; lga: {name:string;code:string}; park:{name:string;code:string};
    operator:{name:string;reference:string}; driver:{name:string;reference:string};
    vehicle:{registration:string;type:string}; route:{origin:string;destination:string}|null;
};
export type TicketDetail = TicketRow & {
    context: TicketContext; issuer: string; fee_code: string; fee_name: string; valid:boolean; result:string;
};
export type TicketDetailProps = {
    ticket:TicketDetail; links:Record<string,string>; verification_url:string; qr_image:string; can_cancel:boolean; can_pay:boolean; payments:import('@/types/payments').FinancialPage|null;
    activities:{description:string;created_at:string;properties:{reason?:string}}[];
};
export type TicketPage = Paginated<TicketRow>;
export type Review = {assignment_id:number;revenue_head_id:number;amount:string;currency:string;fee_code_snapshot:string;fee_name_snapshot:string;context_snapshot:TicketContext;expiry_minutes:number|null;confirmation:string;request_key:string};
export const ticketStatuses = ['pending','paid','expired','cancelled','reversed'];
export const paymentStatuses = ['unpaid','pending','paid','failed','reversed','refunded'];
