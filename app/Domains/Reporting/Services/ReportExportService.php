<?php

namespace App\Domains\Reporting\Services;

use App\Domains\Identity\Models\User;
use App\Domains\Reporting\Actions\GenerateReportExportAction;
use App\Domains\Reporting\Jobs\GenerateReportExport;
use App\Domains\Reporting\Models\ReportExport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ReportExportService
{
    public function request(User $u, string $type, array $input): ReportExport
    {
        $r = app(ReportCatalog::class)->get($type);
        app(ReportCatalog::class)->authorize($u, $r, true);
        $v = validator($input, ['idempotency_key' => 'required|string|regex:/^[a-f0-9]{64}$/', 'format' => 'required|in:csv,xlsx,pdf', 'queue' => 'sometimes|boolean'])->validate();
        $f = app(ReportFilters::class)->resolve($u, $r, $input);
        $count = $r->query($u, $f)->count();
        if ($count > config('reports.max_source_rows')) {
            throw ValidationException::withMessages(['format' => 'Narrow the filters before exporting this many source records.']);
        }
        if ($v['format'] === 'pdf' && $r->family !== 'revenue' && $count > config('reports.pdf_max_rows')) {
            throw ValidationException::withMessages(['format' => 'PDF output exceeds the row limit. Narrow the filters or choose CSV or Excel.']);
        }
        $hash = hash('sha256', json_encode([$u->id, $type, $v['format'], $f, (bool) ($v['queue'] ?? false)], JSON_THROW_ON_ERROR));
        $queued = $count > config('reports.queue_threshold') || ($v['queue'] ?? false);
        $e = DB::transaction(function () use ($u, $r, $v, $f, $hash, $queued) {
            $d = ['public_id' => (string) Str::ulid(), 'type' => $r->type, 'title' => $r->title, 'format' => $v['format'], 'criteria' => $f, 'status' => 'queued', 'requested_at' => now()->toIso8601String(), 'scope_signature' => app(ReportScope::class)->signature($u)];
            $inserted = DB::table('idempotency_keys')->insertOrIgnore(['idempotency_key' => $v['idempotency_key'], 'operation' => 'report_export', 'user_id' => $u->id, 'request_hash' => $hash, 'response_status' => 202, 'response_payload' => json_encode($d, JSON_THROW_ON_ERROR), 'created_at' => now(), 'expires_at' => now()->addYear()]);
            $e = ReportExport::where('idempotency_key', $v['idempotency_key'])->lockForUpdate()->first();
            if (! $e || $e->user_id !== $u->id || ! hash_equals($e->request_hash, $hash)) {
                throw ValidationException::withMessages(['idempotency_key' => 'This request key already belongs to different export details.']);
            }
            if ($inserted) {
                activity('reports')->causedBy($u)->withProperties(['export' => $d['public_id'], 'type' => $r->type, 'format' => $v['format']])->log('report_export_requested');
                if ($queued) {
                    $this->enqueue($e);
                }
            }

            return $e;
        });
        if (! $queued && $e->response_payload['status'] === 'queued') {
            try {
                app(GenerateReportExportAction::class)->execute($e->id);
            } catch (\Throwable $ex) {
                report($ex);
            }
        }

        return $e->refresh();
    }

    private function enqueue(ReportExport $e): void
    {
        // The database queue shares the application connection so metadata and job insertion commit together.
        if (config('queue.connections.database.connection') && config('queue.connections.database.connection') !== config('database.default')) {
            throw new \LogicException('Report queue must use the application database connection.');
        }
        Queue::connection('database')->pushOn('reports', (new GenerateReportExport($e->id))->beforeCommit());
    }

    public function retry(User $u, ReportExport $e): void
    {
        DB::transaction(function () use ($u, $e) {
            $e = ReportExport::whereKey($e->id)->where('user_id', $u->id)->lockForUpdate()->firstOrFail();
            $d = $e->response_payload;
            $r = app(ReportCatalog::class)->get($d['type']);
            app(ReportCatalog::class)->authorize($u, $r, true);
            $f = app(ReportFilters::class)->resolve($u, $r, $d['criteria']);
            if ($d['status'] !== 'failed') {
                return;
            }$d['status'] = 'queued';
            $d['scope_signature'] = app(ReportScope::class)->signature($u);
            unset($d['error']);
            $e->update(['response_status' => 202, 'response_payload' => $d]);
            $this->enqueue($e);
            activity('reports')->causedBy($u)->withProperties(['export' => $d['public_id']])->log('report_export_retried');
        });
    }
}
