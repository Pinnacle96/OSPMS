# OSUN STATE PARK MANAGEMENT SYSTEM
## Laravel Project Architecture & Implementation Plan — Version 1.0

**Prepared by:** Pinnacle Tech Hub  
**Status:** Development Execution Baseline  
**Applies to:** Phase 1 Demo / Presentation and Pilot-Ready Foundation  
**Backend:** Laravel Modular Monolith  
**Frontend:** React + TypeScript + Inertia.js + Tailwind CSS  
**Demo Database:** MySQL 8  
**Production Target:** PostgreSQL + Redis + Horizon  
**Demo Hosting:** Shared Hosting  
**Production Hosting:** VPS / Cloud  

---

# 1. Purpose

This document translates the approved Project Scope, Database Schema, and Screen/Page Inventory into the actual code structure and execution plan for development.

It defines:

- Laravel application architecture
- domain/module boundaries
- folder structure
- models
- migrations
- enums
- policies
- controllers
- actions
- services
- queries
- events/listeners
- jobs
- notifications
- React structure
- route structure
- seeders
- test structure
- development sequence
- demo deployment strategy
- production migration strategy

This is the development execution baseline.

A developer should be able to open this document and know:

> where a feature belongs, what classes should exist, what order to build in, and what must not be duplicated.

---

# 2. Architectural Decision

The application will be built as a **modular monolith**.

We will not begin with microservices.

The project remains one Laravel application and one deployable codebase, but business concerns are separated into domains.

```text
Laravel Application
│
├── Identity
├── Geography
├── Parks
├── Operators
├── Drivers
├── Vehicles
├── Routes
├── Revenue
├── Ticketing
├── Payments
├── Finance
├── Reconciliation
├── Enforcement
├── Incidents
├── Complaints
├── Notifications
├── Reporting
├── Audit
└── System
```

React/Inertia is the presentation layer.

Laravel remains authoritative for:

- authorization
- validation
- ticket amount resolution
- financial rules
- transaction state changes
- reconciliation
- audit logging
- reporting queries
- payment processing

React must not duplicate core business logic.

---

# 3. Repository Structure

Recommended project root:

```text
osun-park-management/
│
├── app/
├── bootstrap/
├── config/
├── database/
├── public/
├── resources/
├── routes/
├── storage/
├── tests/
├── .env.example
├── artisan
├── composer.json
├── package.json
├── phpunit.xml
├── tsconfig.json
├── vite.config.ts
└── README.md
```

The application should remain a standard Laravel project so that maintenance does not require custom framework knowledge.

---

# 4. Laravel Application Structure

Recommended `app/` structure:

```text
app/
│
├── Domains/
│   ├── Identity/
│   ├── Geography/
│   ├── Parks/
│   ├── Operators/
│   ├── Drivers/
│   ├── Vehicles/
│   ├── Routes/
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
├── Support/
│   ├── Audit/
│   ├── Money/
│   ├── Payments/
│   ├── References/
│   ├── Security/
│   └── Files/
│
└── Exceptions/
```

---

# 5. Standard Domain Structure

Each business domain should follow a predictable structure.

Example:

```text
app/Domains/Ticketing/
│
├── Actions/
│   ├── IssueTicketAction.php
│   ├── CancelTicketAction.php
│   └── ExpireTicketAction.php
│
├── DTOs/
│   └── IssueTicketData.php
│
├── Enums/
│   ├── TicketStatus.php
│   └── TicketPaymentStatus.php
│
├── Models/
│   └── Ticket.php
│
├── Policies/
│   └── TicketPolicy.php
│
├── Queries/
│   ├── TicketListQuery.php
│   └── TicketDashboardQuery.php
│
├── Services/
│   ├── TicketReferenceService.php
│   └── TicketVerificationService.php
│
└── Events/
    ├── TicketIssued.php
    └── TicketCancelled.php
```

Not every domain must contain every folder.

Create folders only when the domain actually needs them.

---

# 6. Domain Responsibilities

## 6.1 Identity

Responsibilities:

- users
- roles
- permissions
- authentication-related business rules
- LGA/Park/Operator access scopes
- login activity
- account activation/suspension

Core classes:

```text
Models/
  User.php
  UserLgaAccess.php
  UserParkAccess.php
  UserOperatorAccess.php
  LoginActivity.php

Policies/
  UserPolicy.php
  RolePolicy.php

Actions/
  CreateUserAction.php
  UpdateUserAction.php
  SuspendUserAction.php
  AssignUserScopeAction.php

Services/
  UserAccessScopeService.php
```

Spatie Permission remains responsible for role/permission persistence.

---

## 6.2 Geography

Responsibilities:

- LGAs
- geographic master data

Core classes:

```text
Models/
  Lga.php

Actions/
  CreateLgaAction.php
  UpdateLgaAction.php

Policies/
  LgaPolicy.php

Queries/
  LgaListQuery.php
  LgaDashboardQuery.php
```

---

## 6.3 Parks

Responsibilities:

- park registration
- park activation/suspension
- park profile
- park-to-route relationships
- park operational summaries

Core classes:

```text
Models/
  Park.php
  ParkRoute.php

Enums/
  ParkStatus.php

Actions/
  CreateParkAction.php
  UpdateParkAction.php
  ActivateParkAction.php
  SuspendParkAction.php
  AssignRouteToParkAction.php

Policies/
  ParkPolicy.php

Queries/
  ParkListQuery.php
  ParkDashboardQuery.php
```

---

## 6.4 Operators

Responsibilities:

- transport operator registration
- approval/suspension
- operator-to-park relationship
- operator-to-route relationship

Core classes:

```text
Models/
  Operator.php
  OperatorPark.php
  OperatorRoute.php

Enums/
  OperatorStatus.php

Actions/
  RegisterOperatorAction.php
  ApproveOperatorAction.php
  SuspendOperatorAction.php
  AssignOperatorToParkAction.php
  AssignOperatorToRouteAction.php

Policies/
  OperatorPolicy.php
```

---

## 6.5 Drivers

Responsibilities:

- driver registration
- approval/suspension
- driver documents
- driver status
- driver history

Core classes:

```text
Models/
  Driver.php
  DriverAssignment.php

Enums/
  DriverStatus.php
  AssignmentStatus.php

Actions/
  RegisterDriverAction.php
  ApproveDriverAction.php
  SuspendDriverAction.php

Policies/
  DriverPolicy.php

Queries/
  DriverListQuery.php
  DriverDetailQuery.php
```

Assignment creation itself belongs to an Assignment service/action because it also touches vehicle/operator/park/route relationships.

---

## 6.6 Vehicles

Responsibilities:

- vehicle registration
- vehicle approval/suspension
- registration/document expiry
- vehicle history

Core classes:

```text
Models/
  Vehicle.php

Enums/
  VehicleStatus.php
  VehicleType.php

Actions/
  RegisterVehicleAction.php
  ApproveVehicleAction.php
  SuspendVehicleAction.php

Policies/
  VehiclePolicy.php

Queries/
  VehicleListQuery.php
```

---

## 6.7 Assignments

Although the database table is `driver_assignments`, operational assignment logic should be treated as a cross-domain business service.

Recommended namespace:

```text
app/Domains/Operators/Actions/CreateDriverAssignmentAction.php
```

