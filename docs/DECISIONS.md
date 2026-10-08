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


## ADR-010 — Reviewed ticket issuance, retained snapshots and safe verification

Date: 2026-10-08
Status: Implemented
Context: Milestone 7 covers SCR-056–060 and SCR-067. The approved Ticket schema provides immutable fee/context snapshots, nullable expiry and a unique verification token. Payment, receipt and ledger tables belong to Milestone 8.
Decision: Issue pending/unpaid tickets only from a current active DriverAssignment in managed park scope with `issue_ticket`. Revalidate driver, vehicle, approved operator, LGA, park, optional route and retained approvals. Resolve the fee on the backend. Bind the displayed review to the actor, assignment, resolved terms, minimal operating snapshot and random request key using an APP_KEY HMAC; recheck under row locks at issuance. Changed terms require another review. Use locking current reads for fee candidates, revenue head and relationship approvals, so MySQL REPEATABLE READ cannot reuse an earlier transaction snapshot after a concurrent change. Fee writes and issuance retry deadlocks up to three times. A driver lock serializes duplicate submissions of the same reviewed request; store its request key in the approved context_snapshot JSON and return the existing ticket. This is limited issuance replay protection, not Milestone 8 payment idempotency.
Decision: Snapshot amount/currency/code/name, original LGA/park/operator/driver/vehicle/route context, issuer and issue time. Prevent snapshot mutation and deletion through the model; restrictive foreign keys retain masters. Generate unique ULID-based references and cryptographically random 256-bit verification tokens, with database uniqueness and bounded collision retry. Audit issuance without tokens or private identity details; dispatch domain events after commit. Historical LGA scope uses the ticket's original LGA ID rather than following a later park move. Related tabs are independently ticket-scoped.
Decision: Cancellation requires `cancel_ticket`, manage scope, a reason and a pending ticket with unpaid/failed payment status. It changes ticket status only and retains audit, original terms and history. Supervisors receive cancellation grants; collectors and auditors do not. Keep cancellation reason in general audit because the approved schema has no cancellation columns. Pending payment, paid, reversed, expired and already cancelled states are rejected.
Decision: Use BaconQrCode's SVG backend and generate QR URLs from configured APP_URL. Public verification is token-only, rate limited and exposes an explicit safe allowlist: reference, vehicle plate, park, fee, amount/currency, dates and statuses. Remove account/navigation data from public Inertia props, including for signed-in viewers. Only paid ticket plus paid payment status with unexpired validity returns valid. Pending/unpaid is authentic but payment-required; cancelled, reversed, refunded and expired are not valid. Unknown and malformed tokens share a safe not-found response. Protected QR/detail/print and public verification responses disable caching and referrers.
Decision: Leave expires_at null unless OSPM_TICKET_EXPIRY_MINUTES is explicitly configured. No daily or other validity policy is inferred from a revenue head's frequency. Evaluate expiry immediately in lists/details/public verification; a daily idempotent command persists eligible pending/paid expirations with audit, without changing payment status or amounts. Cancelled/reversed status takes precedence. Demo seeding creates an explicitly synthetic unpaid ticket through the same action and preserves later edits/cancellations.
Reason: Keep displayed fees consistent with the issued obligation, prevent duplicate confirmation submissions, retain historical truth and provide useful verification without exposing private registry data or implying unpaid obligations are receipts.
Scope impact: Milestone 7 only. No payment provider, payment/receipt/ledger table, refund, settlement, collection total, camera scanner, offline PWA or live treasury integration is introduced. APP_URL must be reachable by a scanning device. XMLWriter is required for SVG QR rendering.

## ADR-011 — Atomic demo collection, retained finance and receipt verification

Date: 2026-10-08
Status: Implemented
Context: Milestone 8 covers SCR-061–066 and SCR-068–070. Scope §§21–25 and Architecture §§9–11 require provider independence, demo outcomes, canonical receipts, immutable accounting and idempotent successful payment recording. Scope §22 and Screen Inventory §37 also require controlled demo reversal; Milestone 11 owns the separate refund/adjustment workflow.

