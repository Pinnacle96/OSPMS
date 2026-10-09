# Milestone 15 validation — Reports & Exports

Date: 2026-10-09 (Africa/Lagos)
Scope: Milestone 15 only, SCR-111–113. Milestones 0–14 remain integrated. Milestone 16 is not started.

## Delivered

Central Reports/Index, Reports/Show and Reports/Exports screens implement all eleven approved report types. Shared report classes/contracts, catalog, filters, scoped queries, exact summaries, paginated preview, bounded/searchable choices, CSV/XLSX/PDF writers, generation action and durable database job live in the Reporting domain. General activity events record export request/completion/failure/retry/download. No report-export migration or business table/column was added; existing idempotency_keys, media_attachments and jobs are reused. See ADR-018.

Revenue uses exact NGN credits/debits/net by occurrence date and original ticket geography. Transactions lists payment attempts by initiated date; attempted amounts include unsuccessful attempts and are clearly distinguished from received revenue. Reconciliation reports each completed run snapshot separately. Registry uses record creation, incidents occurrence and complaints submission. Current underlying read/export permissions and domain geography/ownership apply to rows, summaries and choices. Contacts, document identifiers, descriptions, notes, evidence, internal keys and filesystem paths are excluded.

## Executed automated checks

| Check | Outcome |
|---|---|
| MySQL full suite: `php artisan test --log-junit=.qa/m15-final-mysql.xml` | 310 passed; 5305 assertions; no failures/errors/skips |
| Final MySQL reporting suite: `php artisan test tests/Feature/Reporting/ReportsExportsTest.php` | 27 passed; 396 assertions, including database job insertion with after_commit configured true |
| SQLite full suite: `php artisan test --log-junit=.qa/m15-final-sqlite.xml` | 307 passed; 5293 assertions; 3 existing MySQL-only precision skips; no failures/errors |
| `php -l` across app/bootstrap/config/database/routes/tests | 413 PHP files pass; final changed controller/job/service syntax checked again |
| `composer lint` | Pint passes |
| `npm run typecheck`, `npm run lint`, `npm run build` | TypeScript, ESLint and production build pass |
| `npm run test:field` | All 6 existing scanner tests pass |
| `npm audit --audit-level=low`, `composer audit --no-interaction` | No vulnerability advisories |
| `php artisan migrate:status` | Retained main database migrations all applied; no new migration required |
| `composer validate --strict --no-interaction` | Not a strict clean pass: existing unspecified repository license and intentional exact OpenSpout version constraint produce warnings |
| `git diff --check` and staged artifact scan | Clean patch; no staged runtime paths, application/private keys or GitHub tokens |

Suites ran against disposable MySQL 8.4.11 ospm_test on 127.0.0.1:3308 and isolated SQLite :memory:. They never targeted the retained ospm database. The final MySQL reporting pass preceded the SQLite suite, avoiding shared fake-storage collisions. XDEBUG_MODE=off. Initial query ambiguity/private-cache/test-fixture failures were corrected before these final passes.

Twenty-seven new feature tests cover all eleven report zero states/screens; ledger grouping/summary reconciliation; failed/pending payment semantics; Lagos midnight/month boundaries; completed reconciliation snapshots; minimal registry/operational fields; scoped rows/choice lists/totals; own-operator isolation; historical geography after park moves/archival; all supported financial filters; same-assignment registry filters; malformed/prohibited inputs and ranges; underlying-domain/read-only roles; bounded previews/choices with selected-value retention; exact CSV and duplicate-request audit/file behavior; CSV formula protection; XLSX explicit string cells/no formulas/exact money; escaped local-only PDF; real database job records; permission revocation before worker; owner-only downloads; pivot/role revocation; covered-record geography changes; file tampering/missing files; actor/type/format-bound keys; failure cleanup and retry deduplication; source/PDF limits; queue insertion failure rollback.

## Browser and exported-file validation