or preferably:

```text
app/Domains/System/Assignments/
```

To keep it clearer, create:

```text
app/Domains/Assignments/
│
├── Actions/
│   ├── CreateAssignmentAction.php
│   └── EndAssignmentAction.php
├── Models/
│   └── DriverAssignment.php
├── Policies/
│   └── AssignmentPolicy.php
└── Services/
    └── AssignmentValidationService.php
```

Rules:

- validate driver
- validate vehicle
- validate operator
- validate park
- validate route
- end conflicting active primary assignment where policy allows
- never destroy historical assignments

---

# 7. Revenue Domain

Responsibilities:

- revenue heads
- fee configuration
- amount resolution
- historical fee preservation

Core classes:

```text
app/Domains/Revenue/
│
├── Models/
│   ├── RevenueHead.php
│   └── FeeConfiguration.php
│
├── Enums/
│   ├── RevenueHeadStatus.php
│   ├── FeeConfigurationStatus.php
│   └── RevenueFrequency.php
│
├── Actions/
│   ├── CreateRevenueHeadAction.php
│   ├── UpdateRevenueHeadAction.php
│   ├── CreateFeeConfigurationAction.php
│   └── DeactivateFeeConfigurationAction.php
│
├── Services/
│   └── ResolveApplicableFeeService.php
│
├── Queries/
│   └── RevenueHeadListQuery.php
│
└── Policies/
    ├── RevenueHeadPolicy.php
    └── FeeConfigurationPolicy.php
```

`ResolveApplicableFeeService` is a critical service.

Input:

```text
revenue_head
vehicle_type
lga
park
route
date/time
```

Output:

```text
matching fee_configuration
amount
currency
scope used
```

Priority rule should prefer the most specific applicable configuration.

Recommended priority:

```text
Park + Route + Vehicle Type
Park + Vehicle Type
Park
LGA + Vehicle Type
LGA
Statewide + Vehicle Type
Statewide Default
```

Do not implement this logic in React.

---

# 8. Ticketing Domain

Responsibilities:

- ticket issuance
- ticket cancellation
- ticket expiry
- QR verification
- fee snapshot
- ticket references

Core classes:

```text
app/Domains/Ticketing/
│
├── Models/
│   └── Ticket.php
│
├── Enums/
│   ├── TicketStatus.php
│   └── TicketPaymentStatus.php
│
├── DTOs/
│   └── IssueTicketData.php
│
├── Actions/
│   ├── IssueTicketAction.php
│   ├── CancelTicketAction.php
│   └── MarkTicketPaidAction.php
│
├── Services/
│   ├── TicketReferenceService.php
│   ├── TicketVerificationService.php
│   └── TicketExpiryService.php
│
├── Queries/
│   ├── TicketListQuery.php
│   └── TicketDetailQuery.php
│
├── Policies/
│   └── TicketPolicy.php
│
└── Events/
    ├── TicketIssued.php
    ├── TicketPaid.php
    └── TicketCancelled.php
```

`IssueTicketAction` should:

```text
1. authorize caller
2. validate driver/vehicle/operator/park relationship
3. resolve applicable fee
4. snapshot fee name/code/amount
5. create secure verification token
6. generate ticket reference
7. create ticket
8. write audit event
9. dispatch TicketIssued event
10. return ticket
```

Wrap issuance in a database transaction.

---

# 9. Payment Abstraction

Create a provider-independent payment contract.

```text
app/Support/Payments/
│
├── Contracts/
│   └── PaymentGateway.php
│
├── DTOs/
│   ├── PaymentInitiationResult.php
│   └── PaymentVerificationResult.php
│
├── Gateways/
│   ├── DemoPaymentGateway.php
│   ├── PaystackPaymentGateway.php
│   ├── FlutterwavePaymentGateway.php
│   └── GovernmentPaymentGateway.php
│
└── PaymentGatewayManager.php
```

Phase 1 only requires `DemoPaymentGateway` to work.

The other classes may remain unimplemented placeholders only if needed for interface testing; otherwise add them when integration starts.

Contract responsibilities:

```php
interface PaymentGateway
{
    public function initiate(...): PaymentInitiationResult;
    public function verify(string $reference): PaymentVerificationResult;
    public function refund(...): mixed;
}
```

Exact signatures can be finalized during coding.

---

# 10. Payments Domain

Core classes:

```text
app/Domains/Payments/
│
├── Models/
│   ├── Payment.php
│   ├── Receipt.php
│   └── WebhookEvent.php
│
├── Enums/
│   ├── PaymentStatus.php
│   └── PaymentChannel.php
│
├── Actions/
│   ├── InitiatePaymentAction.php
│   ├── RecordSuccessfulPaymentAction.php
│   ├── RecordFailedPaymentAction.php
│   ├── ReversePaymentAction.php
│   └── GenerateReceiptAction.php
│
├── Services/
│   ├── PaymentReferenceService.php
│   ├── ReceiptReferenceService.php
│   └── PaymentIdempotencyService.php
│
├── Policies/
│   ├── PaymentPolicy.php
│   └── ReceiptPolicy.php
│
└── Events/
    ├── PaymentSucceeded.php
    ├── PaymentFailed.php
    └── PaymentReversed.php
```

`RecordSuccessfulPaymentAction` is one of the most critical classes in the system.

It should run inside a database transaction and:

```text
1. lock/reload payment as needed
2. confirm it has not already been processed
3. mark payment successful
4. mark ticket paid
5. create immutable financial ledger credit
6. generate canonical receipt
7. create financial audit record
8. dispatch PaymentSucceeded
```

It must be idempotent.

---

# 11. Finance Domain

Core classes:

```text
app/Domains/Finance/
│
├── Models/
│   ├── FinancialTransaction.php
│   ├── Settlement.php
│   ├── SettlementItem.php
│   ├── Refund.php
│   └── FinancialAdjustment.php
│
├── Enums/
│   ├── TransactionType.php
│   ├── TransactionDirection.php
│   ├── SettlementStatus.php
│   ├── RefundStatus.php
│   └── AdjustmentStatus.php
│
├── Actions/
│   ├── CreateLedgerCreditAction.php
│   ├── CreateLedgerDebitAction.php
│   ├── CreateSettlementAction.php
│   ├── RequestRefundAction.php
│   ├── ApproveRefundAction.php
│   ├── RequestAdjustmentAction.php
│   └── ApproveAdjustmentAction.php
│
├── Services/
│   ├── LedgerService.php
│   ├── SettlementService.php
│   └── FinancialIntegrityService.php
│
├── Policies/
│   ├── FinancialTransactionPolicy.php
│   ├── SettlementPolicy.php
│   ├── RefundPolicy.php
│   └── FinancialAdjustmentPolicy.php
│
└── Queries/
    ├── FinancialLedgerQuery.php
    └── RevenueDashboardQuery.php
```

Financial ledger rows must not expose edit/delete actions.

---

# 12. Reconciliation Domain

```text
app/Domains/Reconciliation/
│
├── Models/
│   ├── ReconciliationRun.php
│   └── ReconciliationItem.php
│
├── Enums/
│   ├── ReconciliationRunStatus.php
│   ├── ReconciliationItemStatus.php
│   └── ReconciliationExceptionType.php
│
├── Actions/
│   ├── StartReconciliationAction.php
│   ├── RunReconciliationAction.php
│   └── ResolveReconciliationExceptionAction.php
│
├── Services/
│   ├── ReconciliationMatcher.php
│   └── ReconciliationSummaryService.php
│
├── Policies/
│   └── ReconciliationPolicy.php
│
└── Jobs/
    └── ProcessReconciliationRun.php
```

