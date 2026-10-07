# CODEX MASTER BUILD PROMPT
## Osun State Park Management System — Phase 1 Development Kickoff

You are the lead software engineer responsible for starting the **Osun State Park Management System (OSPM)** digital platform for **Pinnacle Tech Hub**.

Your job is to build the project from the approved development documents without drifting from scope, architecture, naming, data model, workflows, or user experience requirements.

This is not a generic dashboard project and it is not a throwaway prototype. It is a **government-facing demonstration system built on a pilot-ready foundation**.

---

# 1. FIRST ACTION — READ THE PROJECT DOCUMENTS

Before writing code, read these four documents completely:

1. `docs/PROJECT_SCOPE_v1.0.md`
2. `docs/DATABASE_SCHEMA_v1.0.md`
3. `docs/SCREEN_INVENTORY_v1.0.md`
4. `docs/ARCHITECTURE_IMPLEMENTATION_PLAN_v1.0.md`

If the repository currently contains the documents under these generated filenames, use them as their equivalents:

- `Osun_State_Park_Management_System_Project_Scope_v1.0.md`
- `OSPM_Database_Schema_v1.0.md`
- `OSPM_Screen_Page_Inventory_v1.0.md`
- `OSPM_Laravel_Architecture_Implementation_Plan_v1.0.md`

Do not start implementation until you understand the four documents.

Create `/docs` if necessary and preserve copies of the controlling documents there using these canonical names:

```text
docs/
├── PROJECT_SCOPE_v1.0.md
├── DATABASE_SCHEMA_v1.0.md
├── SCREEN_INVENTORY_v1.0.md
└── ARCHITECTURE_IMPLEMENTATION_PLAN_v1.0.md
```

These documents are the **single source of truth**.

---

# 2. DOCUMENT PRECEDENCE

If you find an apparent conflict, use this precedence:

1. **PROJECT_SCOPE_v1.0.md** — defines what is in/out of scope.
2. **DATABASE_SCHEMA_v1.0.md** — defines persistent data structure and financial integrity.
3. **SCREEN_INVENTORY_v1.0.md** — defines required screens/routes and UX coverage.
4. **ARCHITECTURE_IMPLEMENTATION_PLAN_v1.0.md** — defines code structure and implementation sequence.

Do not silently choose an interpretation when two controlling documents materially conflict.

Instead:

1. stop that specific implementation;
2. record the conflict in `docs/DECISIONS_REQUIRED.md`;
3. continue only with unaffected work.

Do not invent new business rules.

---

# 3. PROJECT NAME — DO NOT RENAME

The public-facing project name is:

# OSUN STATE PARK MANAGEMENT SYSTEM

Do not rename the system to:

- Integrated Transport Management System
- Smart Mobility Platform
- Transport Revenue Platform
- Integrated Park & Transport System
- or any other invented government programme name.

Internal technical modules may use descriptive names, but the product identity must remain **Osun State Park Management System**.

---

# 4. TECHNOLOGY PROVIDER

The solution is being developed by:

**Pinnacle Tech Hub**

Pinnacle Tech Hub is the technology solution provider.

The UI may contain a subtle line such as:

> Technology Solution by Pinnacle Tech Hub

Do not make Pinnacle branding visually compete with the government system identity.

Do not imply that Pinnacle Tech Hub is the Government, revenue owner, regulator, or statutory authority.

---

# 5. PRIMARY PRODUCT PRINCIPLE

The complete operational chain is:

```text
LGA
→ Park
→ Operator
→ Vehicle
→ Driver
→ Route
→ Ticket
→ Payment
→ Receipt
→ Financial Ledger
→ Reconciliation
→ Audit
```

The system must never become a collection of disconnected dashboards.

Every important object and transaction must participate in the relationships defined by the approved documents.

Core financial principle:

> Every authorised revenue transaction must be identifiable, traceable and auditable.

---

# 6. APPROVED TECHNOLOGY STACK

Use the approved stack only.

## Backend

