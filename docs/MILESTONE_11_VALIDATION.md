# Milestone 11 — Refunds & Adjustments validation

Date: 2026-10-08 (Africa/Lagos)
Scope: Milestone 11, with required existing finance/ticket/receipt integrations. Personal SSH remote: git@github-personal:Pinnacle96/OSPMS.git. No Milestone 12 implementation.

## Implemented

- SCR-079–084: refund request list/detail/create and financial adjustment list/detail/create. Private no-store responses, paginated lists and audit history, reference/status/date filters, approved sorting, shared money/status/feedback components, confirmations, responsive desktop/mobile layouts.
- Exactly the approved refunds and financial_adjustments schema: immutable request terms, ULID route IDs, unique business references, DECIMAL(15,2), eight restrictive FKs, approved workflow fields only.
- Independent permissioned approve/reject flow, including prevention of self-approval by Super Administrator. Finance writes, auditor reads and original historical ticket geography for authorized supervisor requests.
- Approved demo refunds process to successful/failed/processing; failure/pending adds no debit. Retry/resolution revalidates capacity. Successful processing appends one debit linked to the original payment credit. Full cumulative refunds use approved payment statuses; partial/full successful refunds invalidate verification without overwriting original receipts or monetary terms.
- Adjustment approval appends a credit/debit linked to the exact selected original transaction, including corrections with complete financial lineage. Rejection adds audit only.
- Exact decimal strings, ticket/payment locking, shared pending debit reservations, original provider refund cap, actor/operation/payload-bound confirmations and atomic ledger/workflow/general/financial audit writes.
- Reversal protection, dashboard correction totals, corrected-source batch exclusion and fresh reconciliation correction evidence, including corrections to older payments. Prior completed snapshots and provider gross remain retained.
- Synthetic independent partial refund and pending debit adjustment through normal actions; repeated seeding/reset preserves all financial history.

ADR-014 resolves the screen inventory's broad approved-refund statement against the higher-precedence schema: only a successful refund creates a debit. Approval reserves funds. No schema deviation or controlling-document edits occurred. Credit/debit adjustment semantics and verification behavior are documented there.

## Automated checks

PHPUnit used isolated SQLite memory and the separate disposable MySQL 8.4.11 ospm_test database on port 3308. The retained main ospm database was never targeted by the test suite. XDEBUG_MODE=off.

```powershell
php artisan test --compact --log-junit=.qa/m11-final-sqlite.xml
$env:DB_CONNECTION='mysql'
$env:DB_DATABASE='ospm_test'
php artisan test --compact --log-junit=.qa/m11-final-mysql.xml
composer lint
npm run typecheck
npm run lint
npm run build
composer audit
npm audit --omit=dev
composer validate --strict
php artisan migrate --pretend
php artisan route:list --path=finance --except-vendor
```

Full MySQL regression: **189 passed / 3,340 assertions**, no skips (228.62s). Full SQLite regression: **186 passed / 3,328 assertions**, three MySQL-only precision skips (187.63s). PHP syntax passed for 311 files; Pint, TypeScript, ESLint and production build passed. Composer and production npm audits reported zero advisories. Composer strict validation retained only the existing unspecified-license warning.

Final review added a nullable-payment guard to corrected-source settlement exclusion. The full focused MySQL correction suite passed again (29 tests / 253 assertions). The added orphan-import eligibility regression then passed separately on both MySQL and SQLite (1 test / 6 assertions each), confirming unrelated eligible payments remain visible. Pint passed after the final change.

Twenty-nine new tests cover partial/full/refund outcomes; failed retries and pending resolution; independent review and read-only roles; manage/view/geographical scope; shared reservations and provider caps; exact parent linking; idempotency actor/payload/operation binding and revoked access; invalid money/reasons; forged provider amounts; transactional audit failure rollback for both workflows; immutable terms/deletion guards; all six screens and GET immutability; filter/sort validation; demo disablement; old/new reconciliation snapshots; reset retention; complete financial audit hashes; dashboard net totals; corrected batch exclusion; older correction periods and MySQL maximum precision.

Initial regression runs found a stale seed-count expectation and a MySQL JSON-format comparison in the new snapshot test. Expectations now include the retained M11 seed, and the snapshot test compares reread stored JSON on both sides. These were test corrections; no historical financial rows were edited.

## Migration and schema

Applied the new migration to retained local demo data. On ospm_test only, executed:

```powershell
php .qa/m11-schema.php
php artisan migrate:rollback --path=database/migrations/2026_10_08_190000_create_refund_adjustment_tables.php --step=1 --force
php artisan migrate --force
php .qa/m11-schema.php
```

Both schema inspections and the rollback/remigrate passed: two exact column sets, four unique identifiers, DECIMAL(15,2) and eight RESTRICT foreign keys. Main migrate --pretend reports nothing pending.

## Real MySQL concurrency

Ran five two-process races through normal actions, retaining synthetic local financial records:

1. Two distinct full-amount refund requests: one accepted, one rejected for capacity.
2. Same approved refund and processing key: both callers receive the canonical result, exactly one debit.
3. Two approval keys: one decision, competing transition rejected.
4. Full-amount refund versus debit adjustment: exactly one reservation accepted.
5. Full-amount refund versus reversal: exactly one financial operation accepted.

All five passed. The entire existing financial hash chain was verified after each race; a final verification after browser writes passed for 131 retained audit events.

## Browser and presentation

Chrome/Playwright exercised Finance and Super Administrator as separate users. Created and independently approved a partial refund, recorded processing then failed then successful outcomes, verified current receipt/ticket invalidation, requested and approved a linked debit adjustment, and rejected another refund. Confirmations, actor separation and terminal action visibility passed.

All six screens passed desktop (1440px) and mobile (390px) checks. Six mobile pages had no horizontal document overflow. Sixteen final axe WCAG A/AA samples had zero violations; JavaScript errors were zero. Auditor detail was read-only and adjustment creation returned 403. Empty filtered results and source navigation passed. Mobile refund/adjustment detail screenshots were visually inspected. QA scripts/results/screenshots are ignored local artifacts rather than application dependencies.

## Scope and limitations

The four v1.0 documents and their source copies are unchanged. No live refund transfer, treasury/account/allocation policy, invented approval threshold, webhook workflow, enforcement/PWA or later milestone feature was added. Existing financial retention and deployment limitations remain; application model guards and audit hashing do not replace database privilege controls and trusted backups. Composer's existing missing-license warning remains and no license was invented.

Next approved incomplete milestone: Milestone 12 — Enforcement PWA.
