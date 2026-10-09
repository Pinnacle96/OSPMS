<?php

namespace App\Domains\Reporting\Jobs;

use App\Domains\Reporting\Actions\GenerateReportExportAction;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GenerateReportExport implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 60;

    public bool $failOnTimeout = true;

    public function __construct(public int $exportId) {}

    public function handle(GenerateReportExportAction $action): void
    {
        $action->execute($this->exportId);
    }

    public function failed(?\Throwable $exception): void
    {
        app(GenerateReportExportAction::class)->fail($this->exportId);
    }
}
