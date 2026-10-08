# Milestones 4–6 validation report

Validation completed: 2026-10-08 (Africa/Lagos). Implementation began 2026-10-07.

## Delivered scope

- Milestone 4, SCR-032–035: operator registration, approval/suspension, retained park/route approvals, private documents and own-operator access.
- Milestone 5, SCR-036–047: driver/vehicle registration, approval/suspension, expiry fields, private photographs/documents, assignment creation/ending and retained history.
- Milestone 6, SCR-048–055: revenue heads, decimal-safe fee configurations, dates, immutable effective terms and backend scope resolution.

Existing LGA/Park/Route profiles and dashboards now use scoped transport registrations. The four controlling documents are unchanged. No ticketing/payment/ledger/reconciliation/field workflow or later business table was added.

## Executed checks

| Check | Outcome |
|---|---|
| PHP syntax | 176 application/configuration/migration/test/entry files passed |
| Final full SQLite suite | 76 tests passed; exit code 0 |
| Final full MySQL 8 suite | 76 tests passed; exit code 0 |
| Earlier expanded full suites | 75 tests / 1,384 assertions passed independently on SQLite and MySQL |
| PHP formatting and lint | Pint and composer lint passed |
| TypeScript / ESLint / production Vite build | Passed |
| Main demo migration/seeding | Seven migrations passed; synthetic registry/revenue baseline installed |
| Migration rollback/remigration on disposable test database | All seven new migrations rolled back and remigrated successfully |
| Application routes | 89 named application routes; later workflows remain absent |
| Chrome workflow and accessibility smoke | Passed; 24 desktop/mobile samples, zero axe violations, zero JavaScript errors |
| MySQL simultaneous assignment requests | Passed; one created, one rejected; exactly one primary relationship persisted |
| Staged diff / controlling documents / secret exclusion | Passed; all four controlling documents unchanged; generated credentials/private keys excluded |

MySQL uses the existing isolated MySQL 8.4.11 instance at 127.0.0.1:3308. Full database tests use disposable ospm_test; browser smoke uses the retained local synthetic demo database. No production or genuine citizen data is used.

Final database commands: php vendor/phpunit/phpunit/phpunit --debug, first with the default SQLite test configuration, then with DB_CONNECTION=mysql and DB_DATABASE=ospm_test. XDEBUG_MODE=off was used for these final runs. Each log records 76 passed tests and shell exit code 0. The isolated MySQL service was restarted after an overnight stop before these final runs. Earlier slow, interrupted runs are not counted as passes.

Migration sanity commands: php artisan migrate:rollback --step=7 --force followed by php artisan migrate --force, both against ospm_test. Frontend checks: npm run typecheck, npm run lint and npm run build. PHP checks: source-file php -l, Pint and composer lint. Browser checks used the production assets through Laravel routes with Playwright and axe. Staged review included git diff --cached --check, exact controlling-document blob comparison and generated-secret exclusion.

## Acceptance evidence

The 23 added tests cover all 24 required screens and:

- Operator account isolation, scoped relationship lists, foreign LGA rejection, view/manage distinction, shared master write restrictions, collector/State/Auditor fee management restrictions and authorization inside domain Actions.
- Backend-generated references, normalized unique vehicle registration, approval/suspension and actor audit.
- Retained operator route IDs/creation times during removal/reactivation, and suspension with retained inactive park/route masters.
- Valid active driver/vehicle/operator/park/route combinations, rejected inactive or mismatched relationships, single primary assignment, permitted secondary assignments, explicit ending/reassignment and preserved actor/history.
- Private photo/document storage and previews, MIME/extension/category/size rejection, foreign profile denial and parent-bound download URLs.
- Server search/filter/pagination, scoped Park/LGA transport connections and actual registry dashboard counts.
- Idempotent synthetic seeding that preserves edits and ended assignments.
- All seven recommended fee precedence levels, route-only combinations, all-constraint matching, priority/latest effective start, conflict rejection, inclusive start/exclusive end, ignored future/draft/inactive/expired fees, inactive revenue heads and explicit no-match errors.
- Immutable effective fee terms, stale-model rejection, future fee edits, deactivation, scope/date/currency validation and exact decimal string normalization.

SQLite has no fixed-precision decimal storage type and can round extreme values. The maximum DECIMAL(15,2) amount is therefore checked against MySQL; SQLite uses a representative amount and the decimal-string unit test independently checks maximum precision.

## Browser and visual checks

Headless Chrome exercised the production build through real Laravel routes: five searchable lists and empty states, operator/driver/vehicle registration, operator route approvals, private photo upload, assignment creation/detail/ending/history, revenue head creation, future fee edits and effective fee deactivation through confirmation.

Own-operator login returned only its baseline operator and denied access to the browser-created foreign profile. Mobile pages were checked at 390 × 844 for horizontal overflow; desktop used 1440 × 1000. Driver document and mobile fee screenshots were inspected. Twenty-four axe WCAG A/AA samples passed; automated sampling is not a complete accessibility certification.

Browser-created records and the concurrency fixture are labelled synthetic QA data. Their ended assignments, inactive fee and audit history remain in the local demo database. No real charges or receipts were generated.

## Limits and next milestone

- Uploaded files use private local storage. S3/R2 deployment and adapters remain unconfigured.
- Registry approval is explicit; this milestone does not implement automatic expiry status jobs, legal adjudication or blacklist inference.
- Historical-ticket snapshot invariance must also be tested when tickets exist in Milestone 7. No current test claims to create or validate a ticket.
- The full presentation-scale seed and release security/performance gates remain their approved later milestones.
- Existing SMTP delivery and unspecified repository license metadata limitations remain.

Next approved build step: **Milestone 7 — Ticketing + QR**, subject to further user authorization.
