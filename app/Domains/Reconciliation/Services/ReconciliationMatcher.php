<?php

namespace App\Domains\Reconciliation\Services;

use App\Domains\Finance\Models\FinancialTransaction;
use App\Domains\Finance\Models\SettlementItem;
use App\Domains\Payments\Models\Payment;
use App\Domains\Reconciliation\Models\ReconciliationRun;
use App\Domains\Ticketing\Models\Ticket;
use Brick\Math\BigDecimal;

class ReconciliationMatcher
{
    public function match(ReconciliationRun $run): array
    {
        $evidence = [];
        $paid = Payment::whereIn('status', ['successful', 'reversed', 'refunded'])->where('paid_at', '<', $run->period_end)
            ->when($run->provider, fn ($q) => $q->where('provider', $run->provider));
        $tickets = Ticket::where('currency', 'NGN')->where(function ($q) use ($run, $paid) {
            $q->where(fn ($issued) => $issued->where('issued_at', '>=', $run->period_start)->where('issued_at', '<', $run->period_end)->where('ticket_status', '!=', 'cancelled'))
                ->orWhereIn('id', (clone $paid)->where(fn ($p) => $p->where('paid_at', '>=', $run->period_start)->orWhere(fn ($r) => $r->where('reversed_at', '>=', $run->period_start)->where('reversed_at', '<', $run->period_end)))->select('ticket_id'));
        })->when($run->lga_id, fn ($q) => $q->where('lga_id', $run->lga_id))->when($run->park_id, fn ($q) => $q->where('park_id', $run->park_id));
        foreach ($tickets->orderBy('id')->cursor() as $ticket) {
            $payments = (clone $paid)->where('ticket_id', $ticket->id)->orderBy('id')->get();
            // A provider-specific run must not classify another provider's paid ticket as unpaid.
            if ($run->provider && $payments->isEmpty() && Payment::where('ticket_id', $ticket->id)->whereIn('status', ['successful', 'reversed', 'refunded'])->where('provider', '!=', $run->provider)->exists()) {
                continue;
            }
            $evidence += $this->finding($run, $ticket, $payments);
        }
        // Defensive checks for imported/corrupt data; normal application FKs prevent orphan payments.
        if (! $run->lga_id && ! $run->park_id) {
            foreach ((clone $paid)->where('paid_at', '>=', $run->period_start)->whereDoesntHave('ticket')->get() as $payment) {
                $evidence += $this->finding($run, null, collect([$payment]));
            }
            $orphans = FinancialTransaction::where('currency', 'NGN')->where('occurred_at', '>=', $run->period_start)->where('occurred_at', '<', $run->period_end)->where(fn ($q) => $q->whereDoesntHave('payment')->orWhereDoesntHave('ticket'));
            if ($run->provider) {
                $orphans->whereHas('payment', fn ($q) => $q->where('provider', $run->provider));
            }
            foreach ($orphans->get() as $entry) {
                if ($entry->payment && ! $entry->ticket) {
                    continue;
                }
                $item = $run->items()->create(['ticket_id' => $entry->ticket_id, 'financial_transaction_id' => $entry->id, 'expected_amount' => '0.00', 'actual_amount' => '0.00', 'difference_amount' => '0.00', 'status' => 'exception', 'exception_type' => 'unknown']);
                $evidence[$item->id] = ['flags' => ['unknown'], 'explanation' => 'Ledger entry has no complete ticket/payment lineage.', 'ledger_reference' => $entry->transaction_reference, 'ledger_amount' => $entry->amount];
            }
        }

        return $evidence;
    }

