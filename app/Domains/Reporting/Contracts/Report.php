<?php

namespace App\Domains\Reporting\Contracts;

use App\Domains\Identity\Models\User;
use Illuminate\Database\Eloquent\Builder;

interface Report
{
    public function filters(): array;

    public function query(User $u, array $criteria): Builder;

    public function summary(User $u, array $criteria): array;

    public function columns(): array;

    public function export(User $u, array $criteria): iterable;
}
