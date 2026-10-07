<?php

namespace App\Domains\System\Services;

class PublicSystemConfig
{
    public function get(): array
    {
        return [
            'name' => config('ospm.name'), 'currency' => config('ospm.currency'),
            'timezone' => config('ospm.timezone'), 'demo_mode' => config('ospm.demo_mode'),
            'branding' => config('ospm.branding'),
        ];
    }
}