Decision: Use the approved five financial tables without additional columns or temporary schemas. Keep payment, receipt, ledger transaction and audit as separate entities. A provider contract and DTOs isolate the demo gateway; live providers and refund methods will be added with their actual workflows. Require demo mode, demo payment mode, demo provider and a non-production environment for every simulation. Amount/currency derive from the immutable ticket, with decimal strings throughout; client money, provider identity, status and actor fields are ignored.

Decision: Bind each 256-bit confirmation key to actor, operation and request hash. Reserve and complete the approved idempotency record inside the same database transaction as the business write. Retain these records, including their response linkage, after the 24-hour expires_at metadata; expiry does not permit financial replay. There is no purge command. Matching retries return the canonical payment; mismatched payloads/actors/operations fail. Database uniqueness and bounded retry handle simultaneous insertion. Lock ticket before payment, then reauthorize and read current state. Only an unexpired pending ticket with unpaid/failed payment status and a positive amount accepts a new attempt. One pending/successful attempt blocks competing attempts.

Decision: Verify gateway reference, provider reference, amount, currency and outcome before success. Update payment and ticket, create exactly one credit and canonical receipt, append both audits and dispatch the domain event after commit. Any failure rolls back the entire operation. Receipt payment_id uniqueness and deterministic unique credit reference prevent duplicate financial artifacts. Failed/pending attempts create no receipt or credit. Pending resolution updates the existing attempt using a new idempotent operation. Failed attempts remain history and a fresh attempt is allowed.

Decision: Restrict reversal to reverse_transaction plus manage scope, require a reason and confirmation, and recheck under locks. Retain the successful credit and original receipt; append a debit linked to that credit, update current payment/ticket state and append audit. Replayed reversal confirmation returns the same payment. No financial row has a delete route. Model guards reject financial deletion, receipt/ledger/audit updates and changes to original payment terms; restrictive foreign keys retain referenced history. Database accounts and backups still need deployment controls because application guards cannot prevent direct privileged SQL.

Decision: Canonicalize financial audit payloads recursively and chain SHA-256 hashes. Serialize appends by locking the first retained users row, including when the chain is empty, then use a locking current read for the previous event. This uses the existing approved schema and avoids an empty-chain race; it imposes a short global serialization point. Financial audit writes require the enclosing business transaction. This provides tamper detection when a trusted chain/checkpoint is available, not protection against an administrator rewriting the entire database. Two simultaneous payments on separate tickets are checked against the complete chain on MySQL.

Decision: Scope payment/receipt/ledger queries by the ticket's original geography and explicit operator relationship. Action permission and manage scope are independently required for collection/reversal. Ledger is read-only. Give permitted payment viewers receipt access, and ledger access only to the approved statewide/LGA finance readership. Public verification uses a safe allowlist with no identity details, internal IDs, tokens, account or audit payload. Unknown/malformed tokens share a 404 response and verification is rate limited. A receipt proves a successful recorded payment even after ticket expiry; reversal invalidates its current verification without changing its historical terms. Ticket verification separately evaluates validity for use.

Decision: Generate protected PDFs on demand with Dompdf, server-side escaped Blade, local fonts, remote resources/PHP/JavaScript disabled and no persistent rendered_file_path mutation. Keep HTML print and PDF visibly marked DEMO / No real funds charged. Use safe public QR URLs from APP_URL; disable response caching/referrers and audit protected receipt access. No external rendering service or Node production process is introduced.

Decision: Seed three synthetic outcomes through normal actions. Repeated seeding and reset preserve tickets, payments, receipts, credits, reversals, keys and audit history, including a reversed baseline payment. Financial dashboard cards remain explicitly unavailable until Milestone 9 aggregation is implemented.

Scope impact: Milestone 8 only. No live collection, treasury integration, revenue dashboards, settlements, reconciliation, refund approvals, adjustments, scanner/PWA or later reports are implemented. The four controlling v1.0 documents remain unchanged.

## ADR-012 — Scoped ledger revenue, consistent dates and read-only dashboards

