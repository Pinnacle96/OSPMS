# Milestone 10 — Settlement + Reconciliation validation

Date: 2026-10-08 (Africa/Lagos)
Scope: Milestone 10 only, with M9 dashboard and ledger integration. No Milestone 11+ implementation started. User-authorized development continues on the personal-SSH Git remote and is integrated into develop after validation.

## Implemented

- SCR-071–073: settlement list, retained batch/items detail and confirmed demo creation from eligible immutable credits.
- SCR-074–078: scoped reconciliation overview, run history, confirmed run creation, snapshot detail and reasoned exception review.
- Exact approved four-table schema, 11 restrictive foreign keys, required uniqueness/indexes, enums and retained financial models.
- Action/policy/Form Request authorization, backend decimal-string money calculation, actor/operation/payload-bound replay protection and atomic audit writes.
- Matching of obligations, received payments, provider references, credit ledger entries and provider settlement gross, with retained evidence for all failed checks.
- Missing ticket/payment/ledger/settlement, duplicate provider references, amount mismatch, reversal and unknown-source findings. One obligation per ticket; separate orphan-ledger source findings avoid replacing valid obligations.
- Completed snapshot immutability, duplicate-job protection, safe failure state, database queue job and synchronous demo processing.
- Audited under-review/reconciled transitions preserve original finding amounts/type/evidence; no financial adjustment or treasury movement.
- Permission-aware navigation, latest-finding dashboard pending counts and ledger detail trace links.
- Matched, missing-payment and amount-mismatch seed fixtures; repeated seeding/reset retains reviewed findings and existing financial history.
- Server pagination, approved sorting, local-date filters, search/status/type/scope filters, loading/error/empty states, confirmations and responsive presentation.

ADR-013 documents matching definitions, primary-exception priority, scoped batches and snapshot/review semantics. The four controlling v1.0 documents and their source copies remain unchanged.

## Automated checks

Executed with XDEBUG_MODE=off. PHPUnit used isolated SQLite memory or the separate disposable MySQL 8.4.11 ospm_test database on localhost port 3308. No test suite targeted the retained main ospm database.

```powershell
php artisan test --compact --log-junit=.qa/m10-final-sqlite.xml
$env:DB_CONNECTION='mysql'
$env:DB_DATABASE='ospm_test'
php artisan test --compact --log-junit=.qa/m10-final-mysql.xml
composer lint
npm.cmd run typecheck
npm.cmd run lint
npm.cmd run build
composer audit
npm.cmd audit --omit=dev
composer validate --strict
php artisan migrate --pretend
php artisan route:list --path=finance --except-vendor
```

- Final SQLite: **158 passed, two skipped, 3,078 assertions** (129.04 seconds).
- Final MySQL: **160 passed, no skips, 3,087 assertions** (162.22 seconds).
- The 29 new reconciliation tests cover clean matches, retained reruns, missing-payment/mismatch totals, missing ledger versus settlement, global provider-qualified duplicates without foreign-record evidence, reversals after settlement, reason/actor/time/audit retention, replay/changed-payload rejection, scope/privacy, historical park moves, read-only auditors, forged client money, duplicate batches, immutable/deletion guards, rollback, failed jobs, local boundaries, demo/live/production guards, all eight routes, seed/reset retention, orphan ledger grouping, late payments, malformed input, imported orphan payments, incomplete credit lineage and validated dates/sorting.
- Both skipped SQLite cases validate production MySQL DECIMAL precision: the earlier reporting maximum-sum test and the new maximum settlement/reconciliation amount test. MySQL confirms exact totals beyond one record's DECIMAL capacity and rejects batch overflow before persistence.
- PHP syntax: **285** source/test/entry-point files passed `php -l`.
- Pint, TypeScript, ESLint and the final production Vite build passed.
- Composer audit and production npm audit reported **zero advisories**. Strict Composer validation reports only the pre-existing missing-license metadata warning; no license was invented.
- Fourteen finance routes are registered, including all 11 new M10 endpoints. Main database migration sanity reports nothing pending.