- Laravel
- Modular monolith architecture
- Laravel Eloquent
- Laravel Policies
- Laravel Form Requests
- Laravel Events/Listeners where appropriate
- Laravel Jobs/Queues
- Laravel Scheduler
- Laravel Sanctum where API token access is required

## Authorization / Audit

- `spatie/laravel-permission`
- `spatie/laravel-activitylog`
- custom append-only financial audit log as specified in the schema

## Frontend

- React
- TypeScript
- Inertia.js
- Tailwind CSS
- Recharts for dashboard charts

## Demo Infrastructure

- MySQL 8
- Database queue
- Laravel cron/scheduler
- Shared hosting compatible
- Cloudflare-compatible deployment
- Cloudflare R2 / S3-compatible storage where configured

## Production Target

Do not implement production infrastructure now, but keep compatibility with:

- PostgreSQL
- Redis
- Laravel Horizon
- Nginx + PHP-FPM
- VPS / Cloud
- object storage
- Cloudflare WAF/CDN

Do not introduce:

- microservices
- Kubernetes
- Next.js
- Vue
- a second backend framework
- a separate Node production server
- Flutter in Phase 1
- WebSockets unless a controlling document is revised to require them

---

# 7. DEVELOPMENT ARCHITECTURE

Use a Laravel modular monolith.

The intended business domains are:

```text
Identity
Geography
Parks
Routes
Operators
Drivers
Vehicles
Assignments
Revenue
Ticketing
Payments
Finance
Reconciliation
Enforcement
Incidents
Complaints
Reporting
Audit
System
```

Use the detailed structure from `ARCHITECTURE_IMPLEMENTATION_PLAN_v1.0.md`.

The target high-level structure is:

```text
app/
├── Domains/
│   ├── Identity/
│   ├── Geography/
│   ├── Parks/
│   ├── Routes/
│   ├── Operators/
│   ├── Drivers/
│   ├── Vehicles/
│   ├── Assignments/
│   ├── Revenue/
│   ├── Ticketing/
│   ├── Payments/
│   ├── Finance/
│   ├── Reconciliation/
│   ├── Enforcement/
│   ├── Incidents/
│   ├── Complaints/
│   ├── Reporting/
│   ├── Audit/
│   └── System/
│
├── Http/
│   ├── Controllers/
│   │   ├── Web/
│   │   ├── Api/
│   │   ├── Public/
│   │   └── Field/
│   ├── Middleware/
│   └── Requests/
│
├── Jobs/
├── Listeners/
├── Notifications/
├── Providers/
└── Support/
```

Do not collapse all business logic into controllers.

Do not create architecture layers merely for ceremony.

Use:

- Models for persistence/relationships
- Form Requests for input validation
- Policies for authorization
- Actions for transactional use-cases
- Services for reusable domain rules
- Query objects for complex filtering/reporting
- Events/Listeners for secondary side effects
- Jobs for asynchronous/background work

---

# 8. BUSINESS LOGIC OWNERSHIP

Laravel is authoritative.

React is not allowed to independently determine:

- ticket amount
- applicable fee configuration
- whether a ticket is valid
- whether a user is authorized
- whether a payment transition is valid
- whether a refund is allowed
- whether a transaction is reconciled
- revenue allocation rules
- financial ledger effects

React handles presentation and local UI interaction.

All critical validation must also run server-side.

---

# 9. DATABASE RULES

Implement the schema from `DATABASE_SCHEMA_v1.0.md`.

Important rules:

- internal PK: BIGINT
- business-facing records: ULID/public ID where specified
- money: `DECIMAL(15,2)`, never float
- currency: `CHAR(3)`, initially NGN
- statuses: VARCHAR + PHP backed enums, not MySQL ENUM
- timestamps stored consistently
- JSON only for metadata/snapshots
- use migrations for every schema change
- do not manually alter the database outside migrations

## Financial history is immutable

Never update historical ticket amount because current fees change.

Never delete financial ledger entries.

Refunds, reversals and adjustments create new financial records.

A payment must remain distinct from a ticket.

A receipt is generated only for a successful payment.

A successful payment must be idempotent.

Reconciliation is a distinct process from payment success.

---

# 10. AUTHORIZATION RULES

