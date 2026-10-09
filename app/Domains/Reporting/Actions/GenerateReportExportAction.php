<?php

namespace App\Domains\Reporting\Actions;

use App\Domains\Identity\Models\User;
use App\Domains\Reporting\Models\ReportExport;
use App\Domains\Reporting\Services\ReportCatalog;
use App\Domains\Reporting\Services\ReportFileWriter;
use App\Domains\Reporting\Services\ReportFilters;
use App\Domains\Reporting\Services\ReportScope;
use App\Domains\System\Models\MediaAttachment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class GenerateReportExportAction
{
    public function execute(int $id): void
    {
        $path = null;
        try {
            DB::transaction(function () use ($id, &$path) {
                $e = ReportExport::whereKey($id)->lockForUpdate()->firstOrFail();
                $d = $e->response_payload;
                if ($d['status'] !== 'queued') {
                    return;
                }
                $u = User::whereKey($e->user_id)->lockForUpdate()->firstOrFail();
                $r = app(ReportCatalog::class)->get($d['type']);
                app(ReportCatalog::class)->authorize($u, $r, true);
                abort_unless(hash_equals($d['scope_signature'], app(ReportScope::class)->signature($u)), 403);
                $f = app(ReportFilters::class)->resolve($u, $r, $d['criteria']);
                $q = $r->query($u, $f);
                abort_if($q->count() > config('reports.max_source_rows'), 422);
                $table = $q->getModel()->getTable();
                $coverage = $q->select($table.'.id')->lazyById(1000)->pluck('id')->all();
                $summary = $r->summary($u, $f);
                $path = 'report-exports/'.$d['public_id'].'/'.Str::ulid().'.'.$d['format'];
                $count = app(ReportFileWriter::class)->write($r, $u, $f, $d['format'], $path, $summary);
                // Retain one consistent source snapshot; downloads recheck grants and every covered record.
                abort_unless(hash_equals($d['scope_signature'], app(ReportScope::class)->signature($u)), 403);
                $disk = Storage::disk('local');
                $m = MediaAttachment::create(['attachable_type' => $u->getMorphClass(), 'attachable_id' => $u->id, 'category' => 'report_export', 'disk' => 'local', 'path' => $path, 'original_name' => $d['type'].'-'.$d['public_id'].'.'.$d['format'], 'mime_type' => ReportFileWriter::MIMES[$d['format']], 'size_bytes' => $disk->size($path), 'file_hash' => hash_file('sha256', $disk->path($path)), 'uploaded_by' => $u->id, 'created_at' => now()]);
                $e->update(['response_status' => 200, 'response_payload' => [...$d, 'status' => 'completed', 'generated_at' => now()->toIso8601String(), 'media_public_id' => $m->public_id, 'coverage_ids' => $coverage, 'row_count' => $count, 'summary' => $summary]]);
                activity('reports')->causedBy($u)->performedOn($m)->withProperties(['export' => $d['public_id'], 'type' => $d['type'], 'format' => $d['format'], 'rows' => $count])->log('report_export_completed');
            });
        } catch (\Throwable $ex) {
            $this->fail($id);
            throw $ex;
        } finally {
            if ($path && ! MediaAttachment::where('disk', 'local')->where('path', $path)->exists()) {
                Storage::disk('local')->delete($path);
            }
        }
    }

    public function fail(int $id): void
    {
        DB::transaction(function () use ($id) {
            $e = ReportExport::whereKey($id)->lockForUpdate()->first();
            if (! $e || $e->response_payload['status'] !== 'queued') {
                return;
            }$d = $e->response_payload;
            $e->update(['response_status' => 500, 'response_payload' => [...$d, 'status' => 'failed', 'error' => 'Export could not be generated. Check current access and filters, then retry.']]);
            activity('reports')->causedBy(User::find($e->user_id))->withProperties(['export' => $d['public_id']])->log('report_export_failed');
        });
    }
}