    private function finding(ReconciliationRun $run, ?Ticket $ticket, $payments): array
    {
        $flags = [];
        $actual = BigDecimal::zero()->toScale(2);
        $firstLedger = null;
        $firstSettlement = null;
        $sources = [];
        $expected = $ticket?->amount ?? '0.00';
        if (! $ticket) {
            $flags[] = 'payment_without_ticket';
        }
        if ($payments->isEmpty()) {
            $flags[] = 'ticket_without_payment';
        }
        if ($payments->count() > 1) {
            $flags[] = 'unknown';
        }
        foreach ($payments as $payment) {
            if (in_array($payment->status->value, ['reversed', 'refunded'])) {
                $flags[] = 'reversal_exception';
            }
            if (! $payment->provider_reference) {
                $flags[] = 'unknown';
            } elseif (Payment::where('provider', $payment->provider)->where('provider_reference', $payment->provider_reference)->count() > 1) {
                $flags[] = 'duplicate_provider_reference';
            }
            if ($ticket && (! BigDecimal::of($ticket->amount)->isEqualTo($payment->amount) || $ticket->currency !== $payment->currency)) {
                $flags[] = 'amount_mismatch';
            }
            $credits = FinancialTransaction::where('payment_id', $payment->id)->where('direction', 'credit')->orderBy('id')->get();
            if ($credits->isEmpty()) {
                $flags[] = 'missing_ledger_entry';
            }
            if ($credits->count() > 1) {
                $flags[] = 'unknown';
            }
            $source = ['payment_reference' => $payment->payment_reference, 'provider_reference' => $payment->provider_reference, 'payment_amount' => $payment->amount, 'payment_status' => $payment->status->value, 'currency' => $payment->currency, 'credits' => []];
            foreach ($credits as $credit) {
                $firstLedger ??= $credit;
                if ($credit->ticket_id !== $ticket?->id || $credit->transaction_type !== 'payment') {
                    $flags[] = 'unknown';
                }
                if (! BigDecimal::of($payment->amount)->isEqualTo($credit->amount) || $credit->currency !== $payment->currency) {
                    $flags[] = 'amount_mismatch';
                }
                $settled = SettlementItem::with('settlement')->where('financial_transaction_id', $credit->id)->orderBy('id')->get();
                if ($settled->isEmpty()) {
                    $flags[] = 'missing_settlement';
                }
                if ($settled->count() > 1) {
                    $flags[] = 'unknown';
                }
                foreach ($settled as $si) {
                    $firstSettlement ??= $si;
                    if ($si->status !== 'settled' || $si->settlement->status->value !== 'settled') {
                        $flags[] = $si->settlement->status->value === 'reversed' ? 'reversal_exception' : 'missing_settlement';

                        continue;
                    }
                    if ($si->settlement->currency !== 'NGN' || $si->settlement->provider !== $payment->provider || ! BigDecimal::of($credit->amount)->isEqualTo($si->amount)) {
                        $flags[] = 'amount_mismatch';
                    }
                    if ($si->settlement->currency === 'NGN') {
                        $actual = $actual->plus($si->amount);
                    }
                }
                $source['credits'][] = ['reference' => $credit->transaction_reference, 'amount' => $credit->amount, 'settlements' => $settled->map(fn ($si) => ['reference' => $si->settlement->settlement_reference, 'amount' => $si->amount, 'status' => $si->settlement->status->value])->all()];
            }
            $sources[] = $source;
        }
        $priority = ['payment_without_ticket', 'reversal_exception', 'duplicate_provider_reference', 'ticket_without_payment', 'missing_ledger_entry', 'amount_mismatch', 'missing_settlement', 'unknown'];
        $flags = array_values(array_intersect($priority, array_unique($flags)));
        $item = $run->items()->create(['ticket_id' => $ticket?->id, 'payment_id' => $payments->first()?->id, 'financial_transaction_id' => $firstLedger?->id, 'settlement_item_id' => $firstSettlement?->id, 'expected_amount' => $expected, 'actual_amount' => (string) $actual, 'difference_amount' => (string) BigDecimal::of($expected)->minus($actual)->toScale(2), 'status' => $flags ? 'exception' : 'matched', 'exception_type' => $flags[0] ?? null]);

        return [$item->id => ['flags' => $flags, 'ticket_reference' => $ticket?->ticket_reference, 'sources' => $sources]];
    }
}