Use Spatie Permission plus Laravel Policies and access scopes.

Authorization is not achieved by hiding sidebar items.

At minimum enforce:

- LGA admin cannot access another LGA without explicit scope.
- Park manager cannot manage another park.
- Operator account cannot access another operator's private records.
- Ticketing/collection roles cannot perform finance approvals unless permission is explicitly granted.
- Auditor is read-only by default.
- Public verification exposes only safe fields.
- Super Admin activity is still audited.

Implement `UserAccessScopeService` as defined in the architecture document.

List/query classes must apply user scope server-side.

---

# 11. UI / UX DESIGN MANDATE

The UI must look like a **serious, professional government operations and revenue-management system**.

It must not look like:

- a startup landing page
- a crypto dashboard
- a gaming dashboard
- a consumer fintech app
- a template filled with random gradients
- an over-animated SaaS demo
- a futuristic AI interface

## Desired visual character

The interface should communicate:

- government authority
- trust
- accountability
- order
- operational clarity
- financial seriousness
- accessibility
- professionalism

Use a restrained, institutional visual language.

---

# 12. GOVERNMENT DASHBOARD DESIGN SYSTEM

Create a reusable design system through Tailwind tokens/components.

Until official Osun State Government brand assets are supplied, use a **neutral government-safe demo identity** rather than inventing an official State brand.

Recommended demo palette:

```text
Primary / Header:
Deep Navy / Ink

Secondary:
Government Blue

Success:
Restrained Green

Warning:
Amber

Danger:
Muted Red

Background:
Very Light Gray / Off White

Cards:
White

Borders:
Cool Gray

Text:
Dark Slate
```

Important:

- all primary brand values must be configurable;
- do not claim these are official Osun Government colours;
- do not fabricate a government coat of arms/logo;
- provide a branding setting so official assets can replace demo branding later.

Use CSS/Tailwind design tokens rather than scattering hex values.

Example conceptual tokens:

```text
--gov-primary
--gov-primary-dark
--gov-secondary
--gov-surface
--gov-background
--gov-border
--gov-text
--gov-muted
--status-success
--status-warning
--status-danger
```

---

# 13. TYPOGRAPHY

Use a clean, highly readable interface font available legally through the project setup.

Preferred characteristics:

- professional
- neutral
- excellent numeric readability
- strong hierarchy
- readable at small dashboard sizes

Use consistent scale:

```text
Page title
Section title
Card metric
Body
Label
Caption
Table text
```

Avoid oversized marketing typography inside authenticated dashboards.

---

# 14. ADMINISTRATIVE LAYOUT

Authenticated desktop layout:

```text
┌────────────────────────────────────────────────────────────┐
│ Government System Header / Search / Notifications / User   │
├────────────────┬───────────────────────────────────────────┤
│ Sidebar        │ Breadcrumb                               │
│                │ Page Title                Page Actions    │
│ Dashboard      │                                           │
│ Operations     │ KPI / Summary Cards                       │
│ Ticketing      │                                           │
│ Finance        │ Filters                                   │
│ Enforcement    │                                           │
│ Support        │ Tables / Charts / Detail Content           │
│ Reporting      │                                           │
│ Administration │                                           │
└────────────────┴───────────────────────────────────────────┘
```

Characteristics:

- persistent sidebar on desktop
- compact responsive navigation on tablet/mobile
- clear breadcrumbs
- consistent page titles
- no unnecessary full-screen hero sections
- no huge decorative illustrations in admin screens
- data first

---

# 15. DASHBOARD DESIGN

The State Dashboard should immediately communicate operational status.

Priority metrics may include:

```text
Today's Revenue
Today's Transactions
Active Parks
Registered Operators
Registered Vehicles
Registered Drivers
Successful Payments
Failed Payments
Pending Reconciliation
```

Then show:

```text
Revenue Trend
Revenue by LGA
Revenue by Park
Payment Status
Recent Exceptions
Recent Activity
```

Dashboard rules:

- use real backend-derived values
- keep chart count restrained
- every chart must answer a useful operational question
- no fake percentage growth unless derived from valid comparison periods
- no decorative charts
- format Naira consistently
- show selected date range
- show data scope where relevant

