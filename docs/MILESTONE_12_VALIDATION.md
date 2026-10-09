# Milestone 12 — Enforcement PWA validation

Date: 2026-10-09 (Africa/Lagos)
Scope: SCR-085–094 and basic inspection creation only. Source branch: codex/enforcement-pwa. Integration branch: develop. Personal SSH remote: git@github-personal:Pinnacle96/OSPMS.git.

## Implemented

- All ten field screens: home, camera/manual scan, ticket/receipt verification, driver/vehicle/operator search and quick views, confirmed inspection creation. Mobile layout, accessible navigation/forms, scoped pagination, installation prompt/instructions and clear connection states.
- Exactly the approved inspections schema: 16 columns, two unique identifiers, six restrictive foreign keys, optional paired DECIMAL(10,7) coordinates, server officer/reference/time, retained observations. No updated_at, assignment ID or incident/workflow columns added.
- Enforcement field permissions and landing, with existing desktop read access retained. Finance administration remains inaccessible. Safe allowlists exclude private contact/licence/address/owner/document data and unrestricted related history.
- Existing authoritative safe ticket/receipt services and historical ticket geography, including pending/expired/reversed/refunded states. Original amounts stay historical; receipt validity and ticket use validity are separate. Registry summaries distinguish clear/attention/missing evidence without a legal clearance or penalty decision.
- Scoped current operating contexts and server-derived foreign keys. Clear observations require active registrations/park, current matching assignment, recorded unexpired dates and valid selected ticket. Other observations require notes. Revalidate under locks; confirmation, inspection and general audit are atomic.
- Shared ConfirmedActionService, with FinancialConfirmationService preserved as a compatibility subclass. Actor/operation/payload-bound retries, permission/geography checks on replay, immutable history and retained seed/reset fixtures.
- Installable manifest, three generic icons and anonymous offline HTML. Worker lives at /field/sw.js; static assets live under public/pwa-field so no directory shadows Laravel's /field route. Canonical /field/ is preserved by Apache rules and save redirects. Authenticated field responses are private/no-store with encrypted history; logout and inactive-account revocation clear history after session invalidation.
- Only five anonymous assets enter CacheStorage. Install/update fetches force fresh anonymous resources, and activation removes previous field-shell caches. No identity, authenticated page, token/result URL, Inertia JSON, financial response, POST or offline queue is cached. Offline verification is indeterminate; searches and saving require a connection.

ADR-015 documents the minimal inspection vocabulary, compliance meaning, scope/history behavior, confirmation sharing and conservative offline boundary. The four controlling v1.0 files remain unchanged.

## Automated checks

Final suites ran sequentially against isolated SQLite memory and disposable MySQL 8.4.11 ospm_test on port 3308. The retained ospm database was never a test-suite target. XDEBUG_MODE=off.

```powershell
$env:DB_CONNECTION='mysql'
$env:DB_DATABASE='ospm_test'
php artisan test --compact --log-junit=.qa/m12-final-mysql.xml
Remove-Item Env:\DB_CONNECTION
Remove-Item Env:\DB_DATABASE
php artisan test --compact --log-junit=.qa/m12-final-sqlite.xml
composer lint
npm run typecheck
npm run lint
npm run test:field
npm run build
composer validate --no-check-publish
composer audit --format=plain
npm audit --audit-level=low
php artisan migrate --pretend
php artisan route:list --path=field --except-vendor
```

MySQL: **215 passed / 4038 assertions**, no skips. SQLite: **212 passed / 4026 assertions**, 3 existing MySQL-only numeric-precision skips. The 26 enforcement tests cover the ten screens, privacy/scope, related-history redaction, lookup escaping, safe verification and corrections, server-derived identities, replay/actor/payload binding, revoked permission/geography, stale/ended/deleted contexts, missing/expired evidence, validation, audit rollback, retained schema/models/seeds, active/password gates, history clearing and actor request limits. Existing finance/authentication/registry suites also passed after confirmation sharing.

Six Node tests exercise the actual TypeScript scanner normalizer, including ticket/receipt/local/configured-origin links and rejection of malicious origins, credentials, schemes, routes, malformed tokens and ambiguous queries. TypeScript, ESLint, production build, Pint and 333 PHP syntax checks passed. Composer/npm audits reported no advisories. Composer validation retained only the existing unspecified-license warning; no license was invented.

