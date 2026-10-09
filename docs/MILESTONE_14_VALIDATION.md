# Milestone 14 — Complaints + Notifications validation

Date: 2026-10-09 (Africa/Lagos)
Scope: Milestone 14 only, SCR-104–110. The four controlling v1.0 documents are unchanged. Implementation decisions are recorded in ADR-017. Milestone 15 is not started.

## Completed workflows

- Approved Complaint/ComplaintNote tables and standard Laravel database notifications, status/source enums, retained models, policies, Form Requests and transactional actions.
- Scoped Help Desk queue with search, status/assignment filters and pagination; staff recording; detail, current eligible officer assignment, internal/shared authored notes, review, resolution and closure with retained reasons.
- Public name/category/description validation, optional contacts/park/evidence, active park public IDs/names, CSRF, five/minute and twenty/hour IP limits, session-bound confirmation and session-owned reference-only success.
- Private complaint evidence reuses approved media metadata/storage, nullable public uploader, byte-bound replay, parent authorization, no-store/nosniff attachment download and rollback/replay cleanup.
- Assignment/driver approval/payment success/reconciliation exception database notices, atomic source transactions, deterministic UUID deduplication, own-account center/read actions, authorization-aware subject links, real unread count and browser/account display preferences.
- Known expired document notices and daily non-overlapping scheduler registration; retained demo seed/reset integration. No new financial mutation, external message delivery, public status lookup, SLA or incident assignment workflow.

## Automated suites

Environment: PHP 8.3.26, Laravel 12, MySQL 8.4.11 on port 3308, Node 22.23.2; Xdebug disabled for CLI validation. The retained main database is ospm. All destructive suite/schema checks targeted disposable ospm_test or SQLite :memory:.

| Check | Result |
|---|---|
| Focused ComplaintsNotificationsTest | 35 passed, 434 assertions |
| Complaint/payment/reconciliation integration filter | 86 passed, 1208 assertions, one existing MySQL-only SQLite precision skip |
| Full MySQL suite | **283 passed, 4909 assertions**, no failures/skips; 397.08 seconds |
| Full SQLite suite | **280 passed, 4897 assertions**, three existing MySQL-only precision skips; 334.03 seconds |
| PHP syntax | 386 PHP files checked, including two ignored framework bootstrap cache files; no syntax errors |
| Pint | Passed after formatting |
| TypeScript / ESLint / production build | Passed |
| Scanner normalization regression | Six Node tests passed |
| npm / Composer vulnerability audit | Zero vulnerabilities/advisories; no abandoned Composer packages reported |
| Composer strict validation | JSON schema valid; existing unspecified-license warning remains, so strict validation returns a warning exit status |
| Git whitespace / staged secret-artifact check | Passed |

Commands executed:

```powershell
php artisan test --compact --filter=ComplaintsNotificationsTest
php artisan test --compact --filter='PaymentsTest|ReconciliationTest|ComplaintsNotificationsTest'
# DB_CONNECTION=mysql, DB_DATABASE=ospm_test, DB_HOST=127.0.0.1, DB_PORT=3308
php artisan test --compact --log-junit=.qa/m14-final-mysql.xml
# DB_CONNECTION=sqlite, DB_DATABASE=:memory:
php artisan test --compact --log-junit=.qa/m14-final-sqlite.xml
php vendor/bin/pint app bootstrap config database routes tests
composer lint
npm run typecheck
npm run lint
npm run build
npm run test:field
npm audit --json
composer audit --format=json
composer validate --strict
git diff --check
```

The 35 new tests cover exact column sets; all seven screens/private encrypted history; server identity/source/reference derivation; authenticated-public prop redaction; session confirmation and reference guessing; cross-session rejection; rate limiting; trimmed content/contacts/park/file validation; prohibited public participant context; geographic revocation including replay/evidence; unlocated routing; the role matrix; derived assignment participants and own-operator privacy; eligible assignee checks; full status workflow; retained note author/time, resolution and closure; skipped/stale/terminal decisions; action/note/evidence replay; immutable reports/notes and delete refusal; changed file-byte rejection and no orphan; notification-failure transaction rollback; ownership-safe read/read-all; authorization-aware notification redaction; role-free account preferences; inactive account revocation; expired known documents and deduplication; real driver approval delivery; retained demo seed/read state. Existing payment and reconciliation tests now also assert actual notification delivery, replay, and absence for failed/pending or clean-match outcomes.

