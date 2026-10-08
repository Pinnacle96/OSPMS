# Milestone 3 validation report

Historical Milestone 3 report. The current implementation and checks are recorded in [Milestones 4–6 validation](MILESTONES_4_6_VALIDATION.md).

Date: 2026-10-07 (Africa/Lagos). Scope: LGAs, Parks & Routes only, preserving Milestones 0–2.

## Result

Milestone 3 is implemented and validated. The next approved feature milestone is Milestone 4 — Operators. No Operator/Driver/Vehicle, ticketing, payment, ledger, reconciliation, enforcement or notification workflow is enabled by this change.

## Executed checks

| Command/check | Result |
|---|---|
| PHP syntax across application, bootstrap excluding generated cache, configuration, database, routes, tests, public entry point and artisan | Passed: 124 files |
| `php artisan test` on SQLite `:memory:` | **53 tests, 883 assertions passed** |
| `php artisan test` with `DB_CONNECTION=mysql`, `DB_DATABASE=ospm_test` | **53 tests, 883 assertions passed** on MySQL 8.4.11 |
| `composer lint` | Passed (Pint); a fresh Pint cache was also used while fixing formatting |
| `npm.cmd run typecheck` | Passed |
| `npm.cmd run lint` | Passed (ESLint) |
| `npm.cmd run build` | Passed; compiled application assets generated |
| `php artisan migrate` and `php artisan db:seed` on local MySQL 8 | Passed; new Route/ParkRoute tables and synthetic registries installed |
| `php artisan migrate:rollback --step=1`, then `php artisan migrate` on disposable `ospm_test` | Passed; the Milestone 3 migration round-tripped without changing the retained demo database |
| `php artisan route:list --except-vendor` | 51 application routes; later module routes remain absent |
| `node .qa/milestone3.cjs` | Browser workflow smoke passed; 14 accessibility samples returned zero violations |
| `node .qa/browser-smoke.cjs` | Foundation browser regression smoke passed; no browser JavaScript errors |
| Controlling-document Git/SHA-256 checks and staged-content checks | Four controlling documents unchanged; generated secrets/private keys and local runtime artifacts excluded |

Final full suites ran after the additional seed regression covering soft archives. An earlier pass had 882 assertions; the final count is 883.

The database runtime remains the verified isolated MySQL 8.4.11 instance at 127.0.0.1:3308 with data under ignored `.qa/mysql8-data`. The MariaDB environment correction and foundation checks are recorded in [the historical foundation report](VALIDATION.md). No production database or external Government service was used.

## Acceptance evidence

The 19 new registry feature tests cover:

- All 14 required screens: SCR-020–031 and scoped dashboards SCR-010/SCR-011.
- LGA/Park/Route creation, details, edits and soft archival; server validation and unique codes.
- Park activation, suspension, reactivation, unchanged first activation time and dedicated audit events.
- Active-LGA activation rule and rejection of LGA deactivation while active Parks remain.
- LGA/park scope isolation for lists, searches, relationship counts, filters, direct URLs and dashboards.
- Shared Route visibility without revealing another LGA's Park records or counts.
- Separate action permissions, statewide geography, view/manage scope levels and authorization inside domain Actions.
- Prevention of unauthorized Park transfers, global Route edits, writes by read-only statewide roles and creation by scoped accounts.
- Route replacement, removal and reactivation preserving assignment IDs/creation times; invalid assignments remain atomic.
- Archive guards retaining linked history, including inactive ParkRoute and archived child Parks.
- Database search/filter/sort/pagination, actual scoped registry dashboard counts and explicit financial zero states.
- Idempotent synthetic seeding that preserves edited and archived records.

The existing identity tests continue to cover authentication, password recovery/reset, active-user enforcement, role grants, audit secret exclusions, session revocation and demo reset safeguards.

## Browser and visual QA

Headless Chrome used the local production build and real MySQL-backed Laravel endpoints at <http://127.0.0.1:8000>. A separate browser instance was used.

The browser smoke created/edited/soft-archived a synthetic QA LGA, Park and Route; activated the QA Park through the confirmation dialog; removed and restored an existing demo Park route assignment; checked server search and empty-state clearing; opened both local dashboards; tested the My Access dashboard link; verified LGA-scoped lists and cross-LGA 403; and verified Auditor write restrictions. Browser-created QA records remain archived audit history; the three active demo registry baselines remain available.

Screenshots were inspected at 1440 × 1000 and 390 × 844. Mobile overflow checks passed for the Park list, create form, profile and dashboard. The refreshed screenshots include the settled assignment state.

Axe-core WCAG 2 A/AA and 2.1 A/AA checks returned **zero violations** on:

- Desktop: LGA, Park and Route lists; their three create forms; Park overview; Park Routes tab; LGA Dashboard; Park Dashboard.
- Mobile: Park list, create form, profile and dashboard.

These are automated samples, not complete accessibility certification. QA harnesses, screenshots and local database binaries remain ignored in `.qa`.

## Implementation boundaries and remaining limitations

Route geography follows the approved ParkRoute relationship; no unapproved Route LGA column was added. Scoped viewers see only accessible Park relationships. Assignment-authorized editors can select active global route metadata, because an unassigned route would otherwise never become selectable locally; other Park relationships remain private.

Archive is deliberately conservative: any linked registry history blocks archival. Inactive status remains available for records with history. There is no archive-restore screen in Milestone 3. Future financial relationships must extend these guards when introduced.

Operator/Driver/Vehicle, financial and incident/compliance tabs remain honest unavailable/zero states. The Operator table remains Milestone 1 FK scaffolding. No official registration dataset, revenue activity, API token workflow, upload integration or chart dataset was fabricated.

Real SMTP delivery remains unconfigured, and Composer's foundation strict validation warning for unspecified license metadata remains unresolved. Neither blocks Milestone 3 runtime functionality. See [ADR-007](DECISIONS.md) and [implementation status](IMPLEMENTATION_STATUS.md).
