<?php

namespace App\Domains\Reporting\Services;

use App\Domains\Identity\Models\User;
use App\Domains\Reporting\Models\ReportExport;
use App\Domains\Reporting\Queries\ReportQuery;

class ReportExportAccess
{
    public function owned(User $u, string $id): ReportExport
    {
        return ReportExport::where('user_id', $u->id)->where('response_payload->public_id', $id)->firstOrFail();
    }

    public function current(User $u, ReportExport $e, bool $coverage = false): bool
    {
        $d = $e->response_payload;
        $r = app(ReportCatalog::class)->get($d['type']);
        if ($e->user_id !== $u->id || ! app(ReportCatalog::class)->allowed($u, $r, true) || ! hash_equals($d['scope_signature'], app(ReportScope::class)->signature($u))) {
            return false;
        }
        if ($coverage) {
            $q = app(ReportQuery::class)->base($r, $u);
            $table = $q->getModel()->getTable();
            foreach (array_chunk($d['coverage_ids'] ?? [], 1000) as $ids) {
                if ((clone $q)->whereIn($table.'.id', $ids)->count() !== count($ids)) {
                    return false;
                }
            }
        }

        return true;
    }

    public function dto(User $u, ReportExport $e): array
    {
        $d = $e->response_payload;

        return array_intersect_key($d, array_flip(['public_id', 'type', 'title', 'format', 'status', 'requested_at', 'generated_at', 'error'])) + ['can_download' => $d['status'] === 'completed' && $this->current($u, $e), 'can_retry' => $d['status'] === 'failed' && app(ReportCatalog::class)->allowed($u, app(ReportCatalog::class)->get($d['type']), true)];
    }
}