Demo environment:

- reconciliation may run synchronously for a small dataset
- the architecture should still allow queue processing

Production:

```text
Redis + Horizon
```

---

# 13. Enforcement Domain

```text
app/Domains/Enforcement/
│
├── Models/
│   ├── Inspection.php
│   └── Violation.php
│
├── Enums/
│   ├── InspectionResult.php
│   └── ViolationStatus.php
│
├── Actions/
│   ├── RecordInspectionAction.php
│   ├── RecordViolationAction.php
│   └── ResolveViolationAction.php
│
├── Services/
│   ├── FieldVerificationService.php
│   └── ComplianceSummaryService.php
│
└── Policies/
    ├── InspectionPolicy.php
    └── ViolationPolicy.php
```

The field PWA must call the same Laravel services used by desktop pages.

Do not create a separate business logic layer in the PWA.

---

# 14. Incidents Domain

```text
app/Domains/Incidents/
│
├── Models/
│   └── Incident.php
│
├── Enums/
│   └── IncidentStatus.php
│
├── Actions/
│   ├── CreateIncidentAction.php
│   ├── EscalateIncidentAction.php
│   └── ResolveIncidentAction.php
│
├── Policies/
│   └── IncidentPolicy.php
│
└── Queries/
    └── IncidentListQuery.php
```

Evidence is attached through the shared media attachment system.

---

# 15. Complaints Domain

```text
app/Domains/Complaints/
│
├── Models/
│   ├── Complaint.php
│   └── ComplaintNote.php
│
├── Enums/
│   ├── ComplaintStatus.php
│   └── ComplaintSource.php
│
├── Actions/
│   ├── CreateComplaintAction.php
│   ├── AssignComplaintAction.php
│   ├── AddComplaintNoteAction.php
│   └── ResolveComplaintAction.php
│
├── Policies/
│   └── ComplaintPolicy.php
│
└── Queries/
    └── ComplaintListQuery.php
```

The public complaint form should call the same complaint creation service with a restricted public data contract.

---

# 16. Reporting Domain

Avoid embedding large report SQL inside controllers.

```text
app/Domains/Reporting/
│
├── Reports/
│   ├── DailyRevenueReport.php
│   ├── MonthlyRevenueReport.php
│   ├── RevenueByParkReport.php
│   ├── RevenueByLgaReport.php
│   ├── TransactionReport.php
│   ├── ReconciliationReport.php
│   ├── VehicleReport.php
│   ├── DriverReport.php
│   ├── OperatorReport.php
│   ├── IncidentReport.php
│   └── ComplaintReport.php
│
├── Actions/
│   └── GenerateReportExportAction.php
│
└── Jobs/
    └── GenerateReportExport.php
```

Each report class should expose a predictable interface:

```text
filters
query
summary
columns
export
```

---

# 17. Audit Domain

```text
app/Domains/Audit/
│
├── Models/
│   └── FinancialAuditLog.php
│
├── Services/
│   ├── FinancialAuditService.php
│   └── AuditHashService.php
│
└── Queries/
    ├── ActivityAuditQuery.php
    └── FinancialAuditQuery.php
```

Use Spatie Activitylog for general activity.

Use the custom financial audit service for high-value financial events.

---

# 18. System Domain

Responsibilities:

- settings
- references
- media attachment metadata
- common system configuration

```text
app/Domains/System/
│
├── Models/
│   ├── SystemSetting.php
│   └── MediaAttachment.php
│
├── Services/
│   ├── SettingService.php
│   └── MediaUploadService.php
│
└── Policies/
    └── SystemSettingPolicy.php
```

Do not place API secrets into `system_settings`.

Secrets remain in environment/secrets configuration.

---

# 19. Reference Generation

Create a reusable service:

```text
app/Support/References/ReferenceGenerator.php
```

Responsibilities:

```text
generate park code
generate driver number
generate vehicle number
generate operator number
generate ticket reference
generate payment reference
generate receipt reference
generate settlement reference
generate reconciliation reference
generate incident reference
generate complaint reference
```

References must be unique and generated server-side.

For financial references, use database uniqueness plus retry handling.

---

# 20. Money Handling

Create:

```text
app/Support/Money/Money.php
```

or a lightweight money value object.

Rules:

- application code never uses float for financial calculations
- database uses decimal
- amounts received from forms are normalized server-side
- formatted currency is display logic only
- raw calculations use decimal/string-safe arithmetic

For Phase 1, avoid unnecessary currency abstraction beyond NGN support, but retain `currency` fields.

---

# 21. Laravel Models

The core model list for Phase 1 is:

```text
User
UserLgaAccess
UserParkAccess
UserOperatorAccess
LoginActivity

Lga
Park
ParkRoute
Route

Operator
OperatorPark
OperatorRoute

Driver
Vehicle
DriverAssignment
MediaAttachment

RevenueHead
FeeConfiguration

Ticket

Payment
Receipt
WebhookEvent

FinancialTransaction
Settlement
SettlementItem
Refund
FinancialAdjustment

ReconciliationRun
ReconciliationItem

Inspection
Violation
Incident

Complaint
ComplaintNote

FinancialAuditLog
SystemSetting
```

Plus package/framework models/tables where necessary.

---

# 22. Eloquent Relationship Rules

Examples:

```text
Lga
  hasMany Parks

Park
  belongsTo Lga
  belongsToMany Routes
  belongsToMany Operators
  hasMany Tickets

Operator
  belongsToMany Parks
  belongsToMany Routes
  hasMany DriverAssignments

Driver
  hasMany DriverAssignments
  hasMany Tickets

Vehicle
  hasMany DriverAssignments
  hasMany Tickets

Ticket
  belongsTo RevenueHead
  belongsTo FeeConfiguration
  belongsTo Lga
  belongsTo Park
  belongsTo Operator
  belongsTo Driver
  belongsTo Vehicle
  belongsTo Route
  hasMany Payments

Payment
  belongsTo Ticket
  hasOne Receipt
  hasMany FinancialTransactions

FinancialTransaction
  belongsTo Payment
  belongsTo Ticket
  belongsTo parent transaction
  hasMany child transactions
```

Controllers should eager-load only relationships actually required by the page.

Avoid global eager loading.

---

# 23. Enum Strategy

Use PHP backed enums.

Example:

```php
enum TicketStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Expired = 'expired';
    case Cancelled = 'cancelled';
    case Reversed = 'reversed';
}
```

Recommended enums:

```text
UserStatus
ParkStatus
OperatorStatus
DriverStatus
VehicleStatus
AssignmentStatus
RevenueHeadStatus
FeeConfigurationStatus
TicketStatus
TicketPaymentStatus
PaymentStatus
PaymentChannel
TransactionType
TransactionDirection
SettlementStatus
ReconciliationRunStatus
ReconciliationItemStatus
ReconciliationExceptionType
RefundStatus
AdjustmentStatus
IncidentStatus
ComplaintStatus
ComplaintSource
ViolationStatus
```

