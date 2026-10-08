<?php

namespace App\Http\Controllers\Web;

use App\Domains\Finance\Services\FinanceListFilter;
use App\Domains\Payments\Queries\FinancialFilterOptions;
use App\Domains\Reconciliation\Actions\ResolveReconciliationExceptionAction;
use App\Domains\Reconciliation\Actions\StartReconciliationAction;
use App\Domains\Reconciliation\Jobs\ProcessReconciliationRun;
use App\Domains\Reconciliation\Models\ReconciliationItem;
use App\Domains\Reconciliation\Models\ReconciliationRun;
use App\Domains\Reconciliation\Services\LatestReconciliation;
use App\Domains\Reconciliation\Services\ReconciliationAccess;
use App\Domains\Reconciliation\Services\ReconciliationSummaryService;
use App\Domains\Reconciliation\Services\ReconciliationView;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\FinanceListRequest;
use App\Http\Requests\Finance\ResolveReconciliationRequest;
use App\Http\Requests\Finance\StartReconciliationRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class ReconciliationController extends Controller
{
    private function items(Request $request, $query, array $filters)
    {
        $query = app(ReconciliationAccess::class)->items($query, $request->user());
        foreach (['status', 'exception_type'] as $key) {
            if (! empty($filters[$key])) {
                $query->where($key, $filters[$key]);
            }
        }
        foreach (['lga_id', 'park_id'] as $key) {
            if (! empty($filters[$key])) {
                $query->whereHas('ticket', fn ($t) => $t->where($key, $filters[$key]));
            }
        }
        if (! empty($filters['search'])) {
            $query->whereHas('ticket', fn ($t) => $t->where('ticket_reference', 'like', '%'.$filters['search'].'%'));
        }

        return app(FinanceListFilter::class)->dates($query, 'reconciliation_items.created_at', $filters);
    }

    public function dashboard(FinanceListRequest $request)
    {
        Gate::authorize('viewAny', ReconciliationRun::class);
        $filters = $request->validated();
        $query = $this->items($request, app(LatestReconciliation::class)->query(), $filters);

        return Inertia::render('Finance/Reconciliation/Dashboard', ['summary' => app(ReconciliationSummaryService::class)->get(clone $query), 'items' => $this->page($request, (clone $query)->whereNotNull('exception_type')), 'filters' => $filters, 'can_start' => $request->user()->can('create', ReconciliationRun::class), ...app(FinancialFilterOptions::class)->get($request->user())]);
    }

    public function index(FinanceListRequest $request)
    {
        Gate::authorize('viewAny', ReconciliationRun::class);
        $filters = $request->validated();
        $query = app(ReconciliationAccess::class)->runs(ReconciliationRun::query(), $request->user())->when($filters['search'] ?? null, fn ($q, $s) => $q->where('reconciliation_reference', 'like', '%'.$s.'%'));
        // A scoped viewer must not filter by another scope's exception outcome.
        if (! empty($filters['status']) && $request->user()->can('access_statewide')) {
            $query->where('status', $filters['status']);
        }

        return Inertia::render('Finance/Reconciliation/Index', ['records' => app(FinanceListFilter::class)->sort(app(FinanceListFilter::class)->dates($query, 'started_at', $filters), $filters, ['started_at', 'period_start', 'reconciliation_reference'], 'started_at')->paginate(20)->withQueryString()->through(fn ($run) => app(ReconciliationView::class)->run($run, $request->user())), 'filters' => $filters, 'statewide' => $request->user()->can('access_statewide'), 'can_start' => $request->user()->can('create', ReconciliationRun::class)]);
    }

    public function create(Request $request)
    {
        Gate::authorize('create', ReconciliationRun::class);

        return Inertia::render('Finance/Reconciliation/Create', ['period' => ['from' => now(config('ospm.timezone'))->startOfMonth()->toDateString(), 'to' => now(config('ospm.timezone'))->toDateString()], 'idempotency_key' => bin2hex(random_bytes(32)), ...app(FinancialFilterOptions::class)->get($request->user())]);
    }

    public function store(StartReconciliationRequest $request, StartReconciliationAction $action)
    {
        $run = $action->execute($request->user(), $request->validated());
        if ($run->status->value === 'queued') {
            // Presentation datasets execute synchronously; the same job supports database workers.
            if (config('ospm.demo_mode') && ! app()->environment('production')) {
                ProcessReconciliationRun::dispatchSync($run->id);
            } else {
                ProcessReconciliationRun::dispatch($run->id);
            }
        }

        return redirect('/finance/reconciliation/runs/'.$run->public_id)->with('success', 'Reconciliation requested. Findings are retained for review.');
    }

    public function show(FinanceListRequest $request, ReconciliationRun $run)
    {
        Gate::authorize('view', $run);
        $filters = $request->validated();

        return Inertia::render('Finance/Reconciliation/Show', ['run' => app(ReconciliationView::class)->run($run, $request->user()), 'items' => $this->page($request, $this->items($request, $run->items()->getQuery(), $filters)), 'filters' => $filters, 'basis' => $run->summary['basis'] ?? null, ...app(FinancialFilterOptions::class)->get($request->user())]);
    }

    private function page(Request $request, $query)
    {
        return app(FinanceListFilter::class)->sort($query->with(['ticket', 'payment', 'transaction', 'settlementItem.settlement']), $request->validated(), ['created_at', 'expected_amount', 'actual_amount', 'difference_amount'], 'created_at')->paginate(20)->withQueryString()->through(fn ($i) => app(ReconciliationView::class)->item($i, $request->user()));
    }

    public function exception(Request $request, ReconciliationItem $item)
    {
        Gate::authorize('view', $item);

        return Inertia::render('Finance/Reconciliation/Exception', ['item' => app(ReconciliationView::class)->item($item, $request->user()), 'run' => $item->run->only(['public_id', 'reconciliation_reference']), 'evidence' => $item->run->summary['evidence'][$item->id] ?? [], 'resolver' => $item->resolver?->name, 'can_resolve' => $request->user()->can('resolve', $item), 'idempotency_key' => bin2hex(random_bytes(32))]);
    }

    public function resolve(ResolveReconciliationRequest $request, ReconciliationItem $item, ResolveReconciliationExceptionAction $action)
    {
        $action->execute($request->user(), $item, $request->validated());

        return back()->with('success', 'Review recorded. Original financial records and finding amounts are retained.');
    }
}
