<?php

namespace App\Http\Controllers\Web;

use App\Domains\Reporting\Models\ReportExport;
use App\Domains\Reporting\Queries\ReportQuery;
use App\Domains\Reporting\Services\ReportCatalog;
use App\Domains\Reporting\Services\ReportExportAccess;
use App\Domains\Reporting\Services\ReportExportService;
use App\Domains\Reporting\Services\ReportFilters;
use App\Domains\System\Models\MediaAttachment;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class ReportController extends Controller
{
    public function index(Request $q)
    {
        abort_unless($q->user()->can('view_reports'), 403);

        return Inertia::render('Reports/Index', ['reports' => app(ReportCatalog::class)->listing($q->user())]);
    }

    public function show(Request $q, string $reportType)
    {
        $r = app(ReportCatalog::class)->get($reportType);
        $f = app(ReportFilters::class)->resolve($q->user(), $r, $q->all());
        $engine = app(ReportQuery::class);
        $viewer = validator($q->all(), ['page' => 'sometimes|integer|min:1|max:100000', 'choice_search' => 'nullable|string|max:100'])->validate();
        $page = $viewer['page'] ?? 1;
        $size = config('reports.page_size');
        if ($r->family === 'revenue') {
            $all = $engine->grouped($r, $q->user(), $f);
            $records = new LengthAwarePaginator($all->forPage($page, $size)->values(), $all->count(), $size, $page, ['path' => $q->url(), 'query' => $f]);
        } else {
            $records = $engine->eager($r, $r->query($q->user(), $f))->orderByDesc($r->query($q->user(), $f)->getModel()->getTable().'.id')->paginate($size, ['*'], 'page', $page)->withQueryString()->through(fn ($v) => $engine->row($r, $v));
        }

        return Inertia::render('Reports/Show', ['report' => ['type' => $r->type, 'title' => $r->title, 'basis' => $r->basis], 'records' => $records, 'columns' => $r->columns(), 'summary' => $r->summary($q->user(), $f), 'filters' => $f, 'options' => $engine->options($r, $q->user(), $f, $viewer['choice_search'] ?? ''), 'choice_search' => $viewer['choice_search'] ?? '', 'can_export' => app(ReportCatalog::class)->allowed($q->user(), $r, true), 'idempotency_key' => bin2hex(random_bytes(32)), 'limits' => ['queue_threshold' => config('reports.queue_threshold'), 'pdf_max_rows' => config('reports.pdf_max_rows'), 'filter_options_limit' => config('reports.filter_options_limit')]]);
    }

    public function store(Request $q, string $reportType)
    {
        $e = app(ReportExportService::class)->request($q->user(), $reportType, $q->all());

        return redirect('/reports/exports', 303)->with($e->response_payload['status'] === 'failed' ? 'error' : 'success', $e->response_payload['status'] === 'failed' ? $e->response_payload['error'] : ($e->response_payload['status'] === 'completed' ? 'Export is ready.' : 'Export request saved. Check its status in Saved exports.'));
    }

    public function exports(Request $q)
    {
        abort_unless($q->user()->can('view_reports'), 403);
        $records = ReportExport::where('user_id', $q->user()->id)->orderByDesc('id')->paginate(15)->through(fn ($e) => app(ReportExportAccess::class)->dto($q->user(), $e));

        return Inertia::render('Reports/Exports', ['records' => $records]);
    }

    public function retry(Request $q, string $export)
    {
        $e = app(ReportExportAccess::class)->owned($q->user(), $export);
        app(ReportExportService::class)->retry($q->user(), $e);

        return back(303)->with('success', 'Export queued for retry.');
    }

    public function download(Request $q, string $export)
    {
        $access = app(ReportExportAccess::class);
        $e = $access->owned($q->user(), $export);
        abort_unless($access->current($q->user(), $e, true), 403);
        $d = $e->response_payload;
        abort_unless($d['status'] === 'completed', 409, 'This export is not ready.');
        $m = MediaAttachment::where('public_id', $d['media_public_id'])->where('category', 'report_export')->where('attachable_type', $q->user()->getMorphClass())->where('attachable_id', $q->user()->id)->firstOrFail();
        $disk = Storage::disk('local');
        abort_unless($m->disk === 'local' && $disk->exists($m->path) && hash_equals($m->file_hash, hash_file('sha256', $disk->path($m->path))), 410, 'This export file is unavailable. Generate a new export.');
        activity('reports')->causedBy($q->user())->performedOn($m)->withProperties(['export' => $d['public_id']])->log('report_export_downloaded');

        return $disk->download($m->path, $m->original_name, ['Content-Type' => $m->mime_type, 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff', 'Referrer-Policy' => 'no-referrer']);
    }
}
