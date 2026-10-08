<?php

namespace App\Support\Payments;

use App\Support\Payments\Contracts\PaymentGateway;
use App\Support\Payments\Gateways\DemoPaymentGateway;

class PaymentGatewayManager
{
    public function demoEnabled(): bool
    {
        return config('ospm.demo_mode') && config('ospm.payment_mode') === 'demo' && config('ospm.payment_provider') === 'demo' && ! app()->environment('production');
    }

    public function gateway(): PaymentGateway
    {
        abort_unless($this->demoEnabled(), 403, 'Demo payment simulation is disabled in this environment.');

        return app(DemoPaymentGateway::class);
    }
}