Date: 2026-10-08
Status: Implemented
Context: Milestone 9 covers SCR-007–011. Scope §§26–28 and the milestone acceptance require genuine financial totals, scoped filters and reconciliation with detail queries. Settlement/reconciliation tables and workflows belong to Milestone 10; current records are synthetic demo collections.

Decision: Share RevenueDashboardQuery and LedgerAggregateQuery between all five dashboards. Compute gross credits, debits, net credits-minus-debits and ledger entry count from immutable financial_transactions, using occurred_at. Never infer collections from ticket amount or mutable payment status. Reversals preserve original credits in the original period and create debits in the reversal period, which may produce negative revenue. Failed/pending attempts create no ledger revenue. Payment status/channel distributions separately count attempts by initiated_at and show their current status; this is explicitly labelled and is not a historical status snapshot. Phase 1 summary currency is NGN; detail links include that currency filter.

Decision: Aggregate on the database with MySQL DECIMAL SUM. Use Brick Math decimal strings for net subtraction and formatting; declare the already-installed dependency directly, without changing its locked version. SQLite's native SUM uses floating point, so register a decimal-string aggregate on that connection for fast tests. SQLite still has the existing large-value column-affinity limitation; the maximum-precision aggregate test runs on MySQL. React receives authoritative decimal strings. Recharts converts those strings to numbers for visual coordinates only; exact tables/tooltips retain the strings and no revenue arithmetic is duplicated in React.

Decision: Default financial dates to month-to-date in configured display timezone, initially Africa/Lagos. Allow at most 366 calendar days. Treat ranges as inclusive local start and exclusive next-day end, converted to UTC. Use PHP timezone transitions to generate daily database buckets, avoiding reliance on MySQL timezone tables and preserving dates if the configured timezone observes daylight saving. Today/month-to-date cards have explicit named periods with the same dimension filters; operational registry counts are current and independent of financial filters. Payments/Ledger drilldowns carry identical date, timezone, currency and dimension values; direct list requests preserve their previous UTC default. Validate timezone against UTC or the configured timezone.

Decision: Apply permission and historical ticket scope before every aggregate, group and recent-row query. Keep ticket LGA/park membership after later registry moves/archives; grouping joins retain archived master records and display current retained names. Filter inputs intersect authorized records and never broaden scope. Local dashboard identity is fixed by its URL. Financial read queries share a database transaction for consistent financial summaries. No cache or aggregate table is introduced, so committed demo payment/reversal changes appear on refresh.

Decision: Add explicit Executive/Revenue dashboard permissions and routes. Executive, State, Finance and Auditor defaults can view the executive summary; Revenue Officer uses State/Revenue views. Executive accounts land on their own dashboard using permission-based routing. Local revenue requires its own LGA/Park revenue permission as well as profile visibility. A general park viewer gets operational counts without financial props. Park Managers receive the approved revenue summary and payment history, without ledger detail URLs or ledger permission. Dashboard GETs do not change financial records. Private dashboard responses disable caching/referrers. Scoped general-audit readers see only their own recent activity, preventing statewide activity leakage through a dashboard.

Decision: Provide the six specified financial charts, keyboard navigation, exact figures, permission-aware detail links and six recent ledger entries. Limit distribution plots to ten groups while exact figures retain every group. Empty periods are actual zero states; missing financial permission is restricted, not zero. Pending reconciliation and exception measures are unavailable, rather than fabricated zero counts. No treasury settlement is implied. Charts use compiled Recharts assets and require no Node production service.

Scope impact: Milestone 9 only. No database schema change, live provider, settlement, reconciliation, refund/adjustment workflow, report/export, enforcement or other later milestone is added. The four controlling v1.0 documents remain unchanged.


## ADR-013 — Retained demo settlements and explainable reconciliation snapshots

Date: 2026-10-08
Status: Implemented
Context: Milestone 10 covers SCR-071–078 and the exact four tables in Database Schema §10. A successful payment is distinct from provider settlement; resolutions must retain discrepancy evidence and audit the reviewing actor.