## Concurrency, retention and queues

Ignored local two-process MySQL helpers exercised:

1. Same confirmation key / same credit: both responses returned one canonical settlement.
2. Different confirmation keys / same credit: one batch accepted, the competing request rejected; no duplicate settlement item.
3. Settlement versus reversal: the reversal committed and stale batch selection was rejected; original credit/receipt and linked reversal remained retained.
4. Duplicate processing of one reconciliation run: one set of findings and one completion audit event; both workers returned the canonical run.

The complete retained financial audit hash chain verified after each race, including original payment events and new settlement/reconciliation subjects. Repeated job/review requests and business/audit failures are also covered by automated tests.

An actual database queue worker processed a newly retained reconciliation on the isolated `m10-validation` queue:

```powershell
php artisan queue:work --queue=m10-validation --once --tries=3 --timeout=60
```

The worker completed 28 findings and drained that queue. No unrelated queue jobs or source financial records were modified by processing. Demo/browser/race fixtures remain in the main synthetic financial history; resets do not delete them.

## Migration sanity

Applied the new migration to the main demo database without rolling back financial history. On disposable ospm_test only, executed:

```powershell
php artisan migrate:rollback --path=database/migrations/2026_10_08_180000_create_settlement_reconciliation_tables.php --step=1 --force
php artisan migrate --force
php artisan migrate --pretend
```

Rollback/remigration passed, then nothing remained pending. Schema inspection confirmed all four exact column sets, 11 restrictive foreign keys and settlement/source uniqueness. Required model retention guards and snapshot immutability are tested independently.

## Browser and visual checks

Final Chrome/Playwright run against compiled assets completed actual finance creation of a simulated short settlement, confirmed reconciliation, under-review transition and reasoned reconciled outcome. Backend provider gross, zero fees, NGN 0.01 retained difference and final read-only reviewed state matched the UI. Filters returned the expected single finding and an empty result; Auditor/collector write denial and scoped LGA reads passed.

All eight screens passed at 1440 × 1000 and 390 × 844. **22 final WCAG 2/2.1 A/AA axe samples returned zero violations**, with **zero JavaScript errors** and **no document horizontal overflow on eight mobile pages**. Samples include three confirmation dialogs, both screen sizes, empty-filter state, Auditor denial and scoped reconciliation. Overview, run and exception screenshots were visually inspected for amounts, readable evidence, tables and layout.

Final QA found a shared breadcrumb overflow with a longer random reconciliation reference. Wrapping, constrained crumb widths and non-shrinking icons corrected it; the complete browser workflow and all eight mobile screens were rerun successfully after rebuilding. Automated accessibility samples do not certify complete accessibility or production performance.

## Scope and limitations

No controlling-document change, schema expansion, real treasury transfer, live provider settlement ingestion, government allocation/account assumption, refund approval, financial adjustment, report/export, enforcement or other later workflow was introduced. Synthetic fees are zero and government account references remain unset. Async/non-demo runs require the configured database worker; current presentation data is small, and high-volume load testing belongs to Milestone 19.

Run evidence is a retained processing snapshot. A new run reflects current sources; completed runs never rematch. Overview totals use latest findings rather than summing historical reruns. Expected means ticket gross and recorded means settled provider gross, including retained discrepancy after manual review. A manual reconciled outcome does not change amounts or imply a real treasury settlement. Dashboard pending counts apply to selected-period credits; overview filters have their own basis. Finding/run/batch date filters use configured local midnight boundaries.

Existing SMTP, object storage, repository-license and ticket-expiry-policy limitations remain. Audit viewer screens remain Milestone 16. Financial model guards/hash chains supplement database privilege controls and protected backups. No .env, runtime, private keys, credentials or ignored QA artifacts are committed.

Next approved feature milestone: **Milestone 11 — Refunds & Adjustments**.
