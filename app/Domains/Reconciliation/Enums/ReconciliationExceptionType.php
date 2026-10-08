<?php

namespace App\Domains\Reconciliation\Enums;

enum ReconciliationExceptionType: string
{
    case PaymentWithoutTicket = 'payment_without_ticket';
    case TicketWithoutPayment = 'ticket_without_payment';
    case DuplicateProviderReference = 'duplicate_provider_reference';
    case AmountMismatch = 'amount_mismatch';
    case MissingLedgerEntry = 'missing_ledger_entry';
    case MissingSettlement = 'missing_settlement';
    case ReversalException = 'reversal_exception';
    case Unknown = 'unknown';
}
