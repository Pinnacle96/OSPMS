# Implementation status

Build date: 2026-10-07 (Africa/Lagos)
Current authorization: continue through Milestone 6. Milestones 0–3 were completed and integrated previously. Completion validation is dated 2026-10-08 (Africa/Lagos).

- [x] Milestone 0 — Project Bootstrap
- [x] Milestone 1 — Authentication + RBAC
- [x] Milestone 2 — Core Shell + State Dashboard Skeleton
- [x] Milestone 3 — LGAs, Parks & Routes
- [x] Milestone 4 — Operators
- [x] Milestone 5 — Drivers, Vehicles & Assignments
- [x] Milestone 6 — Revenue Heads & Fee Configuration
- [ ] Milestone 7 — Ticketing + QR
- [ ] Milestone 8 — Demo Payments + Receipts + Ledger
- [ ] Milestone 9 — Revenue Dashboards
- [ ] Milestone 10 — Settlement + Reconciliation
- [ ] Milestone 11 — Refunds & Adjustments
- [ ] Milestone 12 — Enforcement PWA
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

Known issues: no financial workflows or charts with data exist yet. Dashboard zeros and empty areas explicitly disclose this. Recent activity is real and permission-filtered. The Field layout is a foundation only; no field routes, service worker or scanner is implemented.

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

Known issues: ticket snapshots are implemented/tested in Milestone 7 when tickets exist. SQLite extreme decimal affinity differs from MySQL; maximum precision is checked on MySQL 8.

## Later milestones

Completed: none.
In progress: none.
Blocked: Milestones 7–20 have not been authorized; not started.
Tests: no later workflow is claimed as tested.
Known issues: the complete Phase 1 financial and operational presentation journey is not yet available.