## MySQL schema and concurrency

Executed migrate, schema inspection, latest migration rollback and remigration on ospm_test. Exact complaint 20-column, note six-column and standard notification eight-column sets were verified, with eight RESTRICT complaint/note foreign keys, two unique complaint identifiers and notification UUID/morph identity. No unapproved preference or incident-assignee schema was added.

Three actual two-process MySQL scenarios used separate CLI workers, a start barrier and synthetic records in retained ospm, without financial writes:

1. Concurrent identical public confirmations returned one canonical complaint and one submission audit.
2. Concurrent identical assignment confirmations returned one decision, one assignment audit and one database notice.
3. Competing assignment keys with the same expected state produced one committed decision/notice and one stale-decision rejection, without partial confirmation history.

## Chrome and accessibility

Headless installed Chrome with Playwright checked all seven screens at desktop 1440×1000 and mobile 390×844. All seven mobile screens had no horizontal page overflow. **24 axe WCAG 2 A/AA and 2.1 A/AA samples had zero violations**, including public submission confirmations, every review action confirmation and a closed complaint. No JavaScript page errors occurred. Mobile screenshots were captured; the complaint-detail screenshot was visually inspected.

Executed browser workflow:

- Anonymous public submission with a real PNG upload; reference-only success and no contact disclosure.
- A second public POST was committed by the server, then its response was deliberately lost. The form retained its fields/key. Retrying the same details recovered the canonical complaint/reference.
- Help Desk fetched the private PNG; downloaded bytes matched the original and no-store/nosniff headers were present.
- Received, assigned, added an internal note, started review, resolved and closed the recovered public complaint using the UI. Closure removed write/evidence controls and retained resolution/history.
- Viewed the assignment notice, explicitly marked it read and found it through the read filter.
- Saved badge visibility, reloaded and verified persistence. A separate browser run verified that another account on the same browser retained its independent default/preferences.
- A real web POST without CSRF returned 419. A fresh anonymous session guessing a success reference received a redirect to the form rather than a complaint result.

The accessibility check initially caught a label/content mismatch on the notification read button; the final name includes its visible “Mark as read” text and all final samples passed. Browser retries used no file; byte-bound anonymous upload retries were exercised by the automated suite. Early harness runs retained additional synthetic public submissions; they were not deleted or presented as duplicates for the final confirmation.

## Retained data

Independent database verification found one canonical final browser complaint for its confirmation, public source/no staff submitter, closed status, complete final resolution/time, one correctly authored internal note, four status-change audits, one assignment audit and one read assignment notification. Anonymous evidence metadata and private bytes matched SHA-256.

All **nine private evidence files** in the retained demo evidence directory matched their media records/hashes, with no orphan files found. The previously completed incident/violation resolutions and evidence counts remained unchanged. All **133 existing financial audit entries** retained their correct predecessor/hash chain; Milestone 14 validation did not add or rewrite financial history. Synthetic seed/reset preservation was verified in the isolated automated suite. The main database was never refreshed/reset.

## Scope and limitations

Public success uses the server session and exposes no lookup/detail/evidence endpoint. Scoped Help Desk access requires current view geography and action permission; assignment cannot bypass located geography. Unlocated public complaints require statewide/Super routing. Shared notes may be shown to authorized own-operator viewers; internal notes, contacts, private files and staff review reasons are redacted for them.

Preferences are local to the account/browser, as approved for SCR-110; email/SMS delivery is not enabled. Database delivery is synchronous/transactional and needs no worker; expiry checks require the deployment scheduler. No advance-expiry window, SLA, offence, penalty, anonymous identity-less report, public tracking portal or incident-assignee schema is invented. The approved source field includes field, but no unapproved enforcement complaint-write grant is introduced.

Existing release limits remain: private evidence uses local storage; physical mobile/HTTPS and production-load checks are later QA work; host PHP upload/post limits must support the 5 MB application limit (current local upload limit is 2 MB). Ordinary rollback/replay cleanup passes; a process crash between file storage and transaction completion can leave an orphan. Repository license metadata remains unspecified. No implementation blocker remains for this milestone.