---

# 16. GOVERNMENT TABLE STANDARD

High-volume pages must use a consistent professional table pattern.

Required:

- server-side search
- server-side filtering
- pagination
- clear column headers
- status badges
- restrained row actions
- empty state
- loading state
- error state
- responsive behavior

Do not hide critical actions inside confusing icon-only menus.

Use action labels/tooltips where needed.

---

# 17. STATUS SYSTEM

Use consistent semantic treatment across the application:

```text
Successful / Paid / Active / Reconciled
→ success treatment

Pending / Under Review
→ warning treatment

Failed / Suspended / Exception
→ danger treatment

Inactive / Expired / Cancelled
→ neutral treatment

Draft / Informational
→ information treatment
```

Never rely on colour alone to communicate status.

Always include text/icon/state label.

---

# 18. ACCESSIBILITY

Implement a professional accessible baseline:

- semantic labels
- keyboard-accessible controls
- visible focus states
- sufficient contrast
- accessible validation errors
- descriptive button names
- table headings
- sensible tab order
- scalable/responsive layout
- status labels not colour-only

Do not sacrifice readability for aesthetic minimalism.

---

# 19. RESPONSIVE STRATEGY

## Desktop

Primary administration experience.

## Tablet

Full operational usability.

## Mobile

Admin screens remain usable, but complex tables may use stacked/cards where appropriate.

## Enforcement PWA

Mobile-first.

Field screens must prioritize:

- large scan button
- large search controls
- clear VALID / INVALID result
- minimal typing
- camera usability
- fast navigation

---

# 20. FRONTEND STRUCTURE

Use the React structure from `ARCHITECTURE_IMPLEMENTATION_PLAN_v1.0.md`:

```text
resources/js/
├── Components/
├── Layouts/
├── Pages/
├── hooks/
├── lib/
├── types/
└── pwa/
```

Required layouts:

```text
AppLayout
AuthLayout
PublicLayout
FieldLayout
```

Build reusable components first:

```text
Sidebar
Topbar
Breadcrumbs
PageHeader
StatCard
DataTable
Pagination
FilterBar
SearchInput
StatusBadge
EmptyState
LoadingState
ErrorState
ConfirmDialog
Modal
Drawer
FormField
DateRangePicker
MoneyDisplay
ReferenceDisplay
QRDisplay
DocumentUploader
ActivityTimeline
AuditTimeline
PermissionGuard
ScopeGuard
Toast
```

Avoid one-off UI patterns when a shared component is appropriate.

---

# 21. ROUTING

Use the route groups and naming conventions defined in the architecture plan.

Expected route families:

```text
/admin/*
/lgas/*
/parks/*
/routes/*
/operators/*
/drivers/*
/vehicles/*
/assignments/*

/revenue-heads/*
/fee-configurations/*
/tickets/*
/payments/*
/receipts/*

/finance/*
/inspections/*
/violations/*
/incidents/*
/complaints/*
/notifications/*
/reports/*
/audit/*
/settings/*

/field/*
/verify/*
/public/*
```

Do not invent new route families without a scope requirement.

---

# 22. SCREEN INVENTORY

`SCREEN_INVENTORY_v1.0.md` defines the complete Phase 1 page baseline.

The inventory contains the expected screen IDs and routes.

When implementing a page:

- include the screen ID in the development task/PR notes;
- keep the specified purpose;
- do not silently create duplicate pages for the same workflow;
- reuse generic report/list/detail infrastructure where prescribed.

The existence of many listed screens does not mean every screen needs unique component code.

Use reusable components and shared patterns.

---

# 23. DEMO MODE

Create explicit demo configuration:

```text
OSPM_DEMO_MODE=true
PAYMENT_MODE=demo
PAYMENT_PROVIDER=demo
```

Add:

```text
config/ospm.php
```

Demo mode supports:

- synthetic data
- simulated payment statuses
- known presentation users
- deterministic presentation workflow
- demo reset command

Create:

```text
php artisan ospm:demo-reset
```