Do not scatter literal status strings throughout controllers.

---

# 24. Form Request Structure

Use Laravel Form Requests for server validation.

Recommended:

```text
app/Http/Requests/
│
├── Admin/
│   ├── StoreUserRequest.php
│   └── UpdateUserRequest.php
├── Parks/
├── Operators/
├── Drivers/
├── Vehicles/
├── Revenue/
├── Tickets/
├── Payments/
├── Finance/
├── Reconciliation/
├── Enforcement/
├── Incidents/
└── Complaints/
```

Controllers should receive validated data from Form Requests, then call Actions.

---

# 25. Controller Strategy

Controllers remain thin.

Bad:

```text
controller:
  validates
  calculates fee
  writes 5 tables
  generates receipt
  sends notification
  audits
```

Correct:

```text
controller:
  authorize
  validate request
  call action/service
  return Inertia response / redirect
```

---

# 26. Web Controllers

Recommended controller groups:

```text
app/Http/Controllers/Web/
│
├── DashboardController.php
│
├── Admin/
│   ├── UserController.php
│   ├── RoleController.php
│   └── UserScopeController.php
│
├── LgaController.php
├── ParkController.php
├── RouteController.php
├── OperatorController.php
├── DriverController.php
├── VehicleController.php
├── AssignmentController.php
├── RevenueHeadController.php
├── FeeConfigurationController.php
├── TicketController.php
├── PaymentController.php
├── ReceiptController.php
│
├── Finance/
│   ├── LedgerController.php
│   ├── SettlementController.php
│   ├── ReconciliationController.php
│   ├── RefundController.php
│   └── AdjustmentController.php
│
├── InspectionController.php
├── ViolationController.php
├── IncidentController.php
├── ComplaintController.php
├── NotificationController.php
├── ReportController.php
├── AuditController.php
└── SettingsController.php
```

A controller may be split when it becomes too large, but do not create one controller per button unnecessarily.

---

# 27. Public Controllers

```text
app/Http/Controllers/Public/
│
├── TicketVerificationController.php
├── ReceiptVerificationController.php
└── PublicComplaintController.php
```

Public endpoints must:

- rate limit
- expose minimal data
- not leak internal IDs
- use secure verification tokens

---

# 28. Field Controllers

```text
app/Http/Controllers/Field/
│
├── FieldHomeController.php
├── FieldVerificationController.php
├── FieldDriverController.php
├── FieldVehicleController.php
├── FieldOperatorController.php
├── FieldInspectionController.php
└── FieldIncidentController.php
```

These return mobile-first Inertia pages.

---

# 29. API Controllers

Phase 1 should expose only the APIs actually needed.

Recommended initial API surface:

```text
/api/v1/field/verify-ticket
/api/v1/field/drivers/search
/api/v1/field/vehicles/search
/api/v1/field/operators/search
/api/v1/field/inspections
/api/v1/field/incidents
```

If Inertia can satisfy a feature cleanly, do not create an API solely for architectural fashion.

Keep core services API-ready.

---

# 30. Policies

Every protected domain entity should have a Laravel Policy.

Minimum policies:

```text
UserPolicy
LgaPolicy
ParkPolicy
RoutePolicy
OperatorPolicy
DriverPolicy
VehiclePolicy
AssignmentPolicy
RevenueHeadPolicy
FeeConfigurationPolicy
TicketPolicy
PaymentPolicy
ReceiptPolicy
FinancialTransactionPolicy
SettlementPolicy
ReconciliationPolicy
RefundPolicy
FinancialAdjustmentPolicy
InspectionPolicy
ViolationPolicy
IncidentPolicy
ComplaintPolicy
ReportPolicy
AuditPolicy
SystemSettingPolicy
```

Policies should combine:

```text
permission
+
scope
```

Example:

A user may have `view_park`, but can only view the park if the user:

- has statewide access, or
- has that LGA scope, or
- has that park scope

---

# 31. Scope Authorization Service

Create:

```text
app/Domains/Identity/Services/UserAccessScopeService.php
```

Methods may include:

```text
canAccessLga(User $user, Lga $lga)
canAccessPark(User $user, Park $park)
canAccessOperator(User $user, Operator $operator)
accessibleLgaIds(User $user)
accessibleParkIds(User $user)
accessibleOperatorIds(User $user)
```

List queries should apply scope filtering centrally.

Do not repeat access filtering manually in every controller.

---

# 32. Query Object Pattern

Use Query classes for complex list/dashboard filtering.

Example:

```text
DriverListQuery
TicketListQuery
PaymentListQuery
FinancialLedgerQuery
ComplaintListQuery
RevenueDashboardQuery
```

Example responsibilities:

```text
apply user scope
apply search
apply status
apply date range
apply LGA filter
apply park filter
apply sorting
paginate
```

This keeps controllers small and consistent.

---

# 33. Migration Structure

Use one migration per coherent table creation.

Example:

```text
database/migrations/
  2026_10_XX_000001_create_lgas_table.php
  2026_10_XX_000002_create_parks_table.php
  ...
```

Do not make manual production schema changes outside migrations.

Migration groups should follow the approved Database Schema v1.0 order.

---

# 34. Seeder Structure

```text
database/seeders/
│
├── DatabaseSeeder.php
│
├── RolePermissionSeeder.php
├── LgaSeeder.php
├── DemoUserSeeder.php
├── ParkSeeder.php
├── RouteSeeder.php
├── OperatorSeeder.php
├── DriverSeeder.php
├── VehicleSeeder.php
├── AssignmentSeeder.php
├── RevenueHeadSeeder.php
├── FeeConfigurationSeeder.php
├── TicketSeeder.php
├── PaymentSeeder.php
├── FinanceSeeder.php
├── ReconciliationSeeder.php
├── IncidentSeeder.php
└── ComplaintSeeder.php
```

Recommended execution:

```text
RolePermissionSeeder
LgaSeeder
DemoUserSeeder
ParkSeeder
RouteSeeder
OperatorSeeder
DriverSeeder
VehicleSeeder
AssignmentSeeder
RevenueHeadSeeder
FeeConfigurationSeeder
TicketSeeder
PaymentSeeder
FinanceSeeder
ReconciliationSeeder
IncidentSeeder
ComplaintSeeder
```

---

# 35. Factories

Use factories for demo/testing:

```text
LgaFactory
ParkFactory
RouteFactory
OperatorFactory
DriverFactory
VehicleFactory
DriverAssignmentFactory
RevenueHeadFactory
FeeConfigurationFactory
TicketFactory
PaymentFactory
FinancialTransactionFactory
IncidentFactory
ComplaintFactory
```

Factories must generate synthetic information only.

Do not use scraped or real citizen data.

---

# 36. Demo Accounts

Seeder should create predictable demo users.

Example roles:

```text
superadmin@demo.local
stateadmin@demo.local
finance@demo.local
auditor@demo.local
lgaadmin@demo.local
parkmanager@demo.local
ticketing@demo.local
enforcement@demo.local
helpdesk@demo.local
operator@demo.local
```

Passwords should:

- be configurable through `.env` in shared demo deployment
- not use production credentials
- be changed before external presentation if publicly reachable

A presentation-only shortcut account may exist, but never bypass normal authorization.

---

