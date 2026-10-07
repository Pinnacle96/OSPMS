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