The command must refuse to run if demo mode is false.

Do not put a publicly available "reset database" button in the application.

---

# 24. DEMO PAYMENT GATEWAY

Implement provider abstraction first.

Phase 1 working gateway:

```text
DemoPaymentGateway
```

It must support:

- successful payment
- failed payment
- pending payment

Reversal must occur through an authorized finance/supervisor workflow, not a collector shortcut.

Payment architecture must remain replaceable later with:

- Government gateway
- Bank gateway
- Paystack
- Flutterwave

Do not tightly couple ticketing to a specific provider.

---

# 25. CRITICAL TICKETING WORKFLOW

Implement this exact flow:

```text
1. User searches for vehicle / driver.
2. Backend confirms active assignment.
3. User selects approved revenue head.
4. Backend resolves applicable fee.
5. UI displays authoritative fee.
6. User confirms.
7. Backend creates ticket.
8. Ticket snapshots fee code/name/amount.
9. Secure verification token is generated.
10. QR is generated from safe public verification URL.
11. User proceeds to payment.
```

No valid paid ticket should be fabricated entirely on the frontend.

---

# 26. CRITICAL PAYMENT WORKFLOW

Successful payment processing must:

```text
1. verify authorization / gateway result
2. ensure idempotency
3. mark payment successful
4. mark ticket paid
5. create one immutable ledger credit
6. generate one canonical receipt
7. create financial audit event
8. dispatch secondary event/notification
```

Use a database transaction.

Repeated success processing must not duplicate:

- ledger
- receipt
- state transition

---

# 27. CRITICAL RECONCILIATION WORKFLOW

Support:

```text
Matched
Payment without ticket
Ticket without payment
Duplicate provider reference
Amount mismatch
Missing ledger entry
Missing settlement
Reversal exception
```

The reconciliation result must be explainable.

Resolving an exception requires:

- authorized user
- reason/note
- timestamp
- audit event

---

# 28. AUDIT REQUIREMENTS

Use:

- Spatie Activitylog for general application activity
- custom `financial_audit_logs` for high-value financial events

Financial audit log must be read-only in the application.

Implement hash chaining as defined in the schema where practical:

```text
entry_hash = hash(previous_hash + normalized payload)
```

Never log:

- passwords
- API secrets
- raw access tokens
- sensitive identity document contents

---

# 29. FILE STORAGE

Use Laravel Filesystem abstraction.

Validate:

- MIME
- extension
- file size
- authorization

Generate safe stored filenames.

Use media metadata table defined in schema.

Do not trust client filenames.

Do not make sensitive evidence publicly enumerable.

---

# 30. REPORTING

Build report infrastructure, not disconnected report pages.

Phase 1 reports include:

- daily revenue
- monthly revenue
- revenue by park
- revenue by LGA
- transactions
- reconciliation
- vehicles
- drivers
- operators
- incidents
- complaints

Exports:

- CSV
- Excel
- PDF

Large export architecture must be queue-ready.

---

# 31. PHASE 1 OUT OF SCOPE

Do not add any of the following:

- microservices
- Kubernetes
- native Android app
- native iOS app
- Flutter app
- live Government treasury integration
- live Government revenue collection
- biometrics
- facial recognition
- ANPR
- CCTV analytics
- GPS fleet tracking
- passenger booking
- ride-hailing
- seat reservation
- driver navigation
- vehicle IoT
- smart physical gates
- NFC
- automatic barriers
- AI fraud detection
- ML forecasting
- blockchain
- cryptocurrency
- digital wallet
- loans
- insurance marketplace
- payroll
- HR
- procurement
- NURTW internal administration
- union membership administration

Do not propose these while implementing Phase 1.

---

# 32. BUILD ORDER — DO NOT SKIP AHEAD

Follow the implementation milestones in `ARCHITECTURE_IMPLEMENTATION_PLAN_v1.0.md`.

For this Codex run, begin with:

# MILESTONE 0 — PROJECT BOOTSTRAP

Then:

# MILESTONE 1 — AUTHENTICATION + RBAC

Then:

