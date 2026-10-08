<?php

namespace App\Support\Money;

use Illuminate\Validation\ValidationException;

class Money
{
    public static function normalize(string $amount): string
    {
        if (! preg_match('/^(0|[1-9][0-9]{0,12})(?:\.([0-9]{1,2}))?$/D', $amount, $parts)) {
            throw ValidationException::withMessages(['amount' => 'Enter a non-negative amount with at most two decimal places.']);
        }

        return $parts[1].'.'.str_pad($parts[2] ?? '', 2, '0');
    }
}
