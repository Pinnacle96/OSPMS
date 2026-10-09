<?php

namespace App\Domains\Incidents\Services;

use App\Domains\Enforcement\Models\Inspection;
use App\Domains\Identity\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Spatie\Activitylog\Models\Activity;

class OperationalRecordView
{
    public function record(Model $r, User $u, bool $detail = false): array
    {
        $r->load(['park', 'operator', 'driver', 'vehicle']);
        $kind = strtolower(class_basename($r));
        $safe = fn ($x, $name) => $x ? ['public_id' => $x->public_id, 'name' => $name] : null;
        $data = ['public_id' => $r->public_id, 'reference' => $r->{$kind.'_reference'}, 'category' => $r->category ?? $r->inspection_type, 'status' => $r instanceof Inspection ? $r->result->value : $r->status->value, 'occurred_at' => ($r->occurred_at ?? $r->issued_at)?->toIso8601String(), 'park' => $safe($r->park, $r->park?->name)];
        if (! $detail) {
            return $data;
        }
        $data += ['description' => $r->description ?? $r->notes, 'operator' => $safe($r->operator, $r->operator?->name), 'driver' => $safe($r->driver, trim(($r->driver?->first_name ?? '').' '.($r->driver?->last_name ?? ''))), 'vehicle' => $safe($r->vehicle, $r->vehicle?->registration_number), 'resolution' => $r->resolution, 'resolved_at' => $r->resolved_at?->toIso8601String()];
        $data['evidence'] = $r->evidence()->orderByDesc('id')->get()->map(fn ($m) => ['public_id' => $m->public_id, 'name' => $m->original_name, 'mime_type' => $m->mime_type, 'size_bytes' => $m->size_bytes, 'created_at' => $m->created_at, 'href' => '/'.$kind.'s/'.$r->public_id.'/evidence/'.$m->public_id]);
        $data['history'] = Activity::where('subject_type', $r->getMorphClass())->where('subject_id', $r->id)->with('causer')->orderByDesc('id')->paginate(15, ['*'], 'history_page')->withQueryString()->through(fn ($a) => ['event' => $a->description, 'actor' => $a->causer?->name ?? 'Retained user', 'at' => $a->created_at->toIso8601String(), 'from' => $a->properties->get('from'), 'to' => $a->properties->get('to') ?? $a->properties->get('status'), 'reason' => $a->properties->get('reason') ?? $a->properties->get('description')]);
        $data['can_upload'] = Gate::forUser($u)->allows('evidence', $r);
        if ($r instanceof Inspection) {
            $data['violations'] = $r->violations()->get()->filter(fn ($v) => Gate::forUser($u)->allows('view', $v))->map(fn ($v) => ['public_id' => $v->public_id, 'reference' => $v->violation_reference, 'status' => $v->status->value])->values();
        } elseif ($kind === 'violation') {
            $data['inspection'] = $r->inspection && Gate::forUser($u)->allows('view', $r->inspection) ? ['public_id' => $r->inspection->public_id, 'reference' => $r->inspection->inspection_reference] : null;
        }

        return $data;
    }

    public function listing(Request $request, string $class): array
    {
        Gate::authorize('viewAny', $class);
        $kind = strtolower(class_basename($class));
        $status = $kind === 'inspection' ? 'result' : 'status';
        $statuses = $kind === 'inspection' ? ['compliant', 'non_compliant', 'requires_review'] : ($kind === 'incident' ? ['reported', 'under_review', 'escalated', 'resolved', 'closed'] : ['open', 'resolved']);
        $f = $request->validate(['search' => 'nullable|string|max:100', 'status' => ['nullable', Rule::in($statuses)], 'category' => 'nullable|string|max:80', 'park' => 'nullable|ulid', 'from' => 'nullable|date_format:Y-m-d', 'to' => 'nullable|date_format:Y-m-d|after_or_equal:from', 'order' => 'nullable|in:asc,desc', 'page' => 'nullable|integer|min:1']);
        $q = app(OperationalScope::class)->query($class::query(), $request->user());
        if ($f['search'] ?? null) {
            $term = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $f['search']).'%';
            $q->whereRaw($kind."_reference LIKE ? ESCAPE '!'", [$term]);
        }
        if ($f['status'] ?? null) {
            $q->where($status, $f['status']);
        }
        if ($f['category'] ?? null) {
            $q->where($kind === 'inspection' ? 'inspection_type' : 'category', $f['category']);
        }
        if ($f['park'] ?? null) {
            $q->whereHas('park', fn ($p) => $p->where('public_id', $f['park']));
        }
        $date = $kind === 'violation' ? 'issued_at' : 'occurred_at';
        if ($f['from'] ?? null) {
            $q->where($date, '>=', CarbonImmutable::parse($f['from'], 'Africa/Lagos')->startOfDay()->setTimezone(config('app.timezone')));
        }
        if ($f['to'] ?? null) {
            $q->where($date, '<', CarbonImmutable::parse($f['to'], 'Africa/Lagos')->addDay()->startOfDay()->setTimezone(config('app.timezone')));
        }
        $parks = app(OperationalScope::class)->parks($request->user())->orderBy('name')->get(['public_id', 'name']);

        return ['records' => $q->with('park')->orderBy($date, $f['order'] ?? 'desc')->orderBy('id', $f['order'] ?? 'desc')->paginate(15)->withQueryString()->through(fn ($r) => $this->record($r, $request->user())), 'filters' => $f, 'statuses' => $statuses, 'parks' => $parks];
    }
}