# 37. React Directory Structure

Recommended:

```text
resources/js/
│
├── app.tsx
├── bootstrap.ts
│
├── Components/
│   ├── App/
│   ├── Data/
│   ├── Forms/
│   ├── Feedback/
│   ├── Finance/
│   ├── Navigation/
│   └── Verification/
│
├── Layouts/
│   ├── AppLayout.tsx
│   ├── AuthLayout.tsx
│   ├── PublicLayout.tsx
│   └── FieldLayout.tsx
│
├── Pages/
│   ├── Auth/
│   ├── Account/
│   ├── Dashboard/
│   ├── Admin/
│   ├── Lgas/
│   ├── Parks/
│   ├── Routes/
│   ├── Operators/
│   ├── Drivers/
│   ├── Vehicles/
│   ├── Assignments/
│   ├── RevenueHeads/
│   ├── Fees/
│   ├── Tickets/
│   ├── Payments/
│   ├── Receipts/
│   ├── Finance/
│   ├── Field/
│   ├── Inspections/
│   ├── Violations/
│   ├── Incidents/
│   ├── Complaints/
│   ├── Notifications/
│   ├── Reports/
│   ├── Audit/
│   ├── Settings/
│   ├── Public/
│   └── Errors/
│
├── hooks/
│   ├── usePermissions.ts
│   ├── useCurrency.ts
│   ├── useDebounce.ts
│   └── useFilters.ts
│
├── lib/
│   ├── permissions.ts
│   ├── formatters.ts
│   ├── dates.ts
│   ├── currency.ts
│   └── routes.ts
│
├── types/
│   ├── auth.ts
│   ├── domain.ts
│   ├── finance.ts
│   └── index.ts
│
└── pwa/
    ├── register.ts
    └── offline.ts
```

---

# 38. React Component Rules

## 38.1 Components are presentational

A component may:

- render props
- handle local UI state
- submit forms
- display validation errors

A component should not:

- calculate authoritative ticket fees
- determine revenue allocation
- decide if a refund is valid
- authorize itself
- reconcile transactions

---

## 38.2 Shared DataTable

Build one reusable `DataTable` pattern that supports:

```text
columns
pagination
server filters
sorting
empty state
loading state
row actions
```

Do not build a completely different table implementation for every module.

---

## 38.3 Shared status components

Use:

```text
<StatusBadge status="paid" />
```

rather than styling statuses separately in every page.

---

# 39. Inertia Shared Props

Recommended global shared props:

```text
auth.user
auth.roles
auth.permissions
auth.scopes
flash.success
flash.error
system.name
system.currency
system.timezone
system.branding
navigation
unread_notifications_count
```

Do not send sensitive role internals or unnecessary database data globally.

---

# 40. Route Files

Split Laravel routes by concern.

```text
routes/
├── web.php
├── auth.php
├── admin.php
├── operations.php
├── ticketing.php
├── finance.php
├── enforcement.php
├── support.php
├── reports.php
├── settings.php
├── public.php
├── api.php
└── console.php
```

`web.php` should load the appropriate files or route groups through Laravel conventions.

---

# 41. Route Prefix Plan

```text
/                       dashboard redirect

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

---

# 42. Route Naming Convention

Use consistent route names.

Examples:

```text
dashboard.state

lgas.index
lgas.show
lgas.create
lgas.store
lgas.edit
lgas.update

parks.index
parks.show

drivers.index
drivers.show

tickets.index
tickets.create
tickets.store
tickets.show
tickets.cancel

payments.show
payments.demo.process

finance.ledger.index
finance.reconciliation.index

field.home
field.scan

public.ticket.verify
public.receipt.verify
```

Avoid unnamed routes in core business modules.

---

# 43. Middleware

Recommended middleware concerns:

```text
auth
verified where required
active_user
permission
scope
rate_limit
audit context
```

Create custom middleware only where it adds real value.

Possible custom middleware:

```text
EnsureUserIsActive
EnsureDemoMode
```

Do not place detailed entity authorization in middleware when a Policy is more appropriate.

---

# 44. Events and Listeners

Use events for side effects, not core transaction correctness.

Examples:

```text
TicketIssued
PaymentSucceeded
PaymentFailed
PaymentReversed
ComplaintAssigned
IncidentEscalated
ReconciliationCompleted
```

Listeners can handle:

```text
send notification
queue email/SMS
refresh derived cache
write secondary activity
```

Core ledger creation should remain inside the financial transaction action, not depend on an asynchronous listener.

---

# 45. Jobs

Phase 1 jobs:

```text
GenerateReportExport
ProcessReconciliationRun
SendQueuedNotification
ExpireOldTickets
CheckDocumentExpiries
```

Demo shared hosting:

```text
QUEUE_CONNECTION=database
```

Production:

```text
QUEUE_CONNECTION=redis
Laravel Horizon
```

---

# 46. Scheduler

Recommended scheduled commands/jobs:

```text
everyMinute / scheduled:
  queue processing strategy appropriate for hosting

daily:
  expire applicable tickets
  check driver licence expiry
  check vehicle roadworthiness expiry
  check insurance expiry

daily:
  database backup where supported

optional:
  scheduled reconciliation
```

On shared hosting, configure cron to run Laravel Scheduler.

---

# 47. Notifications

Use Laravel Notifications.

Channels Phase 1:

```text
database
mail optional
```

Later:

```text
SMS
government notification gateway
```

Notifications:

```text
DriverApprovedNotification
PaymentSuccessfulNotification
ReconciliationExceptionNotification
ComplaintAssignedNotification
IncidentAssignedNotification
DocumentExpiryNotification
```

---

# 48. File Storage

Use Laravel filesystem abstraction.

Disks:

```text
local
public
r2
```

Phase 1 recommendation:

- small UI assets can remain public
- uploaded driver/vehicle/evidence files use private/object storage where practical

Media upload service must:

```text
validate MIME
validate extension
validate size
generate safe filename
store metadata
associate uploader
```

Do not expose raw bucket paths unnecessarily.

---

# 49. QR Implementation

QR codes should encode only the public verification URL/token.

Example:

```text
https://demo.example.com/verify/ticket/{secure-token}
```

Do not embed:

- full driver profile
- private phone numbers
- raw internal IDs
- payment secrets

Verification token should be random/unguessable and unique.

---

# 50. PWA Strategy

Phase 1 field PWA includes:

```text
installable manifest
service worker
basic app shell caching
camera QR scanning
mobile-friendly search
incident evidence capture
```

Offline capability should be conservative.

Allowed offline:

```text
cached app shell
recent safe verification data where implemented
draft incident form where practical
```

Not promised in Phase 1:

```text
offline financial settlement
offline authoritative ticket issuance
complex synchronization conflicts
```

---

# 51. Testing Architecture

```text
tests/
│
├── Feature/
│   ├── Auth/
│   ├── Identity/
│   ├── Parks/
│   ├── Operators/
│   ├── Drivers/
│   ├── Vehicles/
│   ├── Revenue/
│   ├── Ticketing/
│   ├── Payments/
│   ├── Finance/
│   ├── Reconciliation/
│   ├── Enforcement/
│   ├── Complaints/
│   └── Reports/
│
└── Unit/
    ├── Revenue/
    ├── Ticketing/
    ├── Payments/
    ├── Finance/
    └── Reconciliation/
