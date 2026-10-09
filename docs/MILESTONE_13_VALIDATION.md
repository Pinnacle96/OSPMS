# Milestone 13 validation — Incidents & Violations

Validated on 2026-10-09 in `C:/wamp64/www/OSPMS`. PHP 8.3.26, Laravel 12, MySQL 8.4.11 on port 3308, Node 22.23.2 and desktop Chrome with mobile viewport emulation. Main retained demo database: `ospm`; isolated destructive PHPUnit/schema checks: `ospm_test` and in-memory SQLite. No main database refresh or financial reset was performed.

## Result

Milestone 13 implements SCR-095–103. The Incident and Violation tables match the approved columns; existing inspections and media attachments are reused. Incidents, violations and original relationships are retained, with scoped private evidence, supervised review/status/resolution and paginated reasoned history. The four controlling v1.0 documents are unchanged. Implementation decisions are documented in ADR-016; Milestone 14 is not started.

## Automated checks

| Check | Executed result |
|---|---|
| Full MySQL suite | 248 passed, 4469 assertions; 393.15 seconds |
| Full SQLite suite | 245 passed, 4457 assertions; 3 existing MySQL precision skips; 252.52 seconds |
| New milestone coverage | 32 IncidentsViolationsTest cases plus 1 historical-ticket inspection administration case |
| Browser scanner regression | 6 Node tests passed |
| PHP syntax | 359 checked files; no syntax errors |
| Pint | Full application/bootstrap/config/database/routes/tests check passed |
| TypeScript / ESLint / production assets | Passed; final build completed |
| Composer validation | Valid; existing unspecified-license warning remains |
| Composer / npm advisories | No vulnerability advisories found / 0 vulnerabilities |

New tests cover all nine screen components/private encrypted history, server-derived reporter/relationships/reference, park-only and related-context reports, scoped and mismatched/inactive/ended assignment rejection, meaningful input and future local dates, actor/payload/file-bound confirmations, scope revocation, role exclusions/read-only viewers/manage pivots, shared-park own-operator isolation, every incident transition and reason, immutable resolution on closure, illegal/stale/final transition rejection, canonical replay, derived violation context, clear-inspection rejection, supervised final violation resolution, private downloads and exact attachment-parent binding, evidence replay/terminal/read-only guards, script/MIME-spoof/size rejection, PDF and inspection/violation evidence, transactional audit failure and file cleanup, scoped literal search/date/order/pagination/privacy, archived park history/current incident geography, exact schema/FKs, absence of financial writes, immutable observations, retained seed/reset decisions/audits/financial hashes, auth/password/inactive guards, actor rate limits and Lagos midnight boundaries with UTC storage. Ticket-linked inspections remain in original ticket LGA after a park moves.

## MySQL schema and concurrency

The milestone migration rolled back and remigrated only on `ospm_test`. Exact column order/sets passed for incidents (17 columns) and violations (18 columns), with 13 RESTRICT foreign keys and four unique public/reference identifiers. Only the approved two tables were added.

Three real two-process MySQL scenarios used retained synthetic records through normal actions:

1. The same incident confirmation with evidence returned one canonical incident and one report audit; one file/metadata attachment survived.
2. Two supervisors' requests with distinct confirmations and the same expected incident status produced one change and one stale rejection, with one status audit.
3. Repeated concurrent violation resolution returned one canonical resolved record and one resolution audit.

## Browser and accessibility

All nine required screens returned authorized 200 responses and passed desktop (1440 × 1000) and mobile (390 × 844) checks. Nine mobile pages had no horizontal overflow. Final axe WCAG 2 A/AA and 2.1 A/AA checks: 24 samples, zero violations; JavaScript page errors: zero. Final screenshots were inspected; evidence/history panels and responsive form/header spacing were corrected before final checks.

The full mutation workflow verified field incident reporting with a current scoped assignment and private PNG, a deliberately lost committed success response and retry using the same confirmation, byte-identical authenticated attachment download, additional private PDF upload, violation recording from an inspection, supervisor review/escalation/resolution/closure with all reasons retained, final violation resolution and desktop park-only reporting. The lost-response report retained one report audit and two attachments, and closure kept the final resolution. The network-loss harness reconstructs the submitted multipart fields/file when forwarding the intercepted request; ordinary browser upload was also independently checked.

Final read/confirmation checks verified rear-camera capture attributes, changing from upload selection to capture selection and removing the chosen file, all four confirmation types, a closed incident in the field layout, disabled offline submission and anonymous offline fallback with no reporter/form data. Service-worker cache contents remained exactly the five anonymous shell/manifest/icon assets. No incident, evidence, authenticated HTML or mutation was cached or queued.

## Retention and scope

Six retained evidence files each matched their metadata SHA-256 with no unreferenced evidence file remaining after the executed ordinary retries/rollbacks. All 133 existing financial audit hashes remained valid and the count unchanged after milestone seeds, browser workflows and concurrency checks. M13 actions create no payments, ledger entries, refunds, adjustments or financial audit events. Repeated demo seeds/reset preserve existing operational review decisions and financial history; no deletion/reset path for private evidence was introduced.

All writes require the active-account/password guards, action permission, fresh scope and actor limits. Reads/downloads authorize the current parent scope; guessed or cross-parent attachment identifiers fail. Own-operator incident reads do not expose other incidents at a shared park. Manage pivots are required for scoped supervisor transitions. Private page DTOs exclude registry contacts, identity/licence documents, internal relationship IDs, storage paths and file hashes.

