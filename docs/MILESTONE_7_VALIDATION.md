# Milestone 7 validation — Ticketing + QR

Date: 2026-10-08 (Africa/Lagos)
Baseline: develop at 154e8ad1e5d88fd9d41a0555287b6b7a2c86ff8c (Milestones 0–6).
Implementation branch: codex/ticketing-qr, integrated into develop on completion.

## Delivered scope

| Screen | Implementation |
|---|---|
| SCR-056 Ticket List | Scoped search, filters, statuses, sorting, pagination and empty/loading/error states |
| SCR-057 Issue Ticket | Current assignment lookup, backend fee review, confirmation and transactional issuance |
| SCR-058 Ticket Detail | Original fee/context, latest statuses, protected QR, authorized related links and activity |
| SCR-059 Ticket Print View | Protected printable ticket with QR and an explicit ticket/receipt distinction |
| SCR-060 Ticket Cancellation | Supervisor permission, manage scope, eligible unpaid state, mandatory reason and audit |
| SCR-067 Public Ticket Verification | Token lookup, safe public fields, payment-aware validity and not-found/rate-limit responses |

The migration follows the approved Ticket columns, restrictive foreign keys, DECIMAL(15,2), unique public ID/reference/token and recommended lookup indexes. No soft deletion, payment, receipt or ledger table was added. Ticket snapshots cannot be mutated or deleted through the model. Registry archive guards now retain linked ticket history.

Backend Actions enforce permission and scope independently of HTTP. Issuance revalidates current assignment dates, active participants, operator/route approvals and the backend-selected fee under locks. A review HMAC binds the actor, operating terms and request key; stale/tampered reviews fail. Database uniqueness and bounded reference retry protect generated identifiers. Repeated submissions of the same review return one retained ticket. Events dispatch after commit; audit excludes verification tokens and private document/identity contents.

Historical ticket geography uses the recorded LGA ID. Scoped ticket tabs are available on Park, Operator, Driver and Vehicle profiles; local dashboards show real ticket counts. Collection totals remain unavailable until payments exist.

## Automated application checks

- Full SQLite suite: **93 tests, 1,957 assertions passed**.
- Full MySQL 8.4.11 suite: **93 tests, 1,957 assertions passed**, using the separate disposable ospm_test database on port 3308.
- Seventeen ticketing feature tests cover all six screens, forged client values, active-context validation, changed fees, original snapshots after master changes, immutable records, reference collision retry, replayed confirmation, permission/view/manage isolation, operator privacy, supervisor cancellation, ineligible statuses, immediate expiry, idempotent expiry command, public allowlists, signed-in public privacy, token/rate limits, search/filter/pagination/tabs/counts, historical LGA access and non-destructive synthetic seeding.
- All 203 PHP source/configuration/migration/test/entry-point files passed syntax checks. The six subsequently edited PHP files were checked again.
- Pint / composer lint passed.
- ESLint, TypeScript and production Vite build passed. The build includes tsc --noEmit; no production Node server is required.
- Composer dependency/lock validation is consistent. Its pre-existing missing-license metadata warning remains because no license policy was supplied. Composer audit reported no advisories.
- Main synthetic demo database migrated and seeded successfully without resetting retained history.
- Ticket migration rollback followed by remigration passed on ospm_test only; the retained demo database was not rolled back.
- Route inventory contains seven protected ticket routes and one public verification route. Scheduler inventory contains the daily non-overlapping ospm:tickets-expire command.

The JSON snapshot test captures the persisted snapshot before changing masters, so MySQL's JSON key normalization does not create a false order-sensitive failure. Values/types still compare strictly after the changes.

## MySQL concurrency and isolation checks

Two separate PHP processes submitted the exact same reviewed request at a common start time against the retained synthetic demo database. Both returned the same ticket public ID; the database contained exactly one ticket and one ticket_created audit event for that request. This was repeated after the final locking changes.

