<?php

namespace App\Http\Controllers\Web;

use App\Domains\Payments\Models\Receipt;
use App\Domains\Payments\Services\ReceiptPdfService;
use App\Domains\Payments\Services\ReceiptVerificationService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class ReceiptController extends Controller
{
    public function show(Request $request, Receipt $receipt, ReceiptVerificationService $service)
    {
        return $this->page($request, $receipt, $service, 'Receipts/Show');
    }

    public function print(Request $request, Receipt $receipt, ReceiptVerificationService $service)
    {
        return $this->page($request, $receipt, $service, 'Receipts/Print');
    }

    private function page(Request $request, Receipt $receipt, ReceiptVerificationService $service, string $component)
    {
        Gate::authorize('view', $receipt);
        activity('payments')->causedBy($request->user())->performedOn($receipt)->log($component === 'Receipts/Print' ? 'receipt_print_viewed' : 'receipt_viewed');

        return Inertia::render($component, $service->detail($receipt))->toResponse($request)->withHeaders(['Cache-Control' => 'private, no-store', 'Referrer-Policy' => 'no-referrer']);
    }

    public function pdf(Request $request, Receipt $receipt, ReceiptPdfService $service)
    {
        Gate::authorize('view', $receipt);
        $bytes = $service->render($receipt);
        activity('payments')->causedBy($request->user())->performedOn($receipt)->log('receipt_pdf_downloaded');

        return response($bytes, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="'.$receipt->receipt_number.'.pdf"', 'Cache-Control' => 'private, no-store', 'Referrer-Policy' => 'no-referrer', 'X-Content-Type-Options' => 'nosniff']);
    }
}
