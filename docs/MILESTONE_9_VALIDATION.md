# Milestone 9 validation

Date: 2026-10-08 (Africa/Lagos)
Scope: Revenue Dashboards. Baseline: Milestone 8 commit fcd72e62ac90f2c4d0a094e46482a423ce0900a7 on develop.

## Delivered

SCR-007 State, SCR-008 Executive, SCR-009 Revenue, SCR-010 LGA and SCR-011 Park dashboards now display committed financial data with permission and original ticket scope checks. The executive landing and both new routes are permission controlled. Existing local operational counts remain present.

Financial data includes gross credits, debits, net revenue, entry counts, today/month-to-date measures, daily net trend, revenue by LGA/park/revenue head, payment status/channel counts and six recent transactions. Server date/dimension filters and authorized detail links use the same boundaries. Reconciliation and exceptions remain unavailable until Milestone 10. There is no settlement claim, financial write, export or schema addition.

## Automated validation

Executed against isolated SQLite :memory: and verified MySQL 8.4.11/InnoDB on localhost port 3308, with disposable ospm_test. Main demo data and financial history were retained.

```powershell
$env:XDEBUG_MODE='off'
php artisan test --log-junit .qa/m9-sqlite.xml
$env:DB_CONNECTION='mysql'
$env:DB_DATABASE='ospm_test'
php artisan test --log-junit .qa/m9-mysql.xml
Remove-Item Env:\DB_CONNECTION
Remove-Item Env:\DB_DATABASE
composer lint
npm.cmd run typecheck
npm.cmd run lint
npm.cmd run build
composer audit
npm.cmd audit --omit=dev
composer validate --strict
php artisan migrate --pretend
php artisan route:list --path=dashboard --except-vendor
```

The fifteen new reporting tests cover:

- Every revenue grouping and trend adds to selected net revenue, with exact decimal strings.
- Failed/pending payment attempts never contribute ledger revenue; payment status and channel counts remain distinct.
- Reversal retains the original credit in its original period and creates a negative debit-only period.
- Lagos midnight is inclusive at start/exclusive at end, including matching payment/ledger detail results.
- Configured timezone transition dates bucket correctly without MySQL timezone tables.
- Geography, revenue head, channel, original ticket LGA and archive/move history remain scoped.
- Local identity cannot be overridden by a query string; foreign filters cannot expand authorized data.
- Park summaries do not grant ledger access; non-financial park viewers get no financial props.
- Dashboard permission alone does not grant revenue access; an empty scope never implies statewide access.
- Dashboard route grants, read-only executive surface and executive landing work as intended.
- A custom LGA viewer does not need an unrelated park permission to open its LGA dashboard.
- Default/end-only dates, reversed ranges and the 366-day limit validate consistently.
- Financial GETs retain all payment/receipt/ledger/audit/idempotency counts; recent transactions are capped at six.
- MySQL totals remain exact beyond the capacity of a single DECIMAL(15,2) record.

SQLite deliberately skips the MySQL maximum-precision check because its DECIMAL numeric affinity is not the production money store. Existing foundation, registry, fee, ticket and payment suites remain included. Final suites passed: SQLite 130 tests / 2,685 assertions with one MySQL-only skip; MySQL 131 tests / 2,688 assertions, no skips.

Pint, TypeScript, ESLint and production Vite build passed. PHP syntax passed for 253 source/test/entry-point files. Composer and production npm audits found zero advisories. Composer strict validation retains the existing unspecified-license metadata warning. The main migration sanity check reported nothing to migrate, and route inspection confirmed all five dashboard endpoints.

## Browser and visual checks

Chrome/Playwright checked all five dashboards at 1440 × 1000 and 390 × 844. Backend decimal totals matched the visible cards and permission-aware record links. The actual collector workflow created a new simulated payment; its revenue appeared immediately on refresh. A reasoned finance reversal retained the original gross credit, increased debits and restored the prior net revenue. These synthetic transactions remain in history.

Checked selected-period ledger drilldown, channel-filtered empty states, filter reset, restricted Help Desk financial visibility, collector URL denial, Executive landing and absence of write controls on dashboards. Chart focus/arrow-key navigation showed the tooltip. Expanded exact-figure tables remain keyboard scrollable.

Fourteen workflow axe samples using WCAG 2/2.1 A/AA tags returned zero violations; five mobile pages had no document horizontal overflow. Two further desktop/mobile samples against the final compiled assets also passed after compact chart-axis formatting was added. Browser JavaScript errors: zero. Desktop Revenue/State and mobile Park screenshots were visually inspected for cards, chart labels, amounts, tables and layout, including the final Park viewport at native resolution. These automated samples do not certify complete accessibility or high-volume production performance.

One accessibility defect found during QA was fixed by giving scrollable exact-figure tables a labelled keyboard-focusable region. Test fixture required-field omissions, empty-state selectors and reset-navigation waiting were corrected in tests/ignored helpers. A route-name authorization mismatch found in final review was corrected and is covered by a custom-permission regression test.

## Scope and limitations

All four controlling v1.0 documents are unchanged. No Milestone 10 workflow started. Financial calculations run in Laravel/database queries, with exact MySQL sums and decimal-string arithmetic. Client numeric conversion is restricted to chart coordinates. Distribution plots show ten groups at most; exact tables include all groups. Dashboard summary and detail requests made at different times can reflect subsequent committed transactions; there is no stale cache or claim of a shared cross-request snapshot.

Existing limitations remain: SMTP delivery, object storage, unspecified license metadata and ticket expiry policy. Pending reconciliation is unavailable, not a fabricated zero. Demo records represent no real funds. No sensitive .env, runtime, credentials or QA artifacts are committed.

## Final results

- SQLite: 130 passed, one MySQL precision test skipped, 2,685 assertions (94.28 seconds).
- MySQL 8.4.11: 131 passed, no skips, 2,688 assertions (117.40 seconds).
- Production build, TypeScript, ESLint, Pint and PHP syntax passed; Composer/npm production audits found no vulnerabilities. Strict Composer validation has only the existing license warning.
- No pending migrations or schema changes; all five dashboard routes are registered.
- Live demo payment/reversal, exact detail links, keyboard charts, 16 accessibility samples and five mobile overflow checks passed.
- Four controlling v1.0 documents unchanged; staged-file credential/runtime checks passed.
- No Milestone 10 implementation started.