Decision: Create synthetic demo-provider batches only from positive successful, unreversed NGN payment credits with matching ticket/payment/ledger lineage and no prior batch. Store server-calculated provider gross, zero synthetic fees and corresponding net; leave government account reference unset. The approved mismatch scenario reduces the first provider item by NGN 0.01 without altering ticket/payment/ledger amounts. Each batch and its items are immutable and undeletable through application models. Business actions lock tickets, payments and ledger sources in the existing payment order; locking current reads check prior batches even after a concurrent transaction commits. Actor/operation/payload-bound confirmations reuse the approved idempotency_keys table and remain replay-safe after its expiry timestamp.

Decision: A run uses inclusive configured-local dates converted to an exclusive UTC end, maximum 366 days, optional LGA/park and provider. Match one obligation per issued, non-cancelled NGN ticket in the period, plus prior tickets whose received payment or reversal occurs in it. Consider received successful/reversed/refunded payment history before the period end, rather than pending/failed attempts. A provider-specific run excludes tickets paid through another provider. Expected is retained ticket gross; actual is recorded settled NGN provider gross, zero when absent; difference is expected minus actual. Reversals remain exceptions for finance review rather than inventing settlement debits or refund policy. Orphan ledger entries are separate zero-obligation source findings, never substitutes for their ticket obligations.

Decision: Check ticket/payment/credit/provider-batch amounts and currency, provider references, lineage, multiple credits/payments/batches, missing payments/ledger/settlements, reversals and unknown sources. Duplicate references are provider-qualified and detected across scope; evidence does not disclose another ticket/payment. Where multiple checks fail, retain all flags and source amount/reference/status evidence in approved run.summary JSON. Primary exception priority is missing ticket, reversal, duplicate provider reference, missing payment, missing ledger, amount mismatch, missing settlement, unknown. Normal payment FKs prevent missing-ticket creation; defensive matching supports invalid legacy imports without relaxing schema constraints.

Decision: Process inside one database transaction with the run row locked, using the database's consistent read snapshot. Findings, totals and completion audit commit together. Repeat delivery returns a completed run unchanged. A new request creates a new snapshot and leaves earlier evidence intact. The queue-capable job runs synchronously for small demo presentation datasets and uses the configured database queue outside demo; failed jobs retain a safe failure status with no partial findings. Database workers are required for asynchronous execution. Production Redis/Horizon and external provider settlement ingestion remain future work.

Decision: Only finance/super administrators receive write grants by default. Auditors remain read-only, consistent with earlier ADRs. State/Executive/Revenue and LGA administrators receive reconciliation reads; local scope follows original ticket LGA/park/operator, fails closed for orphan records and recomputes every total and displayed run outcome from accessible items. Settlement batches require statewide action/read permission, so mixed batch totals never reach scoped LGA accounts. Scoped run filtering does not expose global exception status. Safe page DTOs exclude raw metadata, confirmation keys, other scopes' evidence and private registry data.

Decision: Overview totals use the latest finding per obligation/source across completed snapshots, preventing duplicate totals after reruns. Dashboard pending counts selected-period credits without a latest matched or manually reconciled ticket finding; ledger trace links show that latest ticket finding. The reconciliation overview has its own scope/type/status filters rather than inheriting dashboard channel/date selections. Financial pages use private no-store responses. Monetary arithmetic uses BigDecimal strings; MySQL validates production DECIMAL precision.

Decision: Review moves an open exception to under_review or reconciled with a required reason, actor and timestamp. Lock and revalidate the current item; bind replay keys to item/outcome/reason/actor. Preserve all original amounts, exception type and evidence. Every transition appends financial hash-chain and general audit events; the item stores the latest review and prior notes remain in audit. A reviewed outcome does not adjust funds, fix source amounts or imply a real treasury settlement. Extend financial audit subjects generically while preserving prior payment event/hash format.

Scope impact: Milestone 10 only, with navigation and M9 dashboard/ledger integration. No refund approval, financial adjustment, ledger correction, live provider, treasury allocation/account policy, report/export or other Milestone 11+ workflow is introduced. Controlling v1.0 documents are unchanged. Synthetic seeding and demo reset preserve settlements, runs, reviewed exceptions and audit history.
