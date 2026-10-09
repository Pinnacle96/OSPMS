# Implementation status

Build date: 2026-10-09 (Africa/Lagos)
Current authorization: Milestone 12 — Enforcement PWA. Milestones 0–11 were completed and integrated previously.

- [x] Milestone 0 — Project Bootstrap
- [x] Milestone 1 — Authentication + RBAC
- [x] Milestone 2 — Core Shell + State Dashboard Skeleton
- [x] Milestone 3 — LGAs, Parks & Routes
- [x] Milestone 4 — Operators
- [x] Milestone 5 — Drivers, Vehicles & Assignments
- [x] Milestone 6 — Revenue Heads & Fee Configuration
- [x] Milestone 7 — Ticketing + QR
- [x] Milestone 8 — Demo Payments + Receipts + Ledger
- [x] Milestone 9 — Revenue Dashboards
- [x] Milestone 10 — Settlement + Reconciliation
- [x] Milestone 11 — Refunds & Adjustments
- [x] Milestone 12 — Enforcement PWA
- [ ] Milestone 13 — Incidents & Violations
- [ ] Milestone 14 — Complaints + Notifications
- [ ] Milestone 15 — Reports & Exports
- [ ] Milestone 16 — Audit + Settings
- [ ] Milestone 17 — Final System Pages + UI Hardening
- [ ] Milestone 18 — Demo Data + Presentation Workflow
- [ ] Milestone 19 — Security, Performance & QA
- [ ] Milestone 20 — Shared Hosting Deployment

## Milestone 0

Completed: Laravel modular monolith, domain skeletons, React/TypeScript/Inertia, Tailwind design tokens, standard framework configuration, MySQL environment example, database queues/cache/sessions, four base layouts, friendly error foundation, dependency locks, Git `develop` branch, test/lint setup and controlling documents.

In progress: none.

Blocked: none.

Tests: all 14 migrations, rollback and remigration/seeding passed on verified MySQL 8.4.11. PHP syntax passed for 95 source/entry-point files; Pint, TypeScript, ESLint and production build passed. Queue worker and scheduler checks passed. See [Validation](VALIDATION.md).

Known issues: SMTP must be configured for real password reset delivery; local default array mailer does not deliver or log reset tokens. R2 uploads, Sanctum API tokens and Recharts are deferred until their owning workflows require them.

## Milestone 1

Completed: SCR-001–006 and SCR-012–019; email/username login, logout, reset tokens, active-user enforcement, required password changes, account profiles, session revocation, 13 roles, permissions, Spatie audit, login history, admin user CRUD without deletion, role editing, permission matrix, scope assignment, scope service, Form Requests, policies and transactional actions.

In progress: none.

Blocked: none for foundation functionality.

Tests: 34 feature tests / 289 assertions passed independently on SQLite and MySQL 8.4.11. Browser login/logout, authorization, validation, scope landing and administration checks passed. Demo reset safeguards and the command itself passed.

Known issues: LGA/Park scope selection now has demo registry records. Operator scope selection and park inheritance are implemented in Milestone 4. General/financial audit viewer screens remain Milestone 16.

## Milestone 2

Completed: SCR-007; App/Auth/Public/Field layouts, sidebar, topbar, real user search, notification placeholder, breadcrumbs, page header, stat cards, tables, sorting, server filters, pagination, status labels, validation messages, loading/error/empty states, native confirmation dialogs, mobile navigation and configurable neutral branding.

In progress: none.

Blocked: none.

Tests: TypeScript, ESLint, production build and browser smoke passed. Screenshots inspected at 1440 × 1000 and 390 × 844; mobile navigation and horizontal overflow checks passed. Automated axe checks reported zero WCAG A/AA violations on login, dashboard and users desktop pages, and the mobile dashboard. Automated checks are not a complete accessibility certification.

Known issues: payment/receipt/ledger workflows exist in Milestone 8 and financial dashboard aggregation/charts in Milestone 9. Recent activity is real and permission/scope-filtered. The Field layout is a foundation only; no field routes, service worker or scanner is implemented.

## Milestone 3

