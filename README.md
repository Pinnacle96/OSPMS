# Osun State Park Management System

Government-facing park operations and revenue administration platform. Technology Solution by **Pinnacle Tech Hub**.

This repository currently implements **Milestones 0–14**: the Laravel foundation, authentication/RBAC, account and user administration, scoped geography and transport registries, assignments, private documents, revenue configuration, tickets, demo payments, receipts, an immutable ledger, revenue dashboards, demo settlement batches, audited reconciliation, refunds, financial adjustments, the enforcement PWA, scoped incidents and violations with private evidence and retained resolution history. Milestone 14 adds scoped Help Desk complaints, public submissions, authored notes, assignment/resolution and a database notification center with local display preferences. Reports remain in their approved later milestone.

Government retains policy, regulatory authority and ownership of operational and financial data. Pinnacle Tech Hub is the technology provider.

## Controlling documents

Read these before changing functionality, in this precedence order:

1. [Project Scope v1.0](docs/PROJECT_SCOPE_v1.0.md)
2. [Database Schema v1.0](docs/DATABASE_SCHEMA_v1.0.md)
3. [Screen Inventory v1.0](docs/SCREEN_INVENTORY_v1.0.md)
4. [Architecture & Implementation Plan v1.0](docs/ARCHITECTURE_IMPLEMENTATION_PLAN_v1.0.md)

Implementation notes: [Decisions](docs/DECISIONS.md), [Required decisions](docs/DECISIONS_REQUIRED.md), [Status](docs/IMPLEMENTATION_STATUS.md), [Milestone 14 validation](docs/MILESTONE_14_VALIDATION.md), [Milestone 13 validation](docs/MILESTONE_13_VALIDATION.md), [Milestone 12 validation](docs/MILESTONE_12_VALIDATION.md), [Milestone 11 validation](docs/MILESTONE_11_VALIDATION.md), [Milestone 10 validation](docs/MILESTONE_10_VALIDATION.md), [Milestone 9 validation](docs/MILESTONE_9_VALIDATION.md), [Milestone 8 validation](docs/MILESTONE_8_VALIDATION.md), [Milestone 7 validation](docs/MILESTONE_7_VALIDATION.md), [Milestones 4–6 validation](docs/MILESTONES_4_6_VALIDATION.md), [Milestone 3 validation](docs/MILESTONE_3_VALIDATION.md), [Foundation validation](docs/VALIDATION.md).

## Stack and architecture

- Laravel 12, PHP 8.2+, Eloquent, Form Requests, Policies and transactional domain Actions
- React 19, TypeScript, Inertia 2, Tailwind 4 and Vite; compiled assets, no Node production server
- Spatie Laravel Permission 6 and Activitylog 4
- MySQL 8 target with InnoDB; database queues/cache/sessions
- SQLite for fast isolated tests; MySQL integration tests supported

`app/Domains` contains the approved domain boundaries. Controllers adapt requests/responses; Actions perform writes; Services own shared rules; Queries own scoped filtering. React presents server-provided data. Financial writes use decimal strings, immutable ledger/receipt records, row locks and idempotent database transactions.

LGAs, Parks and Routes connect to Operators through retained park/route approvals, and to Drivers and Vehicles through historical DriverAssignments. Tickets retain their original fees and operating context independently of later registry changes. See ADR-007–010.

## Local installation

Requirements: PHP 8.2+ with PDO MySQL, PDO SQLite, mbstring, OpenSSL, fileinfo, ctype, DOM/XML, XMLWriter and tokenizer; Composer 2; Node 22 LTS-compatible runtime; npm; MySQL 8; Git.

From the repository directory:

```powershell
composer install
npm.cmd ci
Copy-Item .env.example .env
php artisan key:generate
```

On Linux/macOS use `npm` and `cp .env.example .env`. On an already configured workspace, preserve the existing `.env` and `APP_KEY`.

Create an empty MySQL database named `ospm` and a dedicated database user using your database administration tool. Use UTF-8 (`utf8mb4`) and InnoDB. Set these values in the ignored `.env`:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=ospm
DB_USERNAME=your_local_database_user
DB_PASSWORD=your_local_database_password