Intermediate runs are not counted as final validation. A FormRequest method-name collision, static route/canonical save issues, session history-clearing order and fresh precache update behavior were fixed. Test URL normalization, incognito install restrictions and worker readiness after a real navigation were corrected in the harness. An overlapping disposable-database run was discarded before the final sequential suites.

## Migration and retained data

Applied the migration to the retained local demonstration database. On ospm_test only, inspected the exact columns, two unique identifiers, six RESTRICT FKs and coordinate precision, then rolled back/remigrated and repeated the inspection successfully:

```powershell
php .qa/m12-schema.php
php artisan migrate:rollback --path=database/migrations/2026_10_08_200000_create_inspections_table.php --step=1 --force
php artisan migrate --force
php .qa/m12-schema.php
```

Main migrate --pretend reports nothing pending. Normal-action seeding/reset retains existing observations and all financial history. A final retained-data check verified **133 financial audit hashes** after synthetic browser/payment fixtures; inspections add only general enforcement audit events.

## Browser, camera and retry validation

Chrome/Playwright exercised Enforcement Officer at 1440px desktop and 390px mobile. All ten screens rendered, all ten mobile pages had no document overflow, and **22 final axe WCAG A/AA samples had zero violations**: twenty page samples, inspection confirmation and anonymous offline shell. Application JavaScript errors were zero. Search → quick view → confirmed inspection, safe manual ticket/receipt links, rejected hostile QR URLs and finance-route 403s passed. Home, verification and inspection mobile screenshots were visually inspected.

A normal persistent Chrome profile reported no manifest/installability errors and offered the installation prompt. Start/scope are /field/, the manifest uses the full product identity, icons parse correctly, the updated worker removes older field-shell caches and all five cached URLs are anonymous static assets. Offline loaded verification hides its current valid verdict; full reload shows only the anonymous shell. Saving is disabled offline and nothing is queued. Reconnection checks auth again. Logout emitted clearHistory after invalidation, and returning to encrypted field history required authentication without restoring the old verification.

The camera check used Chrome's real getUserMedia pipeline with a synthetic Y4M QR camera feed in a mobile viewport and the production ZXing decoder. It decoded a normal ticket QR, navigated to a scoped valid result and ended camera tracks. Simulated permission denial displayed manual fallback, which verified successfully. **This is desktop Chrome emulation with a fake media device, not physical Android/iOS camera testing.**

Two real MySQL worker processes submitting the same confirmation returned one retained inspection and one audit. A browser test allowed a POST to commit, discarded its success response, then retried the same form/confirmation; the same reference returned. A database check confirmed one observation/audit. No financial action was queued or triggered by enforcement.

Ignored QA scripts, profiles, logs, feeds and screenshots remain local validation artifacts, not application dependencies.

## Scope and release limits

One approved table and SCR-085–094 only. No SCR-095, inspection administration, incident/evidence/violation workflow, penalty, real payment/treasury integration or other later milestone feature was added. Optional persistent drafts/recent-record offline storage are not implemented. User authorization permits integration into develop and personal SSH push; runtime files, credentials and private keys are excluded from the staged patch.

Physical Android/iOS, actual OS installation and deployed HTTPS camera checks remain device/release validation. Camera/PWA capabilities vary by browser and secure context; APP_URL must be reachable from the scanning device. A phone cannot use the development machine's localhost. Existing SMTP/object-storage/license and unspecified government expiry-policy limitations remain. Production volume testing and the comprehensive security gate belong to Milestone 19.

Technical references: [ZXing browser](https://github.com/zxing-js/browser), [MDN installability](https://developer.mozilla.org/en-US/docs/Web/Progressive_web_apps/Guides/Making_PWAs_installable), [MDN service workers](https://developer.mozilla.org/en-US/docs/Web/API/Service_Worker_API/Using_Service_Workers), [MDN manifest scope](https://developer.mozilla.org/en-US/docs/Web/Progressive_web_apps/Manifest/Reference/scope).

Next incomplete milestone: **Milestone 13 — Incidents & Violations**.