Completed: SCR-020–031, SCR-010 and SCR-011. Approved Route/ParkRoute schema, relationships, LGA/Park/Route create/read/update/archive, activation/suspension, scoped list Queries, server-side search/filter/sort/pagination, transactional policy-enforced Actions, audited changes, retained assignment history, detail tabs, local dashboards, navigation and dashboard scope links. Demo seed data includes three labelled LGAs, parks and routes, with separate LGA/Park scope baselines.

In progress: none.

Blocked: none.

Tests: full identity/registry suite passes on SQLite and MySQL 8.4.11; 19 Milestone 3 tests cover all 14 screens, geographic isolation, shared routes, write authorization, statuses, archive guards, assignment history and idempotent demo seeding. Browser CRUD/status/assignment/scoped-access smoke passed. Fourteen sampled desktop/mobile accessibility audits returned zero violations. See [Milestone 3 validation](MILESTONE_3_VALIDATION.md) for final counts and commands.

Known issues: later Operator/Driver/Vehicle, financial, incident and compliance panels deliberately show unavailable or zero states. Routes derive geography through ParkRoute; authorized assignment editors can select from the active global route catalogue, without receiving other parks' relationships. Archives retain their unique business codes and have no restore UI in this milestone. Demo reset preserves existing registry edits and archives. SMTP and unspecified license metadata remain foundation limitations.

## Milestone 4

Completed: SCR-032–035, OperatorPark/OperatorRoute migrations and relationships, registration, approval/suspension, backend references, scoped lists/details/edits, retained approvals, operator account baseline, private documents and audit. Shared master writes fail closed for local users with unmanaged relationships.

In progress: none.

Blocked: none.

Tests: own-operator and geography isolation, approval permissions, action authorization, retained relationships, inactive-master maintenance, private documents and all four screens. See [Milestones 4–6 validation](MILESTONES_4_6_VALIDATION.md).

Known issues: existing SMTP/object storage limitations remain. Full presentation-scale seed is deferred to Milestone 18.

## Milestone 5

Completed: SCR-036–047, Driver/Vehicle/DriverAssignment/MediaAttachment schema, policies/actions, approval/suspension, expiry fields, registration search/filter/pagination, private photographs/documents, validated assignments, explicit ending/history, one active primary rule, scoped transport tabs and real registry counts.

In progress: none.

Blocked: none.

Tests: relationship validation, primary/secondary assignment rules, history retention, simultaneous MySQL requests, server search/scopes, file validation/access, screenshots, browser workflows and desktop/mobile accessibility samples.

Known issues: expiry fields are recorded; scheduled automatic expiry processing and enforcement remain later milestones. Local unassigned registrations use creator access until assignment history provides scope.

## Milestone 6

Completed: SCR-048–055, RevenueHead/FeeConfiguration schema, policies/actions, finance grants, decimal normalization, future fee editing, immutable effective terms, deactivation, backend fee resolver, specificity/priority/effective-date rules and general audit.

In progress: none.

Blocked: none.

Tests: critical fee-resolution unit tests precede ticketing; cover all recommended specificity levels, dates, statuses, no-match/conflicts, exact money, stale model and history protections. Finance authorization and all eight screens are covered.

Known issues: ticket snapshots are implemented and tested in Milestone 7. SQLite extreme decimal affinity differs from MySQL; maximum precision is checked on MySQL 8.

## Milestone 7

Completed: SCR-056–060 and SCR-067, approved Ticket schema, transactional issuance, backend fee review and revalidation, immutable financial/context snapshots, unique references and secure tokens, QR display/print, supervisor cancellation, immediate expiry evaluation and daily expiry command, scoped lists/tabs/dashboard counts, public safe verification and audit. Repeated reviewed submissions produce one ticket, including simultaneous MySQL requests.

In progress: none.

Blocked: none for this milestone.

Tests: full SQLite and MySQL suites, ticket issuance/cancellation/status/privacy/scope/history tests, migration round trip, QR decoding, responsive Chrome workflows and accessibility samples. See [Milestone 7 validation](MILESTONE_7_VALIDATION.md) for final counts and executed commands.

