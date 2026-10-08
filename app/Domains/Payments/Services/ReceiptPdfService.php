<?php

namespace App\Domains\Payments\Services;

use App\Domains\Payments\Models\Receipt;
use Dompdf\Dompdf;
use Dompdf\Options;

class ReceiptPdfService
{
    public function render(Receipt $receipt): string
    {
        $options = new Options(['isRemoteEnabled' => false, 'isPhpEnabled' => false, 'isJavascriptEnabled' => false, 'defaultFont' => 'DejaVu Sans', 'chroot' => resource_path('views'), 'tempDir' => storage_path('framework/cache'), 'fontCache' => storage_path('framework/cache')]);
        $pdf = new Dompdf($options);
        $data = app(ReceiptVerificationService::class)->detail($receipt);
        $pdf->loadHtml(view('receipts.pdf', $data)->render(), 'UTF-8');
        $pdf->setPaper('A4');
        $pdf->render();

        return $pdf->output();
    }
}
