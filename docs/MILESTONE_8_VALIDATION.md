# Milestone 8 validation

Date: 2026-10-08 (Africa/Lagos)
Scope: Demo Payments + Receipts + Ledger. No real funds are charged.
Baseline: Milestone 7 on develop, commit 14d62fd323c6999dd6623d0814336d63bd41f8b8.

## Delivered screens and records

| Screen | Implementation |
|---|---|
| SCR-061 | Scoped payments list, search, filters, sorting and pagination |
| SCR-062 | Payment attempt detail, pending resolution and authorized demo reversal |
| SCR-063 | Ticket demo payment with backend amount and explicit confirmation |
| SCR-064 | Protected canonical receipt detail and QR |
| SCR-065 | Protected receipt HTML print |
| SCR-066 | Protected PHP-generated receipt PDF download |
| SCR-067 | Existing ticket verification reflects successful/reversed payments |
| SCR-068 | Safe public receipt verification |
| SCR-069 | Scoped, read-only financial ledger |
| SCR-070 | Ledger entry, source references and reversal parent traceability |

The approved payments, receipts, financial_transactions, financial_audit_logs and idempotency_keys tables are implemented in one ordered migration. Successful collection creates payment state, paid ticket state, one receipt, one credit and audit atomically. Failed/pending simulations create no credit or receipt. Finance-only demo reversal appends a linked debit and preserves the original credit and receipt. Receipt verification reflects current payment state independently of ticket expiry.

## Automated checks

Full suites run independently on SQLite :memory: and verified isolated MySQL 8.4.11/InnoDB (127.0.0.1:3308), with a separate disposable ospm_test database. Never run these tests against the retained demo database.

```powershell
$env:XDEBUG_MODE='off'
php artisan test --log-junit .qa/m8-sqlite.xml
$env:DB_CONNECTION='mysql'
$env:DB_DATABASE='ospm_test'
php artisan test --log-junit .qa/m8-mysql.xml
Remove-Item Env:\DB_CONNECTION
Remove-Item Env:\DB_DATABASE
composer lint
npm.cmd run typecheck
npm.cmd run lint
npm.cmd run build
composer audit
composer validate --strict
```

Both full suites passed: **116 tests / 2,331 assertions each**. The 23 payment feature tests exercise atomicity and forced rollback, gateway amount verification, exact backend amounts, duplicate confirmations, competing state guards, failed retries, pending resolution, immutable financial terms, retained reversal, scope/permission separation, operator isolation, PDF authorization, public privacy/rate limits, production/demo safeguards, filters/pagination, chained audit and seeder retention. Existing identity, registries, fees and ticketing tests remain included.

Pint, TypeScript, ESLint and production asset build passed. PHP syntax passed for 247 source/test/entry-point files. Composer audit found no security advisories. Composer strict validation reports only the existing missing-license metadata warning; no licensing policy was invented.

## Real concurrency checks

Three checks used separate simultaneous PHP processes and the retained synthetic demo database, not simulated sequential requests:

1. Same confirmation key and ticket: both callers receive the same payment; exactly one payment, receipt and credit exist.
2. Different keys and the same ticket: one attempt succeeds, the other fails validation; exactly one payment, receipt and credit exist.
3. Different keys and separate tickets: both succeed; exactly two payments, receipts and credits exist and the complete financial audit chain validates in database order.

All three passed on MySQL 8.4. The helper fixtures, subprocess logs and credentials remain ignored under .qa/.env. No retained financial records were removed to make a race check pass.

## Browser, accessibility and PDF

Chrome/Playwright exercised collector success, failure and pending outcomes; pending resolution on the same attempt; receipt detail/print/download; anonymous receipt/ticket verification; finance search and empty results; a confirmed reversal with reason; invalidated public verification; and original-credit ledger traceability. Collectors did not receive the reversal control. Browser JavaScript errors: zero.

Twenty-five axe samples covering WCAG 2/2.1 A/AA tags reported zero violations. Seven protected pages were also checked at 390 × 844 with no document horizontal overflow; desktop checks used 1440 × 1000. These are automated samples, not a complete accessibility certification.

The receipt QR decoded to its exact public receipt verification URL. The actual authenticated PDF download was parsed with pypdf: one A4 page, expected business references, NGN 500.00 and the demo/no-real-funds label. It was rendered at 2x with pypdfium2 because Poppler was unavailable. The rendered PDF QR also decoded to the receipt verification route. Visual inspection confirmed legible text, complete references, clear status/amount, intact QR and no clipping. Protected desktop/mobile receipt screenshots were also inspected. PDF generation uses Dompdf in PHP with remote resources, embedded PHP and JavaScript disabled.

The first production-guard test hit CSRF before authorization when switching the test environment to production. It was corrected to submit a valid session CSRF token; the actual demo authorization guard then returned 403 and created no payment. Browser selector and PDF assertion wording corrections affected only ignored QA helpers. An end-date-only filter defect found in final review was fixed and is covered by the suite.

## Scope and retained data

The four controlling v1.0 documents are unchanged. No live provider, treasury credentials, dashboard financial aggregation, settlement/reconciliation, refund approvals, adjustments, enforcement scanner/PWA or later reports are added. Controlled demo reversal follows Scope §22 and Screen Inventory §37; it does not implement Milestone 11 refund approvals.

Repeated demo seeding/reset preserves financial history, including a reversed baseline fixture. Receipt and ledger mutations/deletion have no application routes. Application model guards, restrictive foreign keys and audit hashes are tested; deployment still needs database privilege controls and protected backups. SMTP delivery, object storage, unspecified ticket-expiry policy and repository license metadata remain the previously documented limitations.

## Final execution results

- SQLite: 116 tests, 2,331 assertions, zero failures (81.09 seconds).
- MySQL 8.4.11: 116 tests, 2,331 assertions, zero failures (98.26 seconds).
- Main demo migration and seeding passed without removing retained data.
- The Milestone 8 migration rolled back and remigrated successfully in disposable ospm_test only; all five tables were recreated. The retained demo database was not rolled back.
- All three MySQL concurrency checks, 25 browser accessibility samples, seven mobile overflow checks and both browser/PDF QR checks passed.
- Staged changes exclude .env, .qa, vendor, node_modules and compiled runtime assets. Private-key, GitHub-token and application-key pattern scan passed. The four controlling v1.0 documents have no diff.
- No Milestone 9 implementation was started.
