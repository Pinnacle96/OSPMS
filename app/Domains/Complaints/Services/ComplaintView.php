<?php

namespace App\Domains\Complaints\Services;

use App\Domains\Complaints\Models\Complaint;
use App\Domains\Complaints\Policies\ComplaintPolicy;
use App\Domains\Identity\Models\User;
use Illuminate\Support\Facades\Gate;
use Spatie\Activitylog\Models\Activity;

class ComplaintView
{
    public function record(Complaint $c, User $u, bool $detail = false): array
    {
        $c->loadMissing(['park', 'operator', 'driver', 'vehicle', 'assignee']);
        $data = ['public_id' => $c->public_id, 'reference' => $c->complaint_reference, 'category' => $c->category, 'status' => $c->status->value, 'source' => $c->source->value, 'park' => $c->park?->only('public_id', 'name'), 'assignee' => $c->assignee?->only('public_id', 'name'), 'created_at' => $c->created_at->toIso8601String()];
        if (! $detail) {
            return $data;
        }
        $external = app(ComplaintPolicy::class)->external($u);

        return [...$data, 'history' => $external ? [] : Activity::where('subject_type', $c->getMorphClass())->where('subject_id', $c->id)->where('log_name', 'complaints')->with('causer')->orderByDesc('id')->limit(100)->get()->map(fn ($a) => ['event' => $a->description, 'actor' => $a->causer?->name ?? 'Public submitter', 'at' => $a->created_at->toIso8601String(), 'from' => $a->properties->get('from'), 'to' => $a->properties->get('to'), 'reason' => $a->properties->get('reason')]), 'description' => $c->description, 'resolution' => $c->resolution, 'resolved_at' => $c->resolved_at?->toIso8601String(), 'contact' => Gate::forUser($u)->allows('contacts', $c) ? $c->only('complainant_name', 'complainant_phone', 'complainant_email') : null, 'operator' => $c->operator?->only('public_id', 'name'), 'driver' => $c->driver?->only('public_id', 'first_name', 'last_name'), 'vehicle' => $c->vehicle?->only('public_id', 'registration_number'), 'notes' => $c->notes()->with('author')->when($external, fn ($q) => $q->where('is_internal', false))->orderBy('created_at')->orderBy('id')->get()->map(fn ($n) => ['note' => $n->note, 'is_internal' => $n->is_internal, 'author' => $n->author?->name ?? 'Retained author', 'created_at' => $n->created_at->toIso8601String()]), 'evidence' => Gate::forUser($u)->allows('viewEvidence', $c) ? $c->evidence()->orderBy('created_at')->get()->map(fn ($m) => ['public_id' => $m->public_id, 'name' => $m->original_name, 'mime_type' => $m->mime_type, 'size_bytes' => $m->size_bytes, 'href' => '/complaints/'.$c->public_id.'/evidence/'.$m->public_id]) : [], 'can_upload' => Gate::forUser($u)->allows('evidence',$c)];
    }
}
