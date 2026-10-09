<?php

namespace App\Domains\Reporting\Reports;

use App\Domains\Identity\Models\User;
use App\Domains\Reporting\Contracts\Report;
use App\Domains\Reporting\Queries\ReportQuery;
use Illuminate\Database\Eloquent\Builder;

abstract class BaseReport implements Report
{
    public string $type;

    public string $title;

    public string $family;

    public string $basis;

    public ?string $group = null;

    public function filters(): array
    {
        return app(ReportQuery::class)->filterKeys($this);
    }

    public function query(User $u, array $criteria): Builder
    {
        return app(ReportQuery::class)->query($this, $u, $criteria);
    }

    public function summary(User $u, array $criteria): array
    {
        return app(ReportQuery::class)->summary($this, $u, $criteria);
    }

    public function columns(): array
    {
        return app(ReportQuery::class)->columns($this);
    }

    public function export(User $u, array $criteria): iterable
    {
        return app(ReportQuery::class)->rows($this, $u, $criteria);
    }
}
