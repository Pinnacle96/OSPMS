<?php

namespace App\Http\Controllers\Web;

use App\Domains\Complaints\Actions\CreateComplaintAction;
use App\Domains\Parks\Models\Park;
use App\Http\Controllers\Controller;
use App\Http\Requests\Complaints\CreateComplaintRequest;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class PublicComplaintController extends Controller
{
    public function create(Request $r)
    {
        $key = bin2hex(random_bytes(32));
        if (! $r->session()->has('public_complaint_binding')) {
            $r->session()->put('public_complaint_binding', bin2hex(random_bytes(32)));
        }
        $r->session()->put('public_complaint_confirmations', array_slice([...$r->session()->get('public_complaint_confirmations', []), $key], -5));

        return Inertia::render('Public/ComplaintForm', ['parks' => Park::where('status', 'active')->orderBy('name')->get(['public_id', 'name']), 'idempotency_key' => $key]);
    }

    public function store(CreateComplaintRequest $r)
    {
        if (! in_array($r->validated('idempotency_key'), $r->session()->get('public_complaint_confirmations', []), true)) {
            throw ValidationException::withMessages(['idempotency_key' => 'Refresh this form to obtain a submission confirmation.']);
        }
        $nonce = $r->session()->get('public_complaint_binding');
        if (! is_string($nonce) || ! preg_match('/\A[a-f0-9]{64}\z/D', $nonce)) {
            throw ValidationException::withMessages(['idempotency_key' => 'Refresh this form to obtain a submission confirmation.']);
        }
        $binding = hash_hmac('sha256', $nonce, config('app.key'));
        $c = app(CreateComplaintAction::class)->execute(null, $r->validated(), $binding);
        $r->session()->put('public_complaint_reference', $c->complaint_reference);

        return redirect('/public/complaints/success', 303);
    }

    public function success(Request $r)
    {
        $ref = $r->session()->get('public_complaint_reference');
        if (! $ref) {
            return redirect('/public/complaints');
        }

        return Inertia::render('Public/ComplaintSuccess', ['reference' => $ref]);
    }
}
