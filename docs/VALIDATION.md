# Foundation validation report

Historical acceptance record for Milestones 0–2. Current implementation and validation continue through [Milestone 3](MILESTONE_3_VALIDATION.md); statements about unimplemented registry workflows below describe the earlier foundation.

Date: 2026-10-07 (Africa/Lagos). Authorized scope: Milestones 0–2 only.

## Verified environment

- PHP 8.3.26, Composer 2.8.11, Node 22.23.2.
- Laravel 12.69.3, Inertia Laravel 2.0.28, Spatie Permission 6.25, Activitylog 4.12.3; exact dependencies are locked.
- Final database validation uses **MySQL 8.4.11 on 127.0.0.1:3308**. A direct PDO query of `VERSION()`, `@@datadir` and `@@port` confirmed the runtime and its isolated data directory: `C:\wamp64\www\OSPMS\.qa\mysql8-data\`.
- Local application: <http://127.0.0.1:8000/login>. The development server was restarted after the database switch; the final browser checks use this MySQL 8 configuration.
- Generated credentials remain in ignored `.env`. No password, application key or reusable authentication token is included in this report.

## Executed checks

| Check | Outcome |
|---|---|
| `php -l` across application, bootstrap (excluding generated cache), configuration, database, routes, tests, public entry point and artisan | Passed: 95 files |
| `php artisan test` with SQLite `:memory:` | Passed: 34 tests, 289 assertions |
| `php artisan test` with `DB_CONNECTION=mysql`, `DB_DATABASE=ospm_test` | Passed on MySQL 8.4.11: 34 tests, 289 assertions |
| `composer lint` | Passed (Laravel Pint) |
| `npm.cmd ci` | Passed |
| `npm.cmd run typecheck` | Passed |
| `npm.cmd run lint` | Passed (ESLint) |
| `npm.cmd run build` | Passed; production assets generated in `public/build` |
| `php artisan migrate`, `php artisan db:seed` | Passed on MySQL 8.4.11 |
| `php artisan migrate:rollback`, then migration and seeding | Passed: all 14 migrations round-tripped on the isolated local database |
| `php artisan migrate:status` | All 14 migrations ran |
| `php artisan ospm:demo-reset` | Passed; identity baseline restored |
| `php artisan queue:work --stop-when-empty --max-time=5 --tries=1` | Exited successfully; no business jobs are registered |
| `php artisan schedule:list` | No scheduled tasks; expected for the implemented foundation |
| `php artisan route:list --except-vendor` | 27 application routes; no later operational/financial module routes |
| `composer validate --strict` | Schema valid; exit 1 because license metadata is unspecified. Legal policy was not invented |
| Dependency audit during Composer/npm dependency installation | No known vulnerabilities reported at execution time; not a security certification |
| SHA-256 comparisons of four canonical controlling documents and supplied originals | Identical; controlling documents unchanged |
| Git ignore and source credential-pattern checks | `.env`, dependencies and local QA/database artifacts ignored; no generated credential matches in application source |

The MySQL test database is separate from the local demo database. Tests use database refresh and must never target retained operational data. Deployment configuration must be cleared before running test overrides.

## Functional coverage

The feature suite covers email and username login, rejection/rate limiting of incorrect credentials, inactive/suspended accounts, logout audit, password recovery and reset, required password changes, profile updates, session revocation, audit secret exclusions, role grants, user administration, server search/filter/sort/pagination, scope persistence and cross-scope isolation, separate geographic/action permissions, dashboard authorization and date filtering, friendly unknown-route errors, and demo reset safeguards. It also verifies that later modules have no working routes.

Implemented screen coverage: SCR-001–006, SCR-007, SCR-012–019; shared error foundations SCR-125–129. Scope foreign-key dependencies are persistence scaffolding only; later LGA/Park/Operator workflows are not claimed as complete.

## Browser and visual verification

Executed `node .qa/browser-smoke.cjs` using an isolated headless Chrome instance. Passed:

- Real login/logout and all nine dashboard cards.
- Thirteen synthetic demo users, server search/empty state/clear, and server-side create-user validation without creating an extra account.
- Role-permission confirmation dialog and cancellation.
- Mobile navigation, account page and horizontal-overflow assertions at 390 × 844.
- Park Manager access landing, direct unauthorized administration returning the friendly 403 page, and friendly 404.
- No browser JavaScript runtime errors during the smoke journey.

Desktop screenshots at 1440 × 1000 and mobile screenshots at 390 × 844 were visually inspected. Local screenshots and harnesses are under ignored `.qa`; they are not public application assets.

Executed `node .qa/accessibility.cjs` with axe-core WCAG 2 A/AA and 2.1 A/AA checks. Zero violations on login desktop, dashboard desktop, users desktop and dashboard mobile. This is automated sampling, not full accessibility certification or comprehensive keyboard/assistive-technology testing.

## Environment correction and limitations

An initial attempt to run installed WAMP MySQL on port 3307 collided with an existing MariaDB 11.5.2 instance. Earlier port-3307 results were MariaDB results, not MySQL 9 results. During setup its localhost root password was inadvertently changed; the original blank local login setting was restored and verified. Other existing databases were retained. Project-created `ospm` and `ospm_test` databases on that MariaDB instance remain unused. Final acceptance checks above use the isolated official MySQL 8.4.11 archive runtime on port 3308.

Real password-reset email delivery was not exercised: the local `array` mailer does not deliver mail. Broker/token behavior is covered by tests; configure SMTP to verify delivery. Operational entities are not seeded, so scope assignment selectors are intentionally empty until their registry milestones. No payment, ledger, enforcement, notification-delivery or complete Phase 1 presentation journey is implemented or tested.

Automatic approval review rejected deletion of the temporary `.bootstrap-laravel` scaffold directory with “blocked by policy.” The ignored directory remains outside the application's public web root. It does not affect application execution.

No functional blocker remains for the authorized foundation. The next approved implementation task is Milestone 3: LGAs, Parks & Routes.
