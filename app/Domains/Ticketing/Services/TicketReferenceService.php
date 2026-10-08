<?php

namespace App\Domains\Ticketing\Services;

use Illuminate\Support\Str;

class TicketReferenceService
{
    public function generate(): string
    {
        return config('ospm.reference_prefix', 'OSPM').'-'.now()->format('Y').'-'.Str::ulid();
    }
}