Executed `node .qa/m15-browser.cjs` against the running retained demo with headless Chrome/Playwright and axe-core. All eleven viewer variants plus Reports center/Saved exports render at 1440×1000 and 390×844. Thirty-four WCAG 2/2.1 A/AA samples have zero violations, no document-level horizontal overflow and no JavaScript runtime errors. Screenshots of the center, mobile transaction viewer and mobile saved files were visually inspected. This is sampling, not full accessibility certification or physical-device testing.

Browser workflows verified invalid-date feedback; CSV, XLSX and PDF generation/download; private/no-store/nosniff attachment headers; an interrupted committed POST followed by same-form retry; one canonical lost-response request; view-only Executive export controls; and another user's denied download. A small explicit-background complaint export moved from Queued/processing to Ready after executing `php artisan queue:work database --queue=reports --once --tries=1 --timeout=60`, with automatic page polling and a protected download.

Downloaded revenue CSV and XLSX files were parsed using Python csv/OpenPyXL: identical record headers/rows, one aggregate row for 43 source entries, exact amounts, Records/Summary sheets and zero formula cells. PDFplumber verified report/provider text and pagination. The PDF skill's read-only workflow used [Poppler Windows binaries](https://github.com/oschwartz10612/poppler-windows) to rasterize the revenue PDF, the three-page/35-attempt transactions PDF and the nine-column/10-record vehicles PDF. Rendered pages were inspected for wrapping, repeated headers, page counts, margins and clipping. Tools/downloads/screenshots stay under ignored .qa and are not application dependencies/assets.

The retained revenue export independently matches SQL source totals: 43 original NGN ledger entries, gross 15500.00, debits 3502.50 and net 11997.50. Eight private generated files match their media metadata/hashes, and no reports jobs remain pending. Existing 133 financial audit hashes, nine private evidence files, M13 closed-incident/resolved-violation history and M14 closed-complaint/authored-note/read-notification history remain unchanged. Executed .qa/m15-retention.php, .qa/m13-retention.php and .qa/m14-retention.php.

## Real concurrency checks

Executed .qa/m15-race.py against the retained demo with two independent PHP request processes sharing one new idempotency key, and two independent generation processes sharing one queued export. Both races retain exactly one completed request, one referenced private file and one completion audit. The leftover durable job safely executes as a no-op after direct competing generators complete. No financial source or history was altered.

## Deployment requirements and limits

Run a database worker listening to reports, including in demo mode. Request/job insertion requires the application database connection; job timeout is 60 seconds and retry_after must remain higher (default 90). Configurable bounds are 1,000 inline-source rows, 100,000 source rows per export, 1,000 PDF output rows, 25 preview rows/page and 250 matching filter choices. Large CSV/XLSX stream without truncation. XLSX monetary values are exact decimal text. Failed requests require an explicit authorized retry. Keep private metadata/files backed up together and never publish storage/app/private/report-exports.

Normal failure/replay cleans unreferenced files. Process termination between filesystem output and database commit can leave an orphan; automatic housekeeping/purge is not approved here. Large production volumes, physical mobile devices, actual Excel desktop/Numbers interoperability and hosting cron/process limits remain release checks. The existing repository license warning is unresolved; the package pin warning is intentional to retain the existing PHP baseline. No implementation blocker remains.

## Scope compliance

The four controlling v1.0 documents are unchanged. No additional report type, public sharing, scheduling UI, report designer, financial write, unapproved business schema or Milestone 16 screen was implemented. Technical persistence and report bases are documented in ADR-018. OpenSpout 4.28.5 provides the approved Excel category; its [pinned manifest](https://raw.githubusercontent.com/openspout/openspout/v4.28.5/composer.json) and [official documentation](https://raw.githubusercontent.com/openspout/openspout/v4.28.5/docs/documentation.md) were consulted, along with local installed code.

Next approved milestone: **Milestone 16 — Audit + Settings**.