A separate two-connection MySQL REPEATABLE READ check established an old fee snapshot, deactivated that synthetic fee on the other connection, and demonstrated that an ordinary consistent read still saw the old active fee. The locking resolver correctly saw the committed deactivation and rejected issuance terms. Temporary isolated fee fixtures were removed afterward. Fee candidates, revenue head and relationship approvals use current locking reads for issuance; fee writes and issuance have bounded deadlock retries.

## Browser and visual checks

Chrome headless at 1440 × 1000 and 390 × 844 exercised ticketing login, empty search, assignment lookup, fee review, confirmation, issuance, details, printing, anonymous verification, invalid token, supervisor cancellation, cancelled verification, mobile pages and the Park Tickets tab.

- **15 axe WCAG 2 A/AA and 2.1 A/AA samples: zero violations**.
- **Zero JavaScript page errors**.
- Mobile list, issue, detail and print pages had no horizontal page overflow.
- The rendered QR image was captured and decoded independently using jsQR; its payload exactly matched the server-generated public verification URL.
- Ticketing Officer had no cancellation control. Super Administrator cancellation required a reason and confirmation; public verification then reported not valid for use.
- Print media hid the print action. A QA-only PDF export was produced from Chrome; the product feature remains the approved HTML print view.
- Desktop ticket detail and mobile public verification screenshots were visually inspected for typography, layout, QR clarity, wrapping and status wording.

Local evidence is retained under ignored .qa: m7-sqlite.xml/log, m7-mysql.xml/log, m7-browser-result.json, m7-ticket-desktop.png, m7-verification-mobile.png and m7-ticket-print.pdf. QA helpers/dependencies and generated artifacts are not application dependencies or tracked source. Automated accessibility samples are not a complete accessibility certification.

## Repeatable core commands

```powershell
php vendor/phpunit/phpunit/phpunit
composer lint
npm.cmd run lint
npm.cmd run build
composer validate --strict
composer audit --locked
php artisan migrate:status
php artisan route:list --path=tickets
php artisan route:list --path=verify
php artisan schedule:list
```

For MySQL, override both connection and database before PHPUnit, then remove the overrides. The suite recreates tables; use only a dedicated disposable database.

```powershell
$env:DB_CONNECTION='mysql'
$env:DB_DATABASE='ospm_test'
php vendor/phpunit/phpunit/phpunit
# Migration round trip only after tests finish, against this disposable database:
php artisan migrate:rollback --step=1 --force
php artisan migrate --force
Remove-Item Env:\DB_CONNECTION
Remove-Item Env:\DB_DATABASE
```

Browser reproduction: sign in as ticketing, select the DEMO-OSG-001 assignment and Demo daily park ticket, review NGN 500.00 and issue; scan/open its QR anonymously. Verify payment-required wording, print the ticket, then sign in as superadmin and cancel with a synthetic reason. Verify the same public URL now reports not valid for use. Do not share the local demo password or use real citizen data.

## Configuration, boundaries and remaining work

No conflict required changing the four controlling v1.0 documents; they remain unchanged. ADR-010 records implementation choices. The only new runtime Composer package is BaconQrCode 3 with its enum dependency; XMLWriter is required for SVG output.

APP_URL determines QR destinations and must be reachable from the scanning device. The prepared localhost address is suitable for same-machine QA only. OSPM_TICKET_EXPIRY_MINUTES is blank by default because no Government expiry duration was supplied. Configuring it affects new tickets only; verification rejects elapsed tickets immediately, while the daily command persists their expiry and audit. Unpaid authenticity never implies a completed collection or receipt.

No Milestone 8 payment/receipt/ledger, live gateway/treasury integration, settlement/refund, enforcement scanner/PWA or later reports were started. Existing SMTP delivery and object-storage limitations remain. There are no blockers to the implemented Milestone 7 workflows. Next authorized feature work would be Milestone 8 — Demo Payments + Receipts + Ledger.