## Limits and release follow-up

Physical Android/iOS photo capture and deployed HTTPS are not claimed as tested. Browser validation used desktop Chrome mobile emulation, synthetic file selection and the HTML capture hint. The 5 MB application validator passed size-limit tests; current CLI PHP limits an actual upload to 2 MB unless configured. Set host `upload_max_filesize` to at least 5M and `post_max_size` above it; README documents this prerequisite. Existing object-storage deployment and SMTP limitations remain.

Private evidence uses the local disk. Ordinary exception/replay cleanup is tested, but a process crash between file storage and transaction completion can leave an orphan; operational cleanup/backups remain release work. Incident and unticketed-inspection LGA scope follows the current park because the approved schema has no historical LGA column; ticket inspections/violations retain original ticket geography. Closed/resolved records have no reopen/delete workflow. No statutory offence/penalty, guilt, blacklisting, complaint, notification, export or later milestone is implemented.

References used while checking the browser validation harness: [Playwright Route API](https://playwright.dev/docs/api/class-route) and [Request API](https://playwright.dev/docs/api/class-request). Local Laravel source and [Laravel Date validation API](https://api.laravel.com/docs/12.x/Illuminate/Validation/Rules/Date.html) were consulted for explicit local time validation.

## Files created or changed

This milestone changes 62 tracked source/document files. Runtime tools, private uploads, `.env`, installed dependencies and built assets are excluded.

- `README.md`
- `app/Console/Commands/DemoResetCommand.php`
- `app/Domains/Enforcement/Actions/RecordViolationAction.php`
- `app/Domains/Enforcement/Actions/ResolveViolationAction.php`
- `app/Domains/Enforcement/Enums/ViolationStatus.php`
- `app/Domains/Enforcement/Models/Inspection.php`
- `app/Domains/Enforcement/Models/Violation.php`
- `app/Domains/Enforcement/Policies/InspectionPolicy.php`
- `app/Domains/Enforcement/Policies/ViolationPolicy.php`
- `app/Domains/Incidents/Actions/CreateIncidentAction.php`
- `app/Domains/Incidents/Actions/ManageIncidentAction.php`
- `app/Domains/Incidents/Enums/IncidentStatus.php`
- `app/Domains/Incidents/Models/Incident.php`
- `app/Domains/Incidents/Policies/IncidentPolicy.php`
- `app/Domains/Incidents/Services/EvidenceService.php`
- `app/Domains/Incidents/Services/OperationalRecordView.php`
- `app/Domains/Incidents/Services/OperationalScope.php`
- `app/Http/Controllers/Web/EvidenceController.php`
- `app/Http/Controllers/Web/IncidentController.php`
- `app/Http/Controllers/Web/InspectionController.php`
- `app/Http/Controllers/Web/ViolationController.php`
- `app/Http/Middleware/HandleInertiaRequests.php`
- `app/Http/Requests/Enforcement/RecordViolationRequest.php`
- `app/Http/Requests/Enforcement/ResolveViolationRequest.php`
- `app/Http/Requests/Incidents/CreateIncidentRequest.php`
- `app/Http/Requests/Incidents/ManageIncidentRequest.php`
- `app/Http/Requests/Incidents/UploadEvidenceRequest.php`
- `app/Providers/AppServiceProvider.php`
- `database/migrations/2026_10_09_000000_create_incidents_and_violations_tables.php`
- `database/seeders/DatabaseSeeder.php`
- `database/seeders/IncidentDemoSeeder.php`
- `database/seeders/RolePermissionSeeder.php`
- `docs/DECISIONS.md`
- `docs/DECISIONS_REQUIRED.md`
- `docs/IMPLEMENTATION_STATUS.md`
- `docs/MILESTONE_13_VALIDATION.md`
- `resources/css/app.css`
- `resources/js/Components/Data/StatusBadge.tsx`
- `resources/js/Components/Incidents/EvidencePanel.tsx`
- `resources/js/Components/Incidents/IncidentForm.tsx`
- `resources/js/Components/Incidents/OperationalLayout.tsx`
- `resources/js/Components/Incidents/RecordDetail.tsx`
- `resources/js/Components/Incidents/RecordList.tsx`
- `resources/js/Components/Incidents/ReviewForm.tsx`
- `resources/js/Layouts/AppLayout.tsx`
- `resources/js/Layouts/FieldLayout.tsx`
- `resources/js/Pages/Field/Home.tsx`
- `resources/js/Pages/Field/Incidents/Create.tsx`
- `resources/js/Pages/Incidents/Create.tsx`
- `resources/js/Pages/Incidents/Index.tsx`
- `resources/js/Pages/Incidents/Manage.tsx`
- `resources/js/Pages/Incidents/Show.tsx`
- `resources/js/Pages/Inspections/Index.tsx`
- `resources/js/Pages/Inspections/Show.tsx`
- `resources/js/Pages/Violations/Index.tsx`
- `resources/js/Pages/Violations/Show.tsx`
- `resources/js/types/incidents.ts`
- `routes/incidents.php`
- `routes/web.php`
- `tests/Feature/DashboardTest.php`
- `tests/Feature/Enforcement/EnforcementPwaTest.php`
- `tests/Feature/Incidents/IncidentsViolationsTest.php`