# MILESTONE 2 — CORE APPLICATION SHELL + STATE DASHBOARD SKELETON

Do not continue into Park, Driver, Ticketing, Payment, Finance, or later milestones until Milestones 0–2 are stable.

---

# 33. MILESTONE 0 — REQUIRED IMPLEMENTATION

Create/configure:

- Laravel application
- React
- TypeScript
- Inertia.js
- Tailwind CSS
- MySQL-ready environment
- standard Laravel repository structure
- domain directory skeleton
- base layouts
- error page foundation
- lint/test skeleton
- `.env.example`
- `config/ospm.php`
- `/docs` controlling documents
- README setup instructions

Confirm:

- Laravel boots
- Inertia renders React
- TypeScript compiles
- `npm run build` succeeds
- database configuration is ready
- no production secret exists in repository

---

# 34. MILESTONE 1 — REQUIRED IMPLEMENTATION

Implement:

- authentication
- login/logout
- forgot/reset password
- active user status
- Spatie Permission
- Spatie Activitylog
- roles
- permissions
- user scope tables
- login activity
- `UserAccessScopeService`
- base policies/authorization approach
- user admin pages required for this milestone

Seed the Phase 1 roles:

```text
Super Administrator
State Administrator
Executive Viewer
Finance Administrator
Revenue Officer
Auditor
LGA Administrator
Park Manager
Ticketing Officer
Collection Agent
Enforcement Officer
Help Desk Officer
Transport Operator
```

Use the permission list/structure from the project documents.

Do not hardcode role checks across the app.

---

# 35. MILESTONE 2 — REQUIRED IMPLEMENTATION

Build the professional application shell:

- `AppLayout`
- `AuthLayout`
- `PublicLayout`
- `FieldLayout`
- sidebar
- topbar
- breadcrumbs
- page header
- reusable stat cards
- reusable data table
- reusable status badge
- filter/search controls
- pagination
- empty/loading/error states
- notification placeholder
- responsive navigation
- State Dashboard skeleton

The dashboard skeleton should be visually complete enough to establish the final design language, even though later modules will provide richer real data.

Do not fake complex business metrics.

Where data does not exist yet, use clearly identified demo seed values or empty/zero states driven by the backend.

---

# 36. UI QUALITY BAR FOR MILESTONE 2

The dashboard should look ready to show to:

- Honourable members
- Ministry officials
- Government executives
- revenue officers
- technical stakeholders

Aim for the visual quality of a mature public-sector financial/operations system.

Required qualities:

- balanced whitespace
- compact but readable data density
- strong header hierarchy
- refined card borders/shadows
- consistent 8px-style spacing rhythm
- restrained rounded corners
- professional icons
- clear active navigation state
- polished form controls
- deliberate empty states
- no visual clutter
- no random colour gradients
- no cartoon illustrations
- no unnecessary glassmorphism
- no excessive animations

Animations, if any, should be subtle and functional.

---

# 37. INITIAL SIDEBAR STRUCTURE

Use the planned navigation hierarchy:

```text
Dashboard

OPERATIONS
  LGAs
  Parks
  Routes
  Operators
  Drivers
  Vehicles
  Assignments

TICKETING
  Tickets
  Payments
  Receipts

FINANCE
  Revenue Dashboard
  Revenue Heads
  Fee Configurations
  Ledger
  Settlements
  Reconciliation
  Refunds
  Adjustments

ENFORCEMENT
  Field App
  Inspections
  Violations
  Incidents

SUPPORT
  Complaints
  Notifications

REPORTING
  Reports
  Audit Logs

ADMINISTRATION
  Users
  Roles & Permissions
  Settings
```

For unfinished modules:

- the sidebar may hide them until implemented, or
- show them disabled only in a dedicated development environment.

Do not create fake working pages that bypass the build sequence.

---

# 38. DATABASE MIGRATION DISCIPLINE

During Milestones 0–2, implement only schema required for:

- users/auth
- roles/permissions
- user scopes
- login activity
- framework tables
- any minimal configuration needed by the shell

Do not prematurely create all financial tables in the first pass unless the architecture/setup requires migration ordering scaffolding.

