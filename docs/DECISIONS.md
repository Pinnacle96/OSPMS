# Implementation decisions

## ADR-001 — Laravel 12 and client-rendered Inertia 2

Date: 2026-10-07
Status: Implemented
Context: PHP 8.3 is installed; shared hosting compatibility is required.
Decision: Laravel 12 (PHP 8.2+), Inertia 2, React 19, TypeScript, Vite and Tailwind 4. Commit both dependency lockfiles. Use client rendering; no SSR server.
Reason: A supported Laravel release with a broad PHP hosting baseline and compatible Spatie packages. The application serves compiled assets through PHP.
Scope impact: None. This selects versions within the approved stack. Recharts is deferred until real chart data exists in Milestone 9.
References: [Laravel releases](https://laravel.com/docs/12.x/releases), [Spatie compatibility](https://spatie.be/docs/laravel-permission/v6/prerequisites).

## ADR-002 — Scope foreign-key dependencies

Date: 2026-10-07
Status: Implemented
Context: Milestone 1 requires LGA, Park and Operator scope tables. Their foreign keys reference master tables scheduled for later milestones.
Decision: Create the full approved `lgas`, `parks` and `operators` table definitions and persistence-only models as migration dependencies. Do not add registry actions, routes, screens or demo operational records. Follow the approved schema exactly; scope pivots retain real foreign keys.
Reason: The kickoff permits migration ordering scaffolding. This makes scope persistence and isolation testable without dangling IDs or a temporary access schema.
Scope impact: No later milestone workflow is implemented. LGA/Park/Operator features remain incomplete. Operator-to-park inheritance is deferred until the approved relationship table exists; current operator access fails closed to explicit assignments or statewide access.

## ADR-003 — Explicit statewide permission and least-privilege grants

Date: 2026-10-07
Status: Implemented
Context: Scope §8 requires granular permissions; Architecture §§30–31 requires scope plus permission. Documents give permission examples and a navigation matrix rather than an exhaustive permission manifest.
Decision: Seed all 13 exact role names. Use `access_statewide` for geographic breadth, separate from action permissions. Only Super Administrator receives `manage_users` and `manage_roles` by default. Role assignment requires `manage_roles` even when `manage_users` is granted. Scoped roles without State Dashboard permission land on My Access.
Reason: Avoid scattered role checks, cross-scope leakage and implicit privilege escalation. Auditor and executive defaults contain read permissions only. No collector receives financial approval permissions.
Scope impact: None. The matrix's unspecified “limited” delegated administration is not enabled until its exact permission boundary is defined. Seeded future-domain permissions do not expose future routes or workflows. Later milestones must refine their permission coverage against the controlling documents.

## ADR-004 — UTC storage and Lagos display

Date: 2026-10-07
Status: Implemented
Context: Database §2.5 requires UTC; initial presentation timezone is Africa/Lagos.
Decision: Keep Laravel's application timezone UTC. Expose the configured display timezone separately. Convert dashboard date boundaries to UTC in Laravel. Show unavailable financial metrics as backend zero states and chart areas as labeled empty states.
Reason: Consistent storage and honest dashboard presentation without fabricated financial data.
Scope impact: None.

## ADR-005 — Configured branding and milestone-limited demo reset

Date: 2026-10-07
Status: Implemented
Context: The kickoff requires replaceable neutral branding and a demo reset command, while later settings/financial workflows remain deferred.
Decision: Use `config/ospm.php` and documented environment keys for primary colours, official logo URL and provider credit. No coat of arms is fabricated. Demo reset restores only implemented identity accounts, roles and scopes, revokes their sessions and preserves audit history. It refuses production and non-demo execution.
Reason: Provide a repeatable foundation demonstration without premature financial implementation or destructive database reset behavior.
Scope impact: The reset command must expand with subsequent milestones; it does not claim to reset financial records today. Branding administration UI remains Milestone 16.

## ADR-006 — Verified isolated MySQL 8 runtime

Date: 2026-10-07
Status: Local environment only
Context: Installed WAMP MySQL is 9.1; its Windows service cannot be started by this session's account. An attempted port-3307 startup collided with pre-existing MariaDB. Earlier checks on that port were therefore MariaDB checks, not MySQL 9 checks. Its original localhost root login was restored and verified after an inadvertent credential change.
Decision: Download the official MySQL 8.4.11 Windows ZIP and run a verified isolated localhost instance on port 3308 with runtime/data under ignored `.qa` and generated credentials in `.env`. Keep `.env.example` targeted at MySQL 8 on port 3306. Explicitly use InnoDB for Laravel MySQL tables.
Reason: Test the approved database major version directly. Verify the running database's `VERSION()` and `@@datadir`, rather than inferring them from an attempted process launch.
Scope impact: None. Application migrations, seeding, tests and browser checks now target MySQL 8.4. No production infrastructure is introduced. Original MariaDB databases were retained; the initial project-created `ospm` and `ospm_test` databases on that instance remain unused.
Reference: [Official MySQL Windows archive documentation](https://dev.mysql.com/doc/refman/8.4/en/windows-choosing-package.html).

## ADR-007 — Milestone 3 geography, permissions and retained history

Date: 2026-10-07
Status: Implemented
Context: Milestone 3 requires LGA/Park/Route CRUD, ParkRoute, audited status changes, scoped queries and local dashboards. Scope describes route geography; the approved schema represents it through parks and does not contain a Route LGA column.
Decision: Keep the schema unchanged and derive route geography through active ParkRoute assignments to accessible parks. Filter shared-route park relationships and counts to the viewer's scope. Add granular management/assignment permissions: State Administrator can manage LGAs/global Routes and register Parks; authorized LGA Administrators can manage scoped Parks and their route assignments. Park Manager retains the foundation's scoped manage_park grant but cannot register Parks, manage global Routes or assign routes by default. Statewide read permission alone never authorizes writes.
Reason: Meet the module navigation matrix and policy-plus-scope architecture without adding unapproved columns or exposing another LGA's park relationships. Assignment permission deliberately permits selection of active global route metadata so an authorized editor can connect a route not yet assigned locally; it does not disclose other park associations.
Decision: Use soft archives and reject archives with linked history, including inactive assignments and archived child Parks. Keep removed ParkRoute rows inactive with their original creation time; no updated_at column is introduced. Active Parks require an active LGA, and active children block LGA deactivation. Preserve the first Park activation timestamp. All writes run in transactions and reauthorize inside Actions; status changes and assignment changes have dedicated audit events.
Reason: Preserve registry history and prevent contradictory operational status. Suspension/deactivation is available when archive is blocked. Later financial relationships must extend archive guards as their modules are introduced.
Decision: Seed labelled synthetic registry records and explicit demo scopes. Repeated seeding/reset respects existing edits and soft archives, and retains route assignment history. Demo reset restores identity and scope baselines; it does not destructively reset registries or financial data.
Scope impact: Milestone 3 only; Operator/Driver/Vehicle, finance, enforcement and notifications remain deferred. Related tabs and unavailable dashboard measures are deliberate empty/zero states. No official park registrations, manager identity column, fabricated revenue or later business routes are introduced.


## ADR-008 — Transport scopes, relationships and private media

Date: 2026-10-07
Status: Implemented
Context: Milestones 4–5 cover SCR-032–047. Driver and Vehicle schemas deliberately have no current park/operator columns; the approved source of those relationships is driver_assignments. Operator records can span multiple parks and operator accounts must stay within their private records.
Decision: Extend the existing scope service using OperatorPark and scoped DriverAssignment relationships. Explicit operator scope exposes only that operator's assignments, not other operators sharing its parks. Filter all related profiles and counts server-side. A registrar can access their own unassigned Driver/Vehicle records until assignment history establishes geographic/operator context. Local writes to shared masters require management access to all retained relationships; statewide read access still needs action permissions.
Reason: Support initial registration without inventing driver/vehicle location columns, while avoiding foreign-scope writes or disclosure. Global master changes affect all associated scopes, so a shared master fails closed for local writers.
Decision: Store OperatorPark/OperatorRoute as approved-schema pivots without updated_at. Removing a relationship marks it inactive; rows, IDs and creation timestamps remain. UI route approvals always carry park context; nullable park_id is retained in the schema for compatibility. Preserve existing park/route links during status maintenance even if the master is inactive, but require active records and current approvals for assignment creation.
Decision: Assignments are created/ended through transactional Actions with policy checks. Lock the driver before checking the single active primary assignment, then vehicle/operator/park and optional route. End an existing primary explicitly; do not silently replace it. Secondary assignments remain supported by the approved is_primary column. Closed assignments remain history. Starts/ends use explicit UTC input.
Decision: Private registry media uses the approved polymorphic metadata table and Laravel's local filesystem abstraction. Validate authorization, category, extension, MIME and 5 MB size; photographs accept JPEG/PNG only. Generate ULID storage filenames; return authorized attachment downloads or image-only previews. Hide paths/hash/storage metadata from page props and audit file access without document contents.
Scope impact: Implements approved Operator/Driver/Vehicle/Assignment and document requirements only. No financial, enforcement, legal adjudication or automatic blacklist inference is added. The shared catalogue controller/Query/UI reuse presentation patterns; domain-specific transactional Actions and Policies own writes.

## ADR-009 — Fee precedence and effective history

Date: 2026-10-07
Status: Implemented
Context: Milestone 6 covers SCR-048–055 and requires backend-only amount resolution, configurable scopes, dates and historical preservation before ticketing. The architecture provides seven recommended precedence levels while the schema also permits route-only combinations.
Decision: Require all non-null constraints to match. Compare park geography before LGA before statewide; within that geography prefer route, then vehicle type. This preserves all seven recommended levels and orders the additional schema-supported combinations. Higher numeric priority breaks equally specific candidates, then later effective_from. Equal specificity, priority and start times are treated as a configuration conflict and return an explicit validation error; do not select an arbitrary amount by database row ID.
Decision: Use inclusive effective_from and exclusive effective_to in UTC; ignore draft/inactive/expired, future and elapsed configurations and inactive/archived revenue heads. Return the matching FeeConfiguration with decimal string amount/currency and its scope fields. Store amounts in DECIMAL(15,2), normalize strings without float, and permit NGN only in this phase.
Decision: Once effective_from is reached, configuration terms cannot be changed and its only new status is inactive. Future configurations can be edited; effective replacements use new rows. Check the locked database record rather than a stale caller instance. Fee actions and general audit preserve actors and approved_by metadata. Finance configuration requires action permissions and statewide access; State Administrator is read-only in this module, consistent with the navigation matrix.
Reason: Deterministic and explainable fee selection, effective-date boundaries and immutable terms prevent historical rates from being silently overwritten. Milestone 7 must independently snapshot amount/currency/code/name on tickets and must not use current fee metadata to recalculate historical amounts.
Scope impact: Revenue configuration only. No ticket, payment, ledger, refund, live collection, assumed revenue allocation or treasury integration is implemented. SQLite's decimal affinity can round very large amounts; maximum DECIMAL precision is validated on MySQL 8, with string normalization tested separately.