```

---

# 52. Critical Tests

The following tests are mandatory before the demo is considered ready.

## Authorization

```text
LGA admin cannot access another LGA
Park manager cannot manage another park
Operator user cannot view another operator's private records
Auditor cannot modify financial ledger
```

## Ticketing

```text
inactive driver cannot receive ticket where policy forbids
invalid assignment is rejected
correct fee is resolved
ticket snapshots amount
ticket reference is unique
verification token is unique
```

## Payments

```text
successful demo payment creates one ledger credit
replaying success does not duplicate ledger entry
failed payment does not create receipt
receipt is only created once
```

## Finance

```text
original financial transaction cannot be edited through application
refund creates debit entry
adjustment creates new entry
historical ticket amount does not change after fee update
```

## Reconciliation

```text
correct records match
amount mismatch becomes exception
ticket without payment becomes exception
duplicate provider reference is detected
resolved exception records actor/reason
```

---

# 53. Logging

Use structured application logs.

Important events:

```text
authentication errors
payment gateway errors
payment state transition failures
reconciliation failures
report generation failures
storage upload failures
unexpected authorization failures
```

Never log:

```text
passwords
API secrets
full access tokens
sensitive identity documents
```

---

# 54. Error Handling

Business errors should be explicit.

Examples:

```text
DriverNotActiveException
VehicleNotActiveException
NoApplicableFeeException
TicketAlreadyPaidException
PaymentAlreadyProcessedException
InvalidPaymentTransitionException
ReconciliationAlreadyCompletedException
```

Convert these into useful UI messages.

Do not expose stack traces in production/demo public pages.

---

# 55. Development Environment

Recommended local requirements:

```text
PHP compatible with selected Laravel release
Composer
Node.js LTS
npm or pnpm
MySQL 8
Git
```

Optional:

```text
Mailpit
local S3-compatible storage
Redis later
```

Use `.env.example` as the authoritative list of environment keys.

---

# 56. Environment Variables

Suggested groups:

```text
APP_*
DB_*

QUEUE_CONNECTION
CACHE_STORE
SESSION_DRIVER

FILESYSTEM_DISK

R2_*

PAYMENT_MODE=demo
PAYMENT_PROVIDER=demo

PAYSTACK_*
FLUTTERWAVE_*

MAIL_*

DEMO_DEFAULT_PASSWORD