As each milestone begins, implement its approved schema.

---

# 39. TESTING DURING MILESTONES 0–2

At minimum write tests for:

```text
user can log in
inactive user cannot log in
authorized user reaches dashboard
unauthenticated user is redirected
role permissions can be assigned
LGA/Park/Operator access scopes persist
super admin can manage users
non-authorized role cannot manage users
```

Do not postpone all testing until the end.

---

# 40. CODE QUALITY RULES

Use:

- strict, clear naming
- small controllers
- reusable domain actions
- typed PHP where practical
- PHP enums
- TypeScript types
- explicit validation
- policy authorization
- database transactions for multi-write critical operations

Avoid:

- giant controllers
- giant React components
- duplicated query logic
- magic numbers
- status strings scattered everywhere
- financial calculations in JavaScript
- raw SQL without a reason
- N+1 query patterns
- unnecessary abstractions
- packages installed "just in case"

---

# 41. README

Create a professional repository README including:

- project overview
- scope statement
- technology stack
- controlling documents
- local installation
- environment setup
- database setup
- frontend build
- migrations
- seeding
- tests
- queue
- scheduler
- demo mode
- deployment notes
- security warning
- Phase 1 boundaries

---

# 42. DECISION LOG

Create:

```text
docs/DECISIONS.md
```

Use it only for actual implementation decisions that do not change product scope.

Format:

```text
## ADR-001 — Decision title

Date:
Status:
Context:
Decision:
Reason:
Scope impact:
```

If a decision changes project scope, do not silently record it as an ADR. It requires an approved scope revision.

---

# 43. PROGRESS TRACKER

Create:

```text
docs/IMPLEMENTATION_STATUS.md
```

Track milestones:

```text
[ ] Milestone 0 — Project Bootstrap
[ ] Milestone 1 — Authentication + RBAC
[ ] Milestone 2 — Core Shell + Dashboard
[ ] Milestone 3 — LGAs, Parks & Routes
...
```

Under each milestone record:

- completed
- in progress
- blocked
- tests
- known issues

Do not mark a milestone complete merely because pages visually exist.

---

# 44. BEFORE CODING — OUTPUT A SHORT PLAN

After reading the four documents, before making changes:

1. summarize your understanding of the architecture in no more than 15 bullets;
2. list the files/directories you expect to create for Milestones 0–2;
3. list any blockers or document conflicts;
4. then begin implementation.

Do not ask for confirmation if there is no material ambiguity.

---

# 45. AFTER IMPLEMENTATION — VALIDATION

Before ending the task, run all relevant available checks:

```text
PHP syntax
Laravel tests
frontend type check
frontend build
lint if configured
migration sanity
```

If the environment prevents a check, state exactly which check could not run and why.

Do not claim success for commands you did not execute.

---

# 46. FINAL CODEX REPORT

At the end of the run provide:

## Completed

List implemented work by milestone.

## Files Created/Changed

Summarize major files/directories.

## Tests/Validation

List commands executed and outcomes.

## Scope Compliance

Confirm whether any deviation from controlling documents occurred.

## Blockers

List unresolved blockers.

## Next Recommended Task

Point to the next incomplete milestone from the approved architecture document.

Do not suggest unrelated features.

---

# 47. FINAL NON-DRIFT RULE

Whenever you are tempted to add something because it appears useful, ask:

```text
Is it explicitly required by:
1. Project Scope?
2. Database Schema?
3. Screen Inventory?
4. Architecture / Implementation Plan?
```

If the answer is no:

**do not implement it.**

Record it separately as a possible future enhancement only if it is genuinely important.

The objective is not to build the biggest system.

The objective is to build the **approved Osun State Park Management System correctly, professionally, securely, and in a way that can progress from presentation demo to Government pilot without a rewrite.**

---

# START NOW

Read the four controlling documents completely.

Then execute **Milestone 0, Milestone 1 and Milestone 2 only**.

Preserve scope discipline.

Build the UI to a polished government-standard dashboard quality.

Do not proceed to later milestones until the foundation is stable and validated.
