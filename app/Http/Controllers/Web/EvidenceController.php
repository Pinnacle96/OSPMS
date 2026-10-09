<?php

namespace App\Http\Controllers\Web;

use App\Domains\Complaints\Models\Complaint;
use App\Domains\Enforcement\Models\Inspection;
use App\Domains\Enforcement\Models\Violation;
use App\Domains\Incidents\Models\Incident;
use App\Domains\Incidents\Services\EvidenceService;
use App\Domains\System\Models\MediaAttachment;
use App\Http\Controllers\Controller;
use App\Http\Requests\Incidents\UploadEvidenceRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class EvidenceController extends Controller
{
    private function record(Request $r)
    {
        $kind = $r->route('kind');
        $class = ['complaints' => Complaint::class, 'incidents' => Incident::class, 'inspections' => Inspection::class, 'violations' => Violation::class][$kind] ?? null;
        abort_unless($class, 404);

        return $class::where('public_id', $r->route('record'))->firstOrFail();
    }

    public function store(UploadEvidenceRequest $r)
    {
        $record = $this->record($r);
        app(EvidenceService::class)->upload($r->user(), $record, $r->validated());

        return back(303)->with('success', 'Private evidence retained.');
    }

    public function show(Request $r, string $kind, string $record, MediaAttachment $media)
    {
        $record = $this->record($r);
        Gate::authorize($kind === 'complaints' ? 'viewEvidence' : 'view', $record);
        abort_unless($media->attachable_type === $record->getMorphClass() && $media->attachable_id === $record->id && $media->disk === 'local', 404);
        activity('incidents')->causedBy($r->user())->performedOn($record)->withProperties(['evidence' => $media->public_id])->log('evidence_downloaded');

        return Storage::disk('local')->download($media->path, 'evidence-'.$media->public_id.'.'.pathinfo($media->path, PATHINFO_EXTENSION), ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