Known issues: expiry duration is unset until configured from policy. QR links require a reachable APP_URL. Ticketing creates unpaid obligations; Milestone 8 adds demo collection separately. The scanner/PWA belongs to Milestone 12. Existing SMTP and object storage limitations remain.

## Milestone 8

Completed: SCR-061–066 and SCR-068–070, with SCR-067 consuming paid/reversed ticket state. Approved Payment, Receipt, FinancialTransaction, FinancialAuditLog and IdempotencyKey tables; provider contract and demo gateway; successful/failed/pending simulations; atomic ticket/payment/receipt/credit/audit writes; idempotent retries and concurrency guards; controlled finance-only demo reversal with a linked debit; scoped payments and read-only ledger; protected receipt detail/print/PDF; safe public receipt verification; general and chained financial audit; retained synthetic fixtures and reset safeguards. No real funds are charged.

In progress: none.

Blocked: none for this milestone.

Tests: full SQLite and MySQL suites, real two-process MySQL payment/audit races, browser collection/reversal/verification flows, PDF rendering and QR decoding, mobile overflow checks and 25 accessibility samples. See [Milestone 8 validation](MILESTONE_8_VALIDATION.md) for final counts and executed checks.

Known issues: revenue dashboards are implemented in Milestone 9. Settlement/reconciliation is implemented in Milestone 10; refund approval and adjustments remain Milestone 11. Demo writes refuse production. Receipt PDF requires writable framework cache; verification requires reachable APP_URL. Existing SMTP, object storage and unspecified repository license limitations remain. Financial model guards and hash chaining do not replace database privilege controls and protected backups.

## Milestone 9

Completed: SCR-007–011. State, Executive, Revenue, LGA and Park dashboards; exact gross/debit/net ledger totals; today/month-to-date and selected-period counts; daily trends and revenue by LGA/park/revenue head; current payment status and channel distributions; six recent transactions; financial permission redaction; original ticket geography; scoped dimension/date filters; matching Payments/Ledger links; read-only executive landing; Recharts with keyboard support and exact figures; responsive/empty/loading/error states. No financial schema changes or dashboard writes.

In progress: none.

Blocked: none for this milestone.

Tests: MySQL 131 passed / 2,688 assertions; SQLite 130 passed / 2,685 assertions with one MySQL-only precision skip. Coverage includes exact totals and every grouping, failed/pending exclusions, reversals across periods, midnight/DST boundaries, decimal capacity, scoped/permission access, archived/moved park history, matching detail queries and absence of GET mutations. Browser checks cover all five dashboards, a fresh payment and reversal, keyboard charts, five mobile pages and 16 accessibility samples. See [Milestone 9 validation](MILESTONE_9_VALIDATION.md).

Known issues: reconciliation metrics and exceptions are integrated in Milestone 10; ledger totals do not imply treasury settlement. Status charts show the current status of attempts initiated in the period, while revenue uses ledger occurrence dates. Financial summaries are NGN-only. SQLite retains its existing large-value numeric-affinity limitation; MySQL verifies production precision. Existing SMTP/object storage/license/expiry-policy limitations remain.

## Milestone 10

Completed: SCR-071–078; exact Settlement/SettlementItem/ReconciliationRun/ReconciliationItem schema, enums, retained models, policies and Form Requests; server-calculated demo provider batches; transactional matching, source evidence and exception detection; immutable completed snapshots; actor/payload-bound confirmations; queue-capable processing; audited reasoned review/resolution; scoped totals and safe detail links; navigation, ledger trace and real dashboard pending counts; synthetic matched/missing-payment/mismatch fixtures retained across reset. All eight screens include responsive forms/details, pagination and validated filtering/sorting where applicable.

In progress: none.

Blocked: none for this milestone.

