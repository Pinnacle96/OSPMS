<?php

namespace App\Domains\Ticketing\Services;

use App\Domains\Finance\Models\Refund;
use App\Domains\Ticketing\Enums\TicketPaymentStatus;
use App\Domains\Ticketing\Enums\TicketStatus;
use App\Domains\Ticketing\Models\Ticket;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

class TicketVerificationService
{
    public function url(Ticket $ticket): string
    {
        // Use deployment configuration, never the request Host header.
        return rtrim(config('app.url'), '/').'/verify/ticket/'.$ticket->verification_token;
    }

    public function qr(Ticket $ticket): string
    {
        $writer = new Writer(new ImageRenderer(new RendererStyle(280, 4), new SvgImageBackEnd));

        return 'data:image/svg+xml;base64,'.base64_encode($writer->writeString($this->url($ticket)));
    }

    public function verify(string $token): ?array
    {
        if (! preg_match('/\A[a-f0-9]{64}\z/D', $token)) {
            return null;
        }
        $ticket = Ticket::where('verification_token', $token)->first();

        return $ticket ? $this->safe($ticket) : null;
    }

    public function safe(Ticket $ticket): array
    {
        $status = app(TicketExpiryService::class)->status($ticket);
        $valid = ! Refund::where('ticket_id', $ticket->id)->where('status', 'successful')->exists() && $status === TicketStatus::Paid && $ticket->payment_status === TicketPaymentStatus::Paid;
        $awaiting = $status === TicketStatus::Pending && in_array($ticket->payment_status, [TicketPaymentStatus::Unpaid, TicketPaymentStatus::Pending, TicketPaymentStatus::Failed], true);

        return [
            'ticket_reference' => $ticket->ticket_reference, 'vehicle' => $ticket->context_snapshot['vehicle']['registration'] ?? null,
            'park' => $ticket->context_snapshot['park']['name'] ?? null, 'fee_name' => $ticket->fee_name_snapshot,
            'amount' => $ticket->amount, 'currency' => $ticket->currency, 'issued_at' => $ticket->issued_at, 'expires_at' => $ticket->expires_at,
            'ticket_status' => $status->value, 'payment_status' => $ticket->payment_status->value,
            'valid' => $valid, 'result' => $valid ? 'Valid ticket' : ($awaiting ? 'Authentic ticket — payment required' : 'Not valid for use'),
        ];
    }
}
