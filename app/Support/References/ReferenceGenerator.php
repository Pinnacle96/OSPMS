<?php

namespace App\Support\References;

use Illuminate\Support\Str;

class ReferenceGenerator
{
    public function generate(string $prefix): string
    {
        return 'OSPM-'.$prefix.'-'.Str::ulid();
    }
}
