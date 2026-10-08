<?php

namespace App\Domains\Payments\Services;

use App\Domains\Finance\Models\FinancialTransaction;
use App\Domains\Payments\Models\Receipt;
use App\Domains\Ticketing\Services\TicketExpiryService;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

class ReceiptVerificationService
{
    public function url(Receipt $r): string
    {
        return rtrim(config('app.url'), '/').'/verify/receipt/'.$r->verification_token;
    }

    public function qr(Receipt $r): string
    {
        return 'data:image/svg+xml;base64,'.base64_encode((new Writer(new ImageRenderer(new RendererStyle(280, 4), new SvgImageBackEnd)))->writeString($this->url($r)));
    }

    public function verify(string $token): ?array
    {
        if (! preg_match('/\A[a-f0-9]{64}\z/D', $token)) {
            return null;
        } $r = Receipt::where('verification_token', $token)->first();

        return $r ? $this->safe($r) : null;
    }

    public function safe(Receipt $r): array
    {
        $p = $r->payment;
        $t = $r->ticket;
        $valid = $p->status->value === 'successful' && FinancialTransaction::where('payment_id', $p->id)->where('transaction_type', 'payment')->where('direction', 'credit')->exists();

        return ['receipt_number' => $r->receipt_number, 'payment_reference' => $p->payment_reference, 'ticket_reference' => $t->ticket_reference, 'amount' => $p->amount, 'currency' => $p->currency, 'channel' => $p->channel, 'payment_status' => $p->status->value, 'paid_at' => $p->paid_at, 'issued_at' => $r->issued_at, 'ticket_status' => app(TicketExpiryService::class)->status($t)->value, 'vehicle' => $t->context_snapshot['vehicle']['registration'] ?? null, 'park' => $t->context_snapshot['park']['name'] ?? null, 'fee_name' => $t->fee_name_snapshot, 'demo' => $p->provider === 'demo', 'valid' => $valid, 'result' => $valid ? 'Valid demo receipt' : 'Receipt not valid'];
    }

    public function detail(Receipt $r): array
    {
        return ['receipt' => [...$this->safe($r), 'public_id' => $r->public_id, 'ticket_public_id' => $r->ticket->public_id, 'payment_public_id' => $r->payment->public_id, 'context' => $r->ticket->context_snapshot], 'verification_url' => $this->url($r), 'qr_image' => $this->qr($r)];
    }
}