OSPM_DEMO_MODE=true
PAYMENT_MODE=demo
PAYMENT_PROVIDER=demo
DEMO_DEFAULT_PASSWORD=your_unique_strong_demo_password
```

Choose a demo password with at least 12 characters, mixed case and a number. No default password is committed. The implemented gateway simulates payments only. It requires all three demo settings above and refuses the production environment; no real funds are charged.

```powershell
php artisan migrate
php artisan db:seed
npm.cmd run build
php artisan serve --host=127.0.0.1 --port=8000
```

Open [the local login page](http://127.0.0.1:8000/login). For frontend development, run `npm.cmd run dev` in a second terminal. Laravel remains the backend and serves production builds without Vite or Node running.

The prepared workspace uses an **isolated MySQL 8.4.11 instance on port 3308**, extracted from the official MySQL archive without administrator installation. Its generated database/demo credentials are stored only in `.env`; its runtime and data are under ignored `.qa`. If that local instance has stopped, it can be restarted with:

```powershell
Start-Process -FilePath 'C:\wamp64\www\OSPMS\.qa\mysql-8.4.11-winx64\bin\mysqld.exe' -ArgumentList '--no-defaults','--basedir=C:/wamp64/www/OSPMS/.qa/mysql-8.4.11-winx64','--datadir=C:/wamp64/www/OSPMS/.qa/mysql8-data','--port=3308','--bind-address=127.0.0.1','--mysqlx=OFF','--log-error=C:/wamp64/www/OSPMS/storage/logs/mysql8-local.log' -WindowStyle Hidden
```

Do not initialize that directory again or delete `.qa` while using this prepared local database. It already contains configured databases. Fresh clones should use their own MySQL 8 installation with the standard setup above. See the validation report for the corrected earlier port-3307 MariaDB checks and restored MariaDB login setting.

## Demo accounts

The seeder creates one synthetic user for every required role. Sign in using the username below or `<username>@demo.local`; every seeded account initially uses `DEMO_DEFAULT_PASSWORD` from your own `.env`.

| Username | Role |
|---|---|
| superadmin | Super Administrator |
| stateadmin | State Administrator |
| executive | Executive Viewer |
| finance | Finance Administrator |
| revenue | Revenue Officer |
| auditor | Auditor |
| lgaadmin | LGA Administrator |
| parkmanager | Park Manager |
| ticketing | Ticketing Officer |
| collection | Collection Agent |
| enforcement | Enforcement Officer |
| helpdesk | Help Desk Officer |
| operator | Transport Operator |

Demo seeders refuse production. Scoped users land on **My Access**, where their LGA/Park scope links open local dashboards. State Dashboard access requires its own permission. Auditors are read-only by default. User/role administration is reserved for explicitly permitted users. Scope records distinguish `view` from `manage`; an empty scope never implies statewide access.

Milestone 3 seeds three clearly labelled demo LGAs, parks and routes. These are synthetic presentation records, not official park registrations. The LGA Administrator manages Osogbo (Demo); the Park Manager manages Demo Osogbo Central Park. Ticketing and Collection demo users have manage scope for that park to issue tickets; Enforcement and Help Desk retain view scope. Each action still requires its own permission. Milestones 4–6 add two synthetic operators, six drivers, six vehicles, four assignments, one revenue head and a NGN 500.00 default fee. The operator demo account has view access to Demo OSG Transport Services only. Seed amounts are presentation fixtures, not approved Government charges. Milestone 7 adds one synthetic unpaid ticket through the normal issuance action. Repeated seeding preserves registry edits, archival decisions, assignment status and ticket history.

```powershell
php artisan ospm:demo-reset
```

The command refuses non-demo and production environments. It restores demo passwords/status/roles, baseline registry scopes and sessions, and ensures missing demo registry records exist. It preserves other accounts, existing registry edits/archives, route assignment history and audit history. It does not restore an archived registry record or reset its edited status. Issued tickets, payments, receipts, ledger and financial audit history are retained; reset does not recreate cancelled tickets or reversed payments. There is no reset button or public reset route.

## LGA, Park and Route workflows

Open **Operations → LGAs / Parks / Routes**. Authorized state administrators can create and edit all three registries. Park editing requires `manage_park` plus manage-level access to the existing park and any destination LGA; scoped accounts cannot register a park by default. LGA and global Route administration require explicit management permission and statewide scope.

Use a park's **Routes** tab to assign approved active routes. Assignment requires `assign_park_routes` plus park management access. Removing a route preserves the assignment as inactive history. Scoped Route lists/details derive visibility through active assignments to accessible parks; shared-route profiles expose only accessible park relationships.

Status changes require UI confirmation and are audited. An active park requires an active LGA; active parks must be suspended/deactivated before deactivating their LGA. First activation time is retained across suspension/reactivation. Archives use soft deletion and are blocked when linked registry history exists, including inactive assignments and archived child parks. Use inactive status for those records.

LGA/Park dashboards use actual scoped park, route, operator, driver, vehicle and ticket counts. Related profile tabs include scoped tickets. Milestone 9 adds permission-controlled financial totals, trends, breakdowns and recent transactions from the retained ledger.

## Operators, drivers, vehicles and assignments

Open **Operations → Operators / Drivers / Vehicles / Assignments**. Registrations receive server-generated references. Approval and suspension require their own permissions; geographic manage access alone never grants those actions. The Super Administrator and State Administrator can administer the registries; authorized LGA/Park accounts remain scoped. Operator users have read access to their own profiles and assignments.

An operator's edit screen manages approved parks and routes by park. Removed relationships remain inactive history. A local administrator cannot change a shared master with relationships outside their managed scope. Existing relationships can be retained when a park/route becomes inactive, so suspension and administrative maintenance remain possible; new assignments still require active participants and current approvals.

Register drivers and vehicles before assigning them. Scoped registrars can see their own unassigned registrations; other unassigned records are restricted. Assignments validate driver, vehicle, operator, park and optional route together. End the existing primary assignment before creating another. End dates must fall between the assignment start and now. Historical rows are retained. The assignment form provides server-filtered participant lookup, capped at 100 results per category.

Use each profile's **Documents** tab to upload documents or photographs. Files receive generated filenames and remain outside the public web root. Document access uses the same profile policy and is audited. Identity document contents, file paths and hashes are not sent in page props.

## Revenue heads and fees

Open **Finance → Revenue heads / Fee configurations**. Finance Administrator and Super Administrator can manage configuration; State Administrator, Revenue Officer and Auditor have read access by default. Local and collector roles cannot administer fees.

Fee amounts use decimal strings and MySQL DECIMAL(15,2), with NGN currency. Configure optional vehicle category, LGA, park and route constraints and UTC effective times. Start times are inclusive and end times exclusive. Inactive/draft/expired or future fees do not resolve.

The backend fee resolver chooses park scope before LGA before statewide, then route and vehicle specificity, then higher priority, then the latest effective start. Equally ranked conflicts are rejected for finance review. Every non-null constraint must match. See ADR-009 for the full ordering.

Once a fee takes effect, its terms are immutable; it may be deactivated. Create a new future fee for replacement terms. Future configurations can be edited. Issued tickets snapshot their resolved fee and operating context. Ticket issuance creates unpaid obligations; demo payments record simulated collection only.

## Tickets and public verification

Open **Ticketing → Tickets**. Ticketing Officer and Collection Agent can issue within their managed park scope; Super Administrator can issue statewide. Select a current assignment and revenue head, review the backend-resolved fee, then confirm. The server rechecks active participants, approvals, permission, scope and fee at issuance. Changed terms require a new review. Repeated submission of the same reviewed request returns the original ticket.

Tickets start pending/unpaid. Their fee, amount, currency, issuer and operating snapshots are retained. Ticket details, print view and related profile tabs require ticket permission and scope. A permitted supervisor can cancel an eligible pending unpaid/failed ticket with a reason; collectors cannot cancel. Paid, payment-pending, reversed, cancelled and expired tickets cannot use this cancellation flow.

QR codes link to `/verify/ticket/{token}` on the configured `APP_URL`. Set `APP_URL` to the address reachable by the scanning device, using HTTPS for deployment; localhost is only reachable on the same machine. Public verification shows an allowlist of ticket reference, vehicle plate, park, fee, amount, dates and statuses. It excludes private driver/operator data and signed-in account details. An unpaid ticket is authentic but requires payment; only a paid ticket with paid payment status and unexpired validity is valid for use. Invalid tokens return the same safe not-found page. Ticket printouts are not payment receipts.

`OSPM_TICKET_EXPIRY_MINUTES` is blank by default because no policy duration was supplied. Configure a positive number of minutes only when the applicable validity policy is known. New tickets capture the resulting expiry; existing tickets are unchanged. Run `php artisan ospm:tickets-expire` manually or through the scheduler. Cancelled/reversed records retain their status. Expiry never changes the original amount or payment status.

## Demo payments, receipts and ledger

Open an eligible ticket and select **Demo payment**. Ticketing Officers and Collection Agents need collection permission and manage access to its original park. Select successful, failed or pending and confirm the clearly labelled simulation. Amount and currency come from the ticket snapshot, never the submitted browser values. Success creates one canonical receipt and ledger credit atomically. Failed attempts remain history and permit a fresh attempt; pending attempts can be resolved on their payment detail screen. Refreshing or replaying the same confirmation cannot create another payment or credit.

Open **Finance → Payments** to search and filter permitted payment history. Receipt detail offers HTML print, a server-generated PDF and a public QR verification link. Downloads require receipt permission and the ticket's original scope. The public receipt page excludes private identity, account, token and audit data. It proves the recorded payment independently of ticket expiry; the ticket verification page separately determines validity for use. Reversing a payment invalidates both current verification results while retaining the original receipt and credit.

Finance Administrator and Super Administrator can perform a controlled **demo reversal**, with a required reason and confirmation. It creates a linked debit and retains the original credit. Collectors and read-only roles cannot reverse. Open **Finance → Ledger** for scoped, read-only credits and reversals, search, filters, ordering and transaction traceability. LGA Administrators see their permitted historical geography; collector and Park Manager defaults have no ledger access. Refunds, adjustments, settlement and reconciliation are available to their authorized roles.

Receipt PDFs use Dompdf in PHP with remote resources, embedded PHP and JavaScript disabled. No Node service or external rendering service is required. Keep `storage/framework/cache` writable. Secure QR links depend on the configured reachable `APP_URL`; do not place verification URLs in analytics or access logs exposed to other users.

Milestone 8 seeds three labelled synthetic payment attempts (successful, failed and pending) through the normal actions. Repeated seeding and demo reset preserve payments, receipts, ledger entries, reversals, idempotency keys and audit history. Reset does not restore a reversed payment or manufacture another receipt. See ADR-011 for transaction and retention decisions.

## Revenue dashboards

Open **Dashboard**, **Executive Dashboard** or **Finance → Revenue Dashboard**. LGA and Park profiles link to their local dashboards. Executive accounts land on their read-only summary; navigation and routes require their own dashboard permissions. Revenue permission and historical ticket scope are checked separately. A park viewer without revenue permission receives operational counts only. Park Managers can view their collection summary and payment history without gaining ledger access.

Financial filters default to month-to-date in `OSPM_TIMEZONE` (Africa/Lagos). Choose up to 366 calendar days and optional LGA, park, revenue head and payment channel. Local dashboard identity is fixed by its URL. Today and month-to-date cards use their named periods with the same dimension filters; registry counts show current operational access and do not change with financial filters.

**Gross credits** total retained ledger credits; **debits** include reversals; **net revenue** is credits minus debits, using ledger occurrence dates. A reversal in a later period creates a negative entry in that period and does not erase the earlier collection. Failed/pending payment attempts contribute no ledger revenue. Status and channel charts count attempts initiated in the selected period using their current status, so they are distinct from ledger entry counts. Phase 1 financial summaries use NGN only.

Charts show daily net revenue, revenue by LGA/park/revenue head, payment status and payment channels. Keyboard chart navigation and expandable exact figures are available. Distribution charts show up to ten groups; their exact figure tables include all matching groups. Authorized links open Payments/Ledger with matching date, timezone, currency and scope filters. Lists opened directly retain UTC date defaults. Geography follows the ticket's retained LGA/park IDs, including after a park move; distribution labels use current retained registry names.

Dashboard aggregation runs in Laravel queries against committed records, with exact MySQL DECIMAL sums and decimal-string arithmetic. Recharts receives numeric values for plot coordinates only; React does not calculate revenue. No aggregate tables, financial writes or dashboard cache are introduced. Pending reconciliation now counts selected-period credits without a latest matched or reviewed finding. Ledger totals do not imply treasury settlement. See ADR-012 and ADR-013.

## Password recovery and sessions

Forgot/reset password uses Laravel's password broker, expiring single-use tokens, generic account lookup responses and rate limits. The local `array` mailer intentionally does not deliver messages or log reset tokens. Configure an actual SMTP transport (for example a local mail catcher) to test delivery:

```dotenv
MAIL_MAILER=smtp
MAIL_HOST=your_mail_host
MAIL_PORT=your_mail_port
MAIL_SCHEME=
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_FROM_ADDRESS=your_authorized_sender_address
```

Never commit SMTP credentials. Password changes revoke other stored sessions; administrative credential changes/deactivation also rotate remember tokens and revoke target sessions. Users created by administrators can be required to change their temporary password before continuing.

## Branding and configuration

`config/ospm.php` is the configuration entry point. Neutral demo colours are not official Osun Government branding. The simple building icon is a neutral UI symbol, not a government seal.

Configurable keys include `OSPM_BRAND_PRIMARY`, `OSPM_BRAND_PRIMARY_DARK`, `OSPM_BRAND_SECONDARY`, `OSPM_GOVERNMENT_LOGO` (a trusted public asset URL/path) and `OSPM_POWERED_BY`. Colours should be CSS hex values. Primary colours flow through shared CSS/Tailwind tokens and all layouts. Branding administration screens are deferred to Milestone 16.

Timestamps are stored in UTC. `OSPM_TIMEZONE=Africa/Lagos` controls display and date-filter boundaries; `OSPM_CURRENCY=NGN` controls money labels. Do not change the approved public product name. Environment variables hold secrets; shared Inertia props contain only public configuration.

Registry uploads use private local storage through Laravel Filesystem. PDF/JPG/PNG files are limited to 5 MB; photo categories accept images only. Authorization protects uploads, downloads and photo previews. Object storage remains a later configurable deployment target; the AWS Flysystem adapter is not installed or claimed as tested.

## Tests and checks

```powershell
php artisan test
composer lint
npm.cmd run typecheck
npm.cmd run lint
npm.cmd run build
php artisan migrate:status
composer validate --strict
```

The PHPUnit default is isolated SQLite `:memory:`. Run `php artisan config:clear` before tests if you previously cached deployment configuration. Tests cover the identity foundation, registry CRUD/status/audit, route assignment history, cross-LGA and shared-route isolation, scoped dashboards, validation and demo safeguards. Composer's strict schema check reports a missing license metadata warning; no licensing policy has been assumed.

To run against MySQL, create a **separate disposable test database**, then override both connection and database:

```powershell
$env:DB_CONNECTION='mysql'
$env:DB_DATABASE='ospm_test'
php artisan test
Remove-Item Env:\DB_CONNECTION
Remove-Item Env:\DB_DATABASE
```

The test suite recreates its configured database tables. Never point it at an operational/demo database that must be retained. See [the current validation report](docs/MILESTONE_14_VALIDATION.md) for executed checks, including MySQL 8.4.

## Settlement and reconciliation

Milestone 10 implements SCR-071–078 at `/finance/settlements` and `/finance/reconciliation`. Finance Administrators and Super Administrators can create demo batches, start matching runs and review exceptions. Auditors can inspect retained records without write access. State/Executive/Revenue viewers can read reconciliation; LGA viewers receive only their original ticket geography, with scoped totals and no batch-wide settlement details.

Create a demo settlement from successful, unreversed NGN credits whose ticket/payment/ledger lineage agrees and which have never been batched. Amounts are calculated in Laravel. The mismatch scenario records the first provider item short by NGN 0.01, without modifying its original credit. Synthetic fees are zero and government account references stay unset. Demo batch creation refuses production/live configuration; no treasury transfer or live provider import exists.

Start a reconciliation run for inclusive local dates (maximum 366 days), optional LGA/park and provider. Runs compare one obligation per ticket with successful payments, provider references, credit ledger entries and settled provider gross. Paid/reversed tickets outside the issue period are included when their payment/reversal occurs in the selected period. Failed/pending attempts do not add received funds or duplicate obligations. Amounts, missing lineage, duplicate references, absent settlements, reversals and unrecognized entries produce explainable retained findings.

A completed run is immutable and returns its existing findings on duplicate delivery. A new run captures current committed sources. The overview uses the latest finding per obligation/source, so reruns do not duplicate totals; run details preserve each earlier snapshot. Review requires an outcome and reason, stores actor/time, and appends both general and financial audit events. `Reconciled` means a manually reviewed outcome; original exception types and monetary differences remain. It never adjusts funds or overwrites historical amounts.

Seeding/reset ensures a clean match, a missing-payment ticket and an amount mismatch, while preserving prior settlements, runs, reviews and audit history. General financial audit viewers remain Milestone 16. See [Milestone 10 validation](docs/MILESTONE_10_VALIDATION.md) and ADR-013 for matching definitions and checks.

## Refunds and adjustments

Milestone 11 implements SCR-079–084. Open **Finance → Refunds** or **Finance → Adjustments** for paginated request history, reference/status/date filters and sorting. Request a refund from a successful demo payment detail; request an adjustment from a ledger detail, which supplies its public source ID.

Finance and Super Administrators can request corrections, independently review another user's requests and process approved demo refunds. State/LGA/Park supervisors can request refunds within their manage scope. Auditors inspect both workflows without mutation rights. The requester cannot approve or reject their own request, including Super Administrator. Every request and decision requires a reason and explicit confirmation.

Refund approval reserves the amount; only successful demo processing appends a debit linked to the original payment credit. Failed/processing outcomes add no debit. A failed attempt may be retried after revalidating available funds; processing may be resolved with another confirmed outcome. Repeated confirmations return the retained canonical result. No real funds are sent and demo processing refuses production.

Adjustments explicitly select credit or debit. Independent approval immediately appends the corresponding ledger entry linked to the exact selected original transaction. Rejection adds no entry. Amounts use decimal strings, and debit requests/refunds share a serialized balance check so they cannot overdraw the payment ledger. Credit adjustments never increase the provider's original refund entitlement. Corrections do not rewrite original payment amounts, receipts, ledger entries or settlement/reconciliation history.

Any successful refund, including partial refunds, invalidates current receipt and ticket verification for use. Original receipt amounts remain historical. A fully refunded payment and ticket payment status become refunded; the ticket lifecycle status is retained. Reversals refuse payments with active requests or retained corrections. Dashboards include correction credits/debits automatically. A fresh reconciliation run records refund/adjustment evidence as a reversal exception, including corrections to older payments; completed runs remain unchanged. New demo batches exclude corrected payment sources.

The synthetic seed includes an independently approved partial demo refund and a pending debit adjustment. Repeated seeds and demo reset preserve their decisions and audit history. See [Milestone 11 validation](docs/MILESTONE_11_VALIDATION.md) and ADR-014. No live refund provider, treasury rule or approval threshold is configured.

## Enforcement field app

Milestone 12 implements SCR-085–094 at `/field/`. Enforcement Officers land there after login; Super Administrators can open **Field workspace** in navigation. Field tasks are QR scanning, ticket/receipt verification, scoped driver/vehicle/operator lookup and confirmed inspection recording. The demo enforcement officer receives a view scope for the synthetic Osogbo park and can record observations without registry management or finance administration rights.

Use **Start camera** to scan a ticket or receipt, or paste its verification code/QR URL. The scanner requests a rear-facing camera where supported and closes the camera on navigation. Install through the browser’s Install app/Add to Home Screen option where supported. Camera and service-worker support require HTTPS or localhost. Configure a reachable `APP_URL` before sharing printed QR codes; a phone cannot use the development machine’s localhost URL.

Quick views show registration status, recorded expiry evidence and only assignments in the officer’s scope. Verification uses the existing authoritative ticket/receipt services; unpaid, expired, reversed or successfully refunded entitlements are not valid for ticket use. Original amounts remain historical, and receipt validity is distinct from ticket validity.

Create an inspection from verification, a quick view or a current operating context. Choose an observation result, enter notes for review/non-compliant outcomes, optionally obtain location with browser permission, then confirm. The server derives identities and time, rechecks scope/status and retains one inspection/audit for repeated confirmations. “Recorded checks clear” requires current assignment/park, active registrations, recorded unexpired dates and a valid selected ticket; it is not a legal clearance or a penalty.

The offline shell contains no identity or records. Current checks and saving require a connection; no inspection is queued offline. Service-worker caching is restricted to anonymous HTML, manifest and icons. After a connection error, retry the same inspection confirmation before leaving the form. Seeds/reset retain observations and all financial history. Inspection administration, incidents, violations and evidence are implemented in Milestone 13. See [Milestone 12 validation](docs/MILESTONE_12_VALIDATION.md) and ADR-015.

## Incidents and violations

Milestone 13 implements SCR-095–103. Enforcement Officers can report an incident from field home, choose a scoped park and optional current operating assignment, record category/time/description, and attach a photograph or PDF. The field form provides a rear-camera capture input where the mobile browser supports it. Participants and reporter identity are derived by the server. Reports, observations and evidence are retained.

Use **Inspections** to review recorded observations and add private evidence. An authorized officer can record a violation observation from an inspection requiring review or marked non-compliant. Its park, operator, driver and vehicle come from the original inspection. **Violations** provides scoped search/detail and supervisor resolution; open/resolved are operational states and do not create a penalty or financial transaction.

Use **Incidents** for search, filtering, evidence and paginated history. State/LGA/Park supervisors with manage scope can start review, escalate, resolve and close a report. Every change requires a reason and confirmation. A resolved incident can only be closed; closure keeps its final resolution and records a separate closure reason. Resolved violations and closed incidents cannot be reopened through these screens. Auditors and other granted viewers remain read-only; operator viewers see only their own named incidents.

Evidence accepts JPEG/PNG/PDF up to the 5 MB application limit and is stored under `storage/app/private/evidence`. Set the host PHP `upload_max_filesize` to at least `5M` and `post_max_size` above the upload limit (for example `8M`); the current CLI PHP defaults to a lower `2M` upload limit. Use authenticated download links; never publish this directory or run `storage:link` for it. Existing object-storage deployment work remains deferred. Offline saving is unavailable. After an interrupted submission, retry the same form before navigating away. Repeated seeds/reset preserve evidence, decisions and financial history. See [Milestone 13 validation](docs/MILESTONE_13_VALIDATION.md) and ADR-016.

## Queues and scheduler

Framework tables are ready for the approved database queue. Demo reconciliation runs synchronously for presentation datasets. Non-demo reconciliation dispatches the same idempotent job to the configured database queue; run a worker:

```powershell
php artisan queue:work --tries=3
php artisan schedule:list
```

For shared hosting, configure cron to call `php /path/to/ospm/artisan schedule:run` every minute. A provider-approved short-running `queue:work --stop-when-empty --max-time=50 --tries=3` strategy can process the database queue. Milestone 7 registers the daily `ospm:tickets-expire` command without overlap. Verification evaluates expiry immediately even before the scheduled command runs. Avoid overlapping workers according to host facilities.

## Shared hosting deployment

1. Point the HTTPS domain's document root to Laravel's `public/` directory. Keep `.env`, storage, source and vendor directories outside the web root.
2. Build assets using `npm ci` and `npm run build`; deploy `public/build` with the application. Do not run a Node production server.
3. Install production PHP dependencies: `composer install --no-dev --optimize-autoloader`.
4. Supply distinct deployment credentials, `APP_KEY`, `APP_URL`, `APP_DEBUG=false`, HTTPS-only secure session cookies, MySQL 8 and SMTP settings. For synthetic presentations use a non-production demo environment; never use production credentials or genuine citizen data.
5. Ensure `storage` and `bootstrap/cache` are writable by the PHP account. Run `php artisan migrate --force`, then approved seeders only for the intended demo environment.
6. Run `php artisan optimize`; verify login, password recovery and scoped access. Configure cron/queue processing when their workflows are implemented, HTTPS, Cloudflare forwarding and host-managed database backups.

No live Government treasury or revenue collection integration exists. Production PostgreSQL, Redis/Horizon, Nginx/PHP-FPM and object storage remain future targets and are not deployed by this build.

## Security and Phase 1 boundaries

Do not expose synthetic presentation accounts publicly with shared/weak passwords. Keep credentials and application keys out of source control, use HTTPS and separate environments, and perform the later security/QA release gate before any government pilot. Existing automated tests and RBAC provide a foundation; they do not certify the entire unbuilt Phase 1 product.

No microservices, native mobile apps, biometrics, tracking, passenger bookings, wallets, AI fraud detection or other out-of-scope workflows have been added. The complete demonstration journey will be delivered in the approved milestone sequence. The next feature milestone is **Milestone 14 — Complaints + Notifications**.

## Complaints and notifications

Milestone 14 implements SCR-104–110. Open **Operations → Complaints** as Help Desk/Super Administrator to record, receive, assign, review, resolve and close complaints. Assignment/status decisions require a reason; resolution requires its final text. Original reports, authored notes, private evidence and review decisions are retained. State/LGA/Park/Auditor/Enforcement readers use current scope without Help Desk write grants. Operator viewers see only their named complaints and shared notes; internal notes, contacts and evidence are withheld.

The public form is `/public/complaints`. It requires a name, category and description; phone/email, park and JPEG/PNG/PDF evidence are optional. Only active park names/public IDs are listed. Submission is CSRF protected and rate-limited to five/minute and twenty/hour per IP. Success displays a session-owned reference without contact details, a public status lookup or anonymous evidence access. Retry an interrupted request with the same form/details to recover a possible saved result. Refreshing repeatedly can replace older form confirmations; the latest five issued keys remain available in the session.

Located complaints follow current park geography. Unlocated public complaints await statewide/Super Administrator routing; a scoped Help Desk officer can manage their own unlocated submission or one explicitly assigned to them. Assignments require an active Help Desk/Super or otherwise explicitly granted complaint manager with access to the selected park. Public contact details are readable by Help Desk/Super by default.

Open the bell or **Account → Notifications** to see your database notices. Assignment, driver approval, successful payment and reconciliation exceptions notify the eligible assignee/creator/run starter. Read/unread filters and explicit single/all read actions preserve ownership. Links and references disappear when current record authorization is revoked. Existing operational/financial records are not replayed merely to backfill notification history.

**Notification preferences** at `/account/notifications` store unread-badge visibility for the current account in this browser. They do not disable database notices or enable email/SMS. No live external delivery is configured.

Known expired licence/insurance/roadworthiness dates can be checked with:

```powershell
php artisan notifications:expired-documents
```

The command is scheduled daily without overlapping. Run Laravel’s scheduler in deployment (`php artisan schedule:run` each minute, or the appropriate service). Unknown dates and dates valid through the current Africa/Lagos day do not generate expiry notices. Repeated checks retain one notice per account/document/expiry date, without changing compliance status.

Private complaint evidence uses the existing local disk and application 5 MB limit. Configure host upload/post limits accordingly; the current local PHP upload limit remains 2 MB. There is no public evidence URL or offline submission queue. See [Milestone 14 validation](docs/MILESTONE_14_VALIDATION.md) and ADR-017 for scope and executed checks.