Tests: final MySQL 160 passed / 3,087 assertions; SQLite 158 passed / 3,078 assertions with two MySQL-only precision skips. Twenty-nine M10 tests cover matching, source exceptions, review/audit retention, replay, rollback, scope/privacy, permissions, exact amounts, date boundaries, validated sorting and reset retention. Four real MySQL concurrency scenarios and a database queue worker passed. All eight screens passed desktop/mobile smoke; 22 final axe samples had zero violations, eight mobile pages had no overflow and JavaScript errors were zero. PHP syntax (285 files), Pint, TypeScript, ESLint, build and dependency audits passed. The four-table migration rolled back/remigrated on ospm_test; 11 restrictive FKs and exact column sets passed inspection. See [Milestone 10 validation](MILESTONE_10_VALIDATION.md).

Known issues: demo settlement fees are zero and government account references are unset; no real funds, external settlement feed, live treasury integration or invented allocation policy is involved. Non-demo processing requires the configured database worker. Resolving a finding acknowledges a reviewed outcome and retains original discrepancy amounts; it does not adjust funds. General audit viewer screens remain Milestone 16. Existing SMTP/object storage/license/expiry-policy limitations remain. Production volume/load testing is part of the later QA milestone.

## Milestone 11

Completed: SCR-079–084; exact Refund/FinancialAdjustment tables, enums and retained models; independent approval/rejection; scoped supervisor refund requests and finance adjustment requests; partial/full demo processing with pending/failure/retry states; source-linked immutable ledger corrections; decimal balance reservations across refunds/adjustments/reversals; actor/payload-bound confirmations; complete general and chained financial audit; permission-aware navigation, source links and paginated history; correction-aware verification, dashboards, settlement eligibility and fresh reconciliation evidence; retained demo seeds/reset behavior.

In progress: none.

Blocked: none for this milestone.

Tests: MySQL 189 passed / 3,340 assertions; SQLite 186 passed / 3,328 assertions with three MySQL-only precision skips. Twenty-nine new workflow tests, five real MySQL races, six desktop/mobile screens, 16 accessibility samples with zero violations, 311 PHP syntax checks, lint/type/build, migration and dependency checks passed. Details are recorded in [Milestone 11 validation](MILESTONE_11_VALIDATION.md).

Known issues: refund processing is demo-only and refuses production; no real provider refund, treasury allocation rule, bank account or approval threshold is configured. Any successful refund invalidates ticket/receipt verification; original receipt amounts and completed reconciliation snapshots remain historical. Adjustments do not reactivate tickets or transfer provider funds. ADR-014 resolves the schema/screen wording on approval versus successful refund debits. Existing SMTP/object storage/license/expiry-policy limitations remain.

## Milestone 12

Completed: SCR-085–094; mobile enforcement layout, scoped safe driver/vehicle/operator lookups and summaries, camera/manual QR verification through existing services, installable manifest/worker/icons, anonymous offline shell, exact retained inspection schema, server-derived confirmed observations, optional coordinates, permission/geography/history guards, actor limits, atomic audit/idempotency and retained demo/reset behavior. Shared financial confirmation compatibility and full product identity are preserved.

In progress: none.

Blocked: none for implementation.

Tests: MySQL 215 passed / 4038 assertions; SQLite 212 passed / 4026 assertions with 3 existing MySQL-only precision skips. Twenty-six enforcement tests, six scanner-normalization tests, two real MySQL confirmation workers, lost-response retry, ten desktop/mobile screens, 22 accessibility samples with zero violations, camera pipeline/fallback/track cleanup, installability/offline/cache-update/history checks, schema round trip, lint/type/build/syntax and dependency audits passed. See [Milestone 12 validation](MILESTONE_12_VALIDATION.md).

Known issues: physical Android/iOS camera, actual device installation and deployed HTTPS checks remain release validation; the executed camera test used a synthetic media feed in desktop Chrome mobile emulation. Offline support is an anonymous shell only; current verification and saving need a connection. APP_URL must be reachable by the device. No legal clearance, offence/penalty rule or later enforcement administration is inferred. Existing SMTP/object-storage/license/expiry-policy limitations remain.

## Later milestones

Completed: none.
In progress: none.
Blocked: Milestones 13–20 have not been authorized; not started.
Tests: no later workflow is claimed as tested.
Known issues: the complete Phase 1 financial and operational presentation journey is not yet available.