OSPM_REFERENCE_PREFIX
```

Do not commit real secrets.

---

# 57. Demo Mode

Add an explicit environment flag:

```text
OSPM_DEMO_MODE=true
```

Use it to enable:

- synthetic payment simulation
- demo labels
- presentation reset tools restricted to super admin
- optional deterministic demo scenario

Do not scatter `if demo` checks everywhere.

Create:

```text
config/ospm.php
```

Example:

```php
return [
    'demo_mode' => env('OSPM_DEMO_MODE', false),
    'currency' => env('OSPM_CURRENCY', 'NGN'),
];
```

---

# 58. Demo Reset

For presentation reliability, create an artisan command:

```text
php artisan ospm:demo-reset
```

Responsibilities:

```text
reject if not demo environment
wipe/reseed demo transactional data safely
restore known presentation state
create demo users
create known ticket/payment/reconciliation examples
```

Never make this command runnable in production.

---

# 59. Demo Presentation Data

Seed a known narrative.

Example:

```text
LGA: Osogbo
Park: Demo Central Motor Park
Operator: Demo Transport Services
Driver: Demo Driver
Vehicle: ABC-123XY
Revenue Head: Daily Park Ticket
Configured Amount: ₦500
```

Also seed:

```text
one paid/reconciled ticket
one failed payment
one pending payment
one reconciliation amount mismatch
one complaint
one incident
```

The live presentation should not depend entirely on random factory data.

---

# 60. Git Branch Strategy

```text
main
develop
feature/*
fix/*
```

Examples:

```text
feature/auth-rbac
feature/lga-parks
feature/driver-registry
feature/ticketing
feature/demo-payments
feature/reconciliation
feature/field-pwa
```

Rules:

- feature branches start from `develop`
- reviewed features merge to `develop`
- stable demo releases merge to `main`
- tag presentation releases

Example:

```text
v0.1-demo
v0.2-demo
v1.0-pilot-ready
```

---

# 61. Commit Style

Recommended:

```text
feat(ticketing): add ticket issuance workflow
fix(finance): prevent duplicate ledger entry
refactor(revenue): centralize fee resolution
test(reconciliation): cover amount mismatch
docs(scope): update payment workflow
```

---

# 62. CI/CD — Phase 1

For the shared-hosting demo, automated deployment is optional.

Recommended initial process:

```text
local development
→ git push
→ build frontend
→ deploy stable package to shared hosting
→ run migrations
→ clear caches
→ verify demo
```

Do not run Node/Vite development server on shared hosting.

Build frontend assets before deployment:

```text
npm run build
```

---

# 63. Shared Hosting Deployment Layout

Preferred if hosting supports pointing document root to Laravel `public/`:

```text
/home/account/apps/ospm/
  app/
  bootstrap/
  config/
  ...
  public/
```

Domain/subdomain document root:

```text
/home/account/apps/ospm/public
```

If hosting cannot set the document root appropriately, use the host's supported Laravel deployment method rather than moving framework files into insecure public directories.

---

# 64. Shared Hosting Runtime

Phase 1:

```text
Apache / LiteSpeed
PHP
Laravel
MySQL
database queue
cron
compiled React/Inertia assets
Cloudflare
```

No Redis required.

No Horizon required.

No WebSocket server required.

---

# 65. Production Migration

After Government approval/pilot authorization:

```text
shared hosting
      ↓
VPS / Cloud
      ↓
Nginx
PHP-FPM
Laravel
PostgreSQL
Redis
Horizon
Object Storage
Monitoring
Automated Backups
Cloudflare WAF
```

The goal is migration, not rewrite.

---

# 66. PostgreSQL Migration Preparation

Before migration:

```text
run full test suite against PostgreSQL
verify all migrations
verify JSON behavior
verify unique indexes
verify case-sensitive searches
verify reporting queries
verify pagination
verify date handling
```

Do not switch production database engines without a staging validation environment.

---

# 67. Feature Development Sequence

The following sequence is the official build order.

---

## MILESTONE 0 — Project Bootstrap

Deliverables:

```text
Laravel project
React + TypeScript + Inertia
Tailwind
Git repository
environment config
base layouts
error pages
CI lint/test skeleton
```

Acceptance:

- application boots
- React page renders through Inertia
- MySQL connection works
- production asset build works

---

## MILESTONE 1 — Authentication + RBAC

Build:

```text
login
logout
forgot/reset password
User model
roles
permissions
user status
scope tables
login activity
UserAccessScopeService
```

Screens:

```text
SCR-001 to SCR-006
SCR-012 to SCR-019
```

Acceptance:

- role permissions work
- inactive user cannot authenticate
- scope restrictions are testable
- admin can create users and assign access

---

## MILESTONE 2 — Core Application Shell + State Dashboard Skeleton

Build:

```text
AppLayout
Sidebar
Topbar
Breadcrumbs
shared page header
StatCard
DataTable
filters
pagination
StatusBadge
notifications placeholder
dashboard skeleton
```

Screen:

```text
SCR-007
```

Use real database counts where available; do not fake calculations in React.

---

## MILESTONE 3 — LGAs, Parks & Routes

Build:

```text
LGA CRUD
Park CRUD
Route CRUD
ParkRoute
activation/suspension
scope-aware list queries
LGA dashboard
Park dashboard skeleton
```

Screens:

```text
SCR-020 to SCR-031
SCR-010
SCR-011
```

Acceptance:

- LGA admin sees only scoped data
- park belongs to LGA
- park routes can be assigned
- status changes are audited

---

## MILESTONE 4 — Operators

Build:

```text
Operator
OperatorPark
OperatorRoute
registration
approval
suspension
documents
operator user access
```

Screens:

```text
SCR-032 to SCR-035
```

Acceptance:

- operator can belong to parks/routes
- operator user sees own scoped profile only

---

## MILESTONE 5 — Drivers, Vehicles & Assignments

Build:

```text
Driver
Vehicle
DriverAssignment
documents/media attachments
approval/suspension
assignment creation
assignment ending
assignment history
expiry fields
```

Screens:

```text
SCR-036 to SCR-047
```

Acceptance:

- relationships are historically preserved
- one active primary assignment rule works
- vehicle/driver searches work
- document uploads are validated

---

## MILESTONE 6 — Revenue Heads & Fee Configuration

Build:

```text
RevenueHead
FeeConfiguration
ResolveApplicableFeeService
fee history
effective dates
scope priority
```

Screens:

```text
SCR-048 to SCR-055
```

Acceptance:

- correct fee resolves based on scope
- expired/inactive fee is ignored
- old tickets will not depend on future fee changes

Critical unit tests must be written here before ticketing.

---

## MILESTONE 7 — Ticketing + QR

Build:

```text
Ticket
IssueTicketAction
TicketReferenceService
verification token
QR generation
ticket cancellation
ticket expiry structure
public verification
```

Screens:

```text
SCR-056 to SCR-060
SCR-067
```

Acceptance:

- valid assignment can issue ticket
- correct fee is snapshotted
- unique reference is generated
- QR opens safe verification page
- unauthorized cancellation is denied

At this milestone the demo starts becoming presentation-worthy.

---

## MILESTONE 8 — Demo Payments + Receipts + Ledger

Build:

```text
PaymentGateway contract
DemoPaymentGateway
Payment
Receipt
FinancialTransaction
idempotency
demo success
demo failure
demo pending
ledger credit
receipt PDF/print
```

Screens:

```text
SCR-061 to SCR-070
SCR-068
```

Acceptance:

- success updates ticket/payment correctly
- one success produces one ledger credit
- replay cannot duplicate transaction
- failed/pending payment creates no valid receipt
- public receipt verification works

This is a release-blocking milestone.

---

## MILESTONE 9 — Revenue Dashboards

Build:

```text
State Dashboard queries
Revenue Dashboard
LGA Dashboard
Park Dashboard
revenue trend chart
revenue by LGA
revenue by park
payment status breakdown
```

Screens:

```text
SCR-007 to SCR-011
```

Acceptance:

- numbers derive from ledger/payment records
- filters respect user scope
- dashboard totals reconcile with detail queries

---

## MILESTONE 10 — Settlement + Reconciliation

Build:

```text
Settlement
SettlementItem
ReconciliationRun
ReconciliationItem
matching engine
exception detection
exception resolution
```

Screens:

```text
SCR-071 to SCR-078
```

Seed and demonstrate:

```text
matched transaction
missing payment
amount mismatch
```

Acceptance:

- reconciliation produces repeatable result
- exceptions are explainable
- resolution is audited

---

## MILESTONE 11 — Refunds & Adjustments

Build:

```text
Refund
FinancialAdjustment
approval workflow
ledger debit
parent transaction linking
financial audit
```

Screens:

```text
SCR-079 to SCR-084
```

Acceptance:

- original ledger entry never changes
- refund/adjustment creates new entry
- unauthorized users cannot approve
- audit trail is complete

---

## MILESTONE 12 — Enforcement PWA

Build:

```text
PWA manifest
service worker
field layout
QR scan
ticket verification
driver search
vehicle search
operator search
inspection creation
```

Screens:

```text
SCR-085 to SCR-094
```

Acceptance:

- installable where browser supports it
- camera scan works on supported mobile browser
- safe verification data is displayed
- enforcement user cannot access finance admin features

---

## MILESTONE 13 — Incidents & Violations

Build:

```text
Inspection
Violation
Incident
evidence upload
status workflow
resolution
```

Screens:

```text
SCR-095 to SCR-103
```

Acceptance:

- evidence is stored securely
- actions are scope-aware
- resolution history is preserved

---

## MILESTONE 14 — Complaints + Notifications

Build:

```text
Complaint
ComplaintNote
public complaint form
assignment
resolution
database notifications
notification center
```

Screens:

```text
SCR-104 to SCR-110
```

Acceptance:

- public submission is rate-limited/validated
- help desk can assign/resolve
- notes retain author/time
- notifications work

---

## MILESTONE 15 — Reports & Exports

Build:

```text
Reports Center
Report Viewer
daily revenue
monthly revenue
revenue by park
revenue by LGA
transactions
reconciliation
vehicles
drivers
operators
incidents
complaints
CSV
Excel
PDF
```

Screens:

```text
SCR-111 to SCR-113
```

Acceptance:

- report totals match source records
- report access respects scopes
- large exports can queue
- generated files are protected

---

## MILESTONE 16 — Audit + Settings

Build:

```text
Spatie activity views
FinancialAuditLog
financial hash chain
general settings
branding
references
payment mode
verification config
security/session settings
```

Screens:

```text
SCR-114 to SCR-124
```

Acceptance:

- financial audit records are read-only
- settings changes are audited
- secrets are not exposed

---

## MILESTONE 17 — Final System Pages + UI Hardening

Build:

```text
403
404
419
500
maintenance
responsive fixes
empty states
loading states
accessibility improvements
consistent status styling
```

Screens:

```text
SCR-125 to SCR-129
```

---

## MILESTONE 18 — Demo Data + Presentation Workflow

Build:

```text
deterministic demo seed
demo reset command
known demo scenario
presentation accounts
presentation rehearsal
```

Required demo journey:

```text
Login
→ State Dashboard
→ LGA
→ Park
→ Driver / Vehicle
→ Issue Ticket
→ Demo Payment
→ Receipt
→ QR Verification
→ Revenue Dashboard
→ Reconciliation
→ Audit Trail
```

Field journey:

```text
Field Home
→ QR Scan
→ Verification
→ Inspection
→ Incident
```

---

## MILESTONE 19 — Security, Performance & QA

Checklist:

```text
authorization review
scope leakage tests
financial idempotency tests
file upload validation
rate limiting
query count review
pagination
indexes
N+1 review
error handling
backup
demo reset
mobile QA
browser QA
```

The demo is not ready until this milestone passes.

---

## MILESTONE 20 — Shared Hosting Deployment

Deploy:

```text
stable main branch
production frontend build
composer production dependencies
environment
MySQL migrations
seed demo data
storage configuration
cron
database queue
Cloudflare
SSL
```

Run smoke test:

```text
login
ticket issuance
demo payment
receipt
QR verification
dashboard
reconciliation
audit
field mobile
```

---

# 68. MVP Release Gate

The Phase 1 build is release-ready only if this workflow succeeds end-to-end:

```text
Authorized user
→ accesses correct scope
→ retrieves registered driver/vehicle
→ system validates assignment
→ resolves configured fee
→ issues ticket
→ processes demo payment
→ creates immutable ledger entry
→ creates receipt
→ verifies QR
→ updates revenue dashboard
→ includes transaction in reconciliation
→ records complete audit history
```

If any of these stages relies on manual database editing, the MVP is not complete.

---

# 69. Feature Ticket Template

Every development task should include:

```text
Title
Scope Reference
Screen ID
Domain
User Role
Preconditions
Business Rules
Acceptance Criteria
Authorization
Audit Requirement
Tests Required
Out of Scope
```

Example:

```text
Title:
Issue Digital Park Ticket

Scope:
Project Scope §19

Screen:
SCR-057

Domain:
Ticketing

Role:
Ticketing Officer / Collection Agent

Preconditions:
Active user
Accessible park
Valid active assignment
Active revenue head
Applicable fee configuration

Acceptance:
Creates unique ticket
Snapshots fee
Creates QR token
Does not create payment automatically
Audit event exists
```

This keeps implementation tied to the approved documents.

---

# 70. Coding Rule — No Business Logic Drift

Before writing a new service/table/page:

```text
1. Find the requirement in Project Scope.
2. Find the database entity in Database Schema.
3. Find the UI page in Screen Inventory.
4. Place the code in the correct domain.
```

If one is missing:

> stop and update the controlling document before coding.

---

# 71. Pull Request Checklist

Before merging a feature:

```text
[ ] linked to scope requirement
[ ] linked to screen ID where applicable
[ ] migrations reviewed
[ ] authorization implemented
[ ] server validation implemented
[ ] business logic in action/service
[ ] no business logic duplicated in React
[ ] audit requirement implemented
[ ] success flow tested
[ ] failure flow tested
[ ] scope leakage tested
[ ] responsive UI checked
[ ] no secrets committed
[ ] no N+1 obvious
[ ] docs updated if architecture changed
```

---

# 72. Non-Negotiable Financial Engineering Rules

1. Never use float for money.
2. Never update historical ticket amount because a fee changed.
3. Never make payment success depend only on frontend state.
4. Never create multiple ledger credits for one payment.
5. Never delete financial transactions.
6. Never alter the original ledger entry for refund/reversal.
7. Never expose payment secrets to React.
8. Never trust provider callback without verification.
9. Never reconcile purely by display totals.
10. Every financial exception must be traceable.

---

# 73. Non-Negotiable Authorization Rules

1. Sidebar visibility is not authorization.
2. Controllers/actions must authorize.
3. List queries must apply access scopes.
4. Public verification uses safe fields only.
5. Operator accounts cannot cross operator boundaries.
6. Park managers cannot cross park boundaries.
7. LGA administrators cannot cross LGA boundaries without explicit access.
8. Auditors are read-only unless separately granted a management permission.
9. Financial approvals should not be granted to low-level collection roles.
10. Super Admin access must still be logged.

---

# 74. Phase 1 Package Decisions

Locked/approved concepts:

```text
Laravel
React
TypeScript
Inertia.js
Tailwind CSS
Spatie Laravel Permission
Spatie Activitylog
MySQL
database queue
Cloudflare / R2 where configured
```

Additional libraries should only be installed if they solve a specific requirement.

Likely categories:

```text
QR generation
Excel export
PDF generation
charting
PWA tooling
```

Do not install broad packages "just in case."

---

# 75. Project README Requirements

The repository `README.md` should include:

```text
project overview
technology stack
local setup
environment variables
migration instructions
seeding instructions
demo accounts
frontend build
test commands
queue instructions
scheduler instructions
shared hosting deployment notes
demo reset instructions
scope document links
```

Controlling documents should live in:

```text
/docs/
  PROJECT_SCOPE_v1.0.md
  DATABASE_SCHEMA_v1.0.md
  SCREEN_INVENTORY_v1.0.md
  ARCHITECTURE_IMPLEMENTATION_PLAN_v1.0.md
```

---

# 76. Recommended First Commands

When beginning implementation:

```bash
composer create-project laravel/laravel ospm
cd ospm

# Install/configure React + TypeScript + Inertia through the selected
# Laravel starter/scaffolding approach.

composer require spatie/laravel-permission
composer require spatie/laravel-activitylog

npm install
npm run build
```

Then configure:

```text
MySQL
.env
authentication
RBAC
base React layout
```

Do not build business modules before the foundation is stable.

---

# 77. Immediate Development Backlog

The first implementation backlog should be:

```text
DEV-001 Bootstrap Laravel repository
DEV-002 Configure React + TypeScript + Inertia
DEV-003 Configure Tailwind and base brand tokens
DEV-004 Create AppLayout/AuthLayout/PublicLayout/FieldLayout
DEV-005 Configure authentication
DEV-006 Install/configure Spatie Permission
DEV-007 Install/configure Spatie Activitylog
DEV-008 Build user status middleware
DEV-009 Build UserAccessScopeService
DEV-010 Create LGA migration/model/seeder
DEV-011 Create Park migration/model
DEV-012 Create Route migration/model
DEV-013 Build LGA/Park/Route policies
DEV-014 Build LGA screens
DEV-015 Build Park screens
DEV-016 Build Route screens
DEV-017 Build state dashboard skeleton
```

Only after these are stable should the registry and ticketing milestones begin.

---

# 78. Final Architecture Baseline

The following architecture is now frozen for Version 1.0:

```text
Laravel Modular Monolith
        │
        ├── Domain Actions / Services / Queries
        │
        ├── Eloquent Models
        │
        ├── Policies + Spatie Permissions
        │
        ├── Inertia Web Controllers
        │
        ├── API-ready Services
        │
        └── Jobs / Events / Audit
                 │
                 ▼
        MySQL Demo Database
                 │
                 ▼
React + TypeScript + Inertia
                 │
                 ├── Desktop Government Admin
                 ├── Public Verification
                 └── Enforcement PWA
```

Deployment progression:

```text
PHASE 1
Shared Hosting + MySQL + Database Queue

                ↓

PHASE 2
VPS/Cloud + PostgreSQL + Redis + Horizon

                ↓

PHASE 3
Scaled Government Infrastructure
```

---

# 79. Development Governance Statement

This document is the official **Laravel Architecture & Implementation Plan v1.0**.

Together with:

1. Project Scope v1.0
2. Database Schema v1.0
3. Screen & Page Inventory v1.0

it forms the development baseline for the Osun State Park Management System digital implementation by Pinnacle Tech Hub.

No major architectural pattern, database domain, financial workflow, route family, user role, or Phase 1 feature should be added or changed without updating the relevant baseline document.

---

# 80. Development Starting Point

Development should now begin at:

## MILESTONE 0 — Project Bootstrap

followed by:

## MILESTONE 1 — Authentication + RBAC

No Park, Driver, Vehicle, Ticketing, Payment or Finance feature should be started before the foundation is working and tested.

The first goal is not to make the dashboard visually impressive.

The first goal is to establish a stable application foundation that every later module can trust.
