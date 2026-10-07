# OSUN STATE PARK MANAGEMENT SYSTEM
## Complete Screen & Page Inventory — Version 1.0

**Prepared by:** Pinnacle Tech Hub  
**Status:** Development Baseline  
**Frontend:** React + TypeScript + Inertia.js + Tailwind CSS  
**Primary Admin Experience:** Desktop-first, mobile responsive  
**Field Experience:** Mobile-first PWA  

---

# 1. Purpose

This document translates Project Scope v1.0 into the complete Phase 1 screen/page inventory.

Every Phase 1 page should exist in this inventory before development.

If a proposed screen is not listed here, it must be classified as:

```text
Future Enhancement
Scope Change Request
Phase 2
```

before being added.

The page IDs in this document should be referenced in development tickets, commits and QA notes where practical.

---

# 2. Application Shell

The authenticated desktop application uses one primary shell:

```text
┌─────────────────────────────────────────────────────┐
│ Top Bar: Search | Notifications | User              │
├──────────────┬──────────────────────────────────────┤
│ Sidebar      │ Page Content                         │
│ Navigation   │                                      │
│              │                                      │
│              │                                      │
└──────────────┴──────────────────────────────────────┘
```

The field PWA uses a separate mobile shell optimized for scanning and lookup.

---

# 3. Global UI Components

These are components, not standalone pages, but should be implemented once and reused.

```text
AppShell
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
ExportButton
PrintButton
```

---

# 4. Authentication & Account Screens

| ID | Screen | Route | React Page | Primary Users | Main Actions |
|---|---|---|---|---|---|
| SCR-001 | Login | `/login` | `Auth/Login.tsx` | All users | Sign in |
| SCR-002 | Forgot Password | `/forgot-password` | `Auth/ForgotPassword.tsx` | All users | Request reset |
| SCR-003 | Reset Password | `/reset-password/{token}` | `Auth/ResetPassword.tsx` | All users | Set new password |
| SCR-004 | My Profile | `/account/profile` | `Account/Profile.tsx` | Authenticated users | View/edit profile |
| SCR-005 | Account Security | `/account/security` | `Account/Security.tsx` | Authenticated users | Change password, sessions |
| SCR-006 | My Access | `/account/access` | `Account/Access.tsx` | Authenticated users | View roles/scopes |

Phase 1 does not require public self-registration.

---

# 5. Dashboard Screens

| ID | Screen | Route | React Page | Primary Users | Main Content |
|---|---|---|---|---|---|
| SCR-007 | State Dashboard | `/dashboard` | `Dashboard/State.tsx` | Super Admin, State Admin | Statewide KPIs, revenue, parks, registrations |
| SCR-008 | Executive Dashboard | `/executive/dashboard` | `Dashboard/Executive.tsx` | Executive Viewer | Read-only executive summary |
| SCR-009 | Revenue Dashboard | `/finance/dashboard` | `Dashboard/Revenue.tsx` | Finance Admin, Revenue Officer | Collections, status, exceptions |
| SCR-010 | LGA Dashboard | `/lgas/{lga}/dashboard` | `Dashboard/Lga.tsx` | LGA Admin, State roles | LGA-specific KPIs |
| SCR-011 | Park Dashboard | `/parks/{park}/dashboard` | `Dashboard/Park.tsx` | Park Manager, State/LGA roles | Park activity and revenue |

Dashboard data must come from Laravel services/queries, not calculations duplicated in React.

---

# 6. User & Access Administration

| ID | Screen | Route | React Page | Primary Users | Main Actions |
|---|---|---|---|---|---|
| SCR-012 | Users List | `/admin/users` | `Admin/Users/Index.tsx` | Super Admin | Search/filter users |
| SCR-013 | Create User | `/admin/users/create` | `Admin/Users/Create.tsx` | Super Admin | Create account |
| SCR-014 | User Detail | `/admin/users/{user}` | `Admin/Users/Show.tsx` | Super Admin | View role/scope/activity |
| SCR-015 | Edit User | `/admin/users/{user}/edit` | `Admin/Users/Edit.tsx` | Super Admin | Update status/profile |
| SCR-016 | Roles List | `/admin/roles` | `Admin/Roles/Index.tsx` | Super Admin | View roles |
| SCR-017 | Role Detail/Edit | `/admin/roles/{role}` | `Admin/Roles/Edit.tsx` | Super Admin | Assign permissions |
| SCR-018 | Permission Matrix | `/admin/permissions` | `Admin/Permissions/Matrix.tsx` | Super Admin | Compare role permissions |
| SCR-019 | User Access Scopes | `/admin/users/{user}/scopes` | `Admin/Users/Scopes.tsx` | Super Admin | Assign LGA/Park/Operator scopes |

The system must never rely on hiding sidebar links as the only permission control. Laravel authorization remains authoritative.

---

# 7. LGA Administration

| ID | Screen | Route | React Page | Primary Users | Main Actions |
|---|---|---|---|---|---|
| SCR-020 | LGA List | `/lgas` | `Lgas/Index.tsx` | State roles | Search/view LGAs |
| SCR-021 | Create LGA | `/lgas/create` | `Lgas/Create.tsx` | State Admin | Create LGA record |
| SCR-022 | LGA Detail | `/lgas/{lga}` | `Lgas/Show.tsx` | State/LGA roles | Summary, parks, stats |
| SCR-023 | Edit LGA | `/lgas/{lga}/edit` | `Lgas/Edit.tsx` | State Admin | Update details/status |

Tabs on LGA Detail:

```text
Overview
Parks
Operators
Drivers
Vehicles
Revenue
Recent Activity
```

---

# 8. Park Administration

| ID | Screen | Route | React Page | Primary Users | Main Actions |
|---|---|---|---|---|---|
| SCR-024 | Park List | `/parks` | `Parks/Index.tsx` | State/LGA roles | Search/filter parks |
| SCR-025 | Create Park | `/parks/create` | `Parks/Create.tsx` | State Admin | Register park |
| SCR-026 | Park Detail | `/parks/{park}` | `Parks/Show.tsx` | State/LGA/Park roles | View full park profile |
| SCR-027 | Edit Park | `/parks/{park}/edit` | `Parks/Edit.tsx` | State Admin, authorized LGA Admin | Update park/status |

Park Detail tabs:

```text
Overview
Operators
Drivers
Vehicles
Routes
Tickets
Revenue
Incidents
Activity
```

---

# 9. Route Administration

| ID | Screen | Route | React Page | Primary Users | Main Actions |
|---|---|---|---|---|---|
| SCR-028 | Route List | `/routes` | `Routes/Index.tsx` | State/LGA roles | Search routes |
| SCR-029 | Create Route | `/routes/create` | `Routes/Create.tsx` | State Admin | Create route |
| SCR-030 | Route Detail | `/routes/{route}` | `Routes/Show.tsx` | State/LGA roles | Parks/operators using route |
| SCR-031 | Edit Route | `/routes/{route}/edit` | `Routes/Edit.tsx` | State Admin | Update route |

---

# 10. Transport Operator Screens

| ID | Screen | Route | React Page | Primary Users | Main Actions |
|---|---|---|---|---|---|
| SCR-032 | Operator List | `/operators` | `Operators/Index.tsx` | State/LGA/Park roles | Search/filter operators |
| SCR-033 | Register Operator | `/operators/create` | `Operators/Create.tsx` | Authorized admin | Register operator |
| SCR-034 | Operator Detail | `/operators/{operator}` | `Operators/Show.tsx` | Authorized roles, Operator user | View profile/vehicles/drivers |
| SCR-035 | Edit Operator | `/operators/{operator}/edit` | `Operators/Edit.tsx` | Authorized admin | Update/approve/suspend |

Operator Detail tabs:

```text
Overview
Parks
Routes
Drivers
Vehicles
Documents
Tickets
Activity
```

---

# 11. Driver Registry Screens

| ID | Screen | Route | React Page | Primary Users | Main Actions |
|---|---|---|---|---|---|
| SCR-036 | Driver List | `/drivers` | `Drivers/Index.tsx` | Authorized roles | Search/filter drivers |
| SCR-037 | Register Driver | `/drivers/create` | `Drivers/Create.tsx` | Authorized admin | Create driver record |
| SCR-038 | Driver Detail | `/drivers/{driver}` | `Drivers/Show.tsx` | Authorized roles | Profile, status, assignments |
| SCR-039 | Edit Driver | `/drivers/{driver}/edit` | `Drivers/Edit.tsx` | Authorized admin | Update/approve/suspend |

Driver Detail tabs:

```text
Overview
Current Assignment
Assignment History
Documents
Tickets
Inspections
Incidents
Activity
```

---

# 12. Vehicle Registry Screens

| ID | Screen | Route | React Page | Primary Users | Main Actions |
|---|---|---|---|---|---|
| SCR-040 | Vehicle List | `/vehicles` | `Vehicles/Index.tsx` | Authorized roles | Search plate/status/type |
| SCR-041 | Register Vehicle | `/vehicles/create` | `Vehicles/Create.tsx` | Authorized admin | Create vehicle |
| SCR-042 | Vehicle Detail | `/vehicles/{vehicle}` | `Vehicles/Show.tsx` | Authorized roles | Vehicle profile/history |
| SCR-043 | Edit Vehicle | `/vehicles/{vehicle}/edit` | `Vehicles/Edit.tsx` | Authorized admin | Update/approve/suspend |

Vehicle Detail tabs:

```text
Overview
Current Assignment
Assignment History
Documents
Tickets
Inspections
Incidents
Activity
```

---

# 13. Assignment Management

| ID | Screen | Route | React Page | Primary Users | Main Actions |
|---|---|---|---|---|---|
| SCR-044 | Assignment List | `/assignments` | `Assignments/Index.tsx` | State/LGA/Park roles | View active/history |
| SCR-045 | Create/Change Assignment | `/assignments/create` | `Assignments/Create.tsx` | Authorized admin | Link driver, vehicle, operator, park, route |
| SCR-046 | Assignment Detail | `/assignments/{assignment}` | `Assignments/Show.tsx` | Authorized roles | View assignment history |
| SCR-047 | End Assignment | `/assignments/{assignment}/end` | `Assignments/End.tsx` | Authorized admin | Close active assignment |

Assignment changes must never overwrite historical assignment records.

---

# 14. Revenue Head Screens

| ID | Screen | Route | React Page | Primary Users | Main Actions |
|---|---|---|---|---|---|
| SCR-048 | Revenue Heads | `/revenue-heads` | `RevenueHeads/Index.tsx` | Finance Admin, State Admin | View fee categories |
| SCR-049 | Create Revenue Head | `/revenue-heads/create` | `RevenueHeads/Create.tsx` | Finance Admin | Create category |
| SCR-050 | Revenue Head Detail | `/revenue-heads/{revenueHead}` | `RevenueHeads/Show.tsx` | Finance roles | View configurations/history |
| SCR-051 | Edit Revenue Head | `/revenue-heads/{revenueHead}/edit` | `RevenueHeads/Edit.tsx` | Finance Admin | Update metadata/status |

---

# 15. Fee Configuration Screens

| ID | Screen | Route | React Page | Primary Users | Main Actions |
|---|---|---|---|---|---|
| SCR-052 | Fee Configurations | `/fee-configurations` | `Fees/Index.tsx` | Finance Admin, State Admin | Filter active/history |
| SCR-053 | Create Fee Configuration | `/fee-configurations/create` | `Fees/Create.tsx` | Finance Admin | Set amount/scope/effective date |
| SCR-054 | Fee Configuration Detail | `/fee-configurations/{fee}` | `Fees/Show.tsx` | Finance roles | Review configuration |
| SCR-055 | Edit/Deactivate Fee | `/fee-configurations/{fee}/edit` | `Fees/Edit.tsx` | Finance Admin | Future config/status only |

The UI must warn that changing a fee does not alter historical tickets.

---

# 16. Ticketing Screens

| ID | Screen | Route | React Page | Primary Users | Main Actions |
|---|---|---|---|---|---|
| SCR-056 | Ticket List | `/tickets` | `Tickets/Index.tsx` | Ticketing/Finance/Admin roles | Search/filter tickets |
| SCR-057 | Issue Ticket | `/tickets/create` | `Tickets/Create.tsx` | Ticketing Officer, Collection Agent | Select vehicle/driver/revenue head |
| SCR-058 | Ticket Detail | `/tickets/{ticket}` | `Tickets/Show.tsx` | Authorized roles | View ticket/payment/QR |
| SCR-059 | Cancel Ticket | `/tickets/{ticket}/cancel` | `Tickets/Cancel.tsx` | Authorized supervisor | Cancel eligible unpaid ticket |
| SCR-060 | Ticket Print View | `/tickets/{ticket}/print` | `Tickets/Print.tsx` | Authorized roles | Print ticket summary |

Ticket issuance flow:

```text
Find vehicle/driver
→ validate active assignment
→ resolve applicable fee
→ show confirmation
→ issue ticket
→ proceed to payment
```

---

# 17. Payment & Receipt Screens

| ID | Screen | Route | React Page | Primary Users | Main Actions |
|---|---|---|---|---|---|
| SCR-061 | Payment List | `/payments` | `Payments/Index.tsx` | Finance/Revenue roles | Search/filter payment attempts |
| SCR-062 | Payment Detail | `/payments/{payment}` | `Payments/Show.tsx` | Finance roles | Provider metadata/status |
| SCR-063 | Demo Payment | `/tickets/{ticket}/pay` | `Payments/DemoPay.tsx` | Ticketing/Collection | Simulate success/failure/pending |
| SCR-064 | Receipt View | `/receipts/{receipt}` | `Receipts/Show.tsx` | Authorized roles | View receipt |
| SCR-065 | Receipt Print | `/receipts/{receipt}/print` | `Receipts/Print.tsx` | Authorized roles | Print receipt |
| SCR-066 | Receipt PDF | `/receipts/{receipt}/pdf` | Server-generated | Authorized roles | Download/share PDF |

The demo payment screen must clearly indicate DEMO MODE.

---

# 18. Public Ticket Verification

| ID | Screen | Route | React Page | Users | Main Actions |
|---|---|---|---|---|---|
| SCR-067 | Public Ticket Verification | `/verify/ticket/{token}` | `Public/TicketVerification.tsx` | Public / Enforcement | Verify authenticity/status |
| SCR-068 | Public Receipt Verification | `/verify/receipt/{token}` | `Public/ReceiptVerification.tsx` | Public / Enforcement | Verify payment receipt |

Only safe fields may be displayed.

Do not expose residential addresses, phone numbers, identity documents or internal audit metadata.

---

# 19. Financial Ledger Screens

| ID | Screen | Route | React Page | Primary Users | Main Actions |
|---|---|---|---|---|---|
| SCR-069 | Financial Ledger | `/finance/ledger` | `Finance/Ledger/Index.tsx` | Finance Admin, Auditor | Search immutable ledger |
| SCR-070 | Ledger Entry Detail | `/finance/ledger/{transaction}` | `Finance/Ledger/Show.tsx` | Finance Admin, Auditor | Trace ticket/payment/settlement |

Ledger pages are read-oriented. Original ledger entries are not editable.

---

# 20. Settlement Screens

| ID | Screen | Route | React Page | Primary Users | Main Actions |
|---|---|---|---|---|---|
| SCR-071 | Settlement List | `/finance/settlements` | `Finance/Settlements/Index.tsx` | Finance Admin | View provider settlement batches |
| SCR-072 | Settlement Detail | `/finance/settlements/{settlement}` | `Finance/Settlements/Show.tsx` | Finance Admin, Auditor | View included transactions |
| SCR-073 | Demo Settlement Create | `/finance/settlements/create` | `Finance/Settlements/Create.tsx` | Finance Admin | Simulate settlement batch |

This is a demo/pilot-ready function, not a claim of live Government treasury integration.

---

# 21. Reconciliation Screens

| ID | Screen | Route | React Page | Primary Users | Main Actions |
|---|---|---|---|---|---|
| SCR-074 | Reconciliation Overview | `/finance/reconciliation` | `Finance/Reconciliation/Dashboard.tsx` | Finance Admin, Auditor | Matched/exceptions KPIs |
| SCR-075 | Reconciliation Runs | `/finance/reconciliation/runs` | `Finance/Reconciliation/Index.tsx` | Finance Admin | View runs |
| SCR-076 | Start Reconciliation | `/finance/reconciliation/runs/create` | `Finance/Reconciliation/Create.tsx` | Finance Admin | Select period/scope |
| SCR-077 | Reconciliation Run Detail | `/finance/reconciliation/runs/{run}` | `Finance/Reconciliation/Show.tsx` | Finance/Admin/Auditor | Items and summary |
| SCR-078 | Reconciliation Exception | `/finance/reconciliation/items/{item}` | `Finance/Reconciliation/Exception.tsx` | Finance Admin | Review/resolve exception |

The exception screen should show:

```text
Ticket
Payment
Provider Reference
Ledger Entry
Expected Amount
Actual Amount
Difference
Exception Type
Audit Timeline
Resolution Notes
```

---

# 22. Refund & Adjustment Screens

| ID | Screen | Route | React Page | Primary Users | Main Actions |
|---|---|---|---|---|---|
| SCR-079 | Refund Requests | `/finance/refunds` | `Finance/Refunds/Index.tsx` | Finance Admin | Review requests |
| SCR-080 | Refund Detail | `/finance/refunds/{refund}` | `Finance/Refunds/Show.tsx` | Finance Admin, Auditor | Approve/reject/process demo |
| SCR-081 | Request Refund | `/payments/{payment}/refund` | `Finance/Refunds/Create.tsx` | Authorized supervisor | Submit reason/amount |
| SCR-082 | Adjustments | `/finance/adjustments` | `Finance/Adjustments/Index.tsx` | Finance Admin, Auditor | View requests |
| SCR-083 | Adjustment Detail | `/finance/adjustments/{adjustment}` | `Finance/Adjustments/Show.tsx` | Finance Admin | Approve/reject |
| SCR-084 | Request Adjustment | `/finance/adjustments/create` | `Finance/Adjustments/Create.tsx` | Authorized finance user | Submit adjustment |

Every approved refund/adjustment must create a new ledger entry.

---

# 23. Enforcement PWA Screens

These screens use a separate mobile-first layout.

| ID | Screen | Route | React Page | Primary Users | Main Actions |
|---|---|---|---|---|---|
| SCR-085 | Field Home | `/field` | `Field/Home.tsx` | Enforcement Officer | Scan/search shortcuts |
| SCR-086 | QR Scanner | `/field/scan` | `Field/Scan.tsx` | Enforcement Officer | Scan ticket/receipt |
| SCR-087 | Verification Result | `/field/verify/{token}` | `Field/VerifyResult.tsx` | Enforcement Officer | View validity/compliance |
| SCR-088 | Driver Lookup | `/field/drivers` | `Field/Drivers/Search.tsx` | Enforcement Officer | Search driver |
| SCR-089 | Driver Quick View | `/field/drivers/{driver}` | `Field/Drivers/Show.tsx` | Enforcement Officer | Status/assignment |
| SCR-090 | Vehicle Lookup | `/field/vehicles` | `Field/Vehicles/Search.tsx` | Enforcement Officer | Search registration |
| SCR-091 | Vehicle Quick View | `/field/vehicles/{vehicle}` | `Field/Vehicles/Show.tsx` | Enforcement Officer | Status/assignment |
| SCR-092 | Operator Lookup | `/field/operators` | `Field/Operators/Search.tsx` | Enforcement Officer | Search operator |
| SCR-093 | Operator Quick View | `/field/operators/{operator}` | `Field/Operators/Show.tsx` | Enforcement Officer | Basic status |
| SCR-094 | Create Field Inspection | `/field/inspections/create` | `Field/Inspections/Create.tsx` | Enforcement Officer | Record inspection |
| SCR-095 | Record Incident | `/field/incidents/create` | `Field/Incidents/Create.tsx` | Enforcement Officer | Report incident/evidence |

Offline-friendly behavior should focus on the highest-value field tasks only.

Do not promise full offline financial processing in Phase 1.

---

# 24. Inspection & Violation Administration

| ID | Screen | Route | React Page | Primary Users | Main Actions |
|---|---|---|---|---|---|
| SCR-096 | Inspection List | `/inspections` | `Inspections/Index.tsx` | State/LGA/Enforcement roles | Search inspections |
| SCR-097 | Inspection Detail | `/inspections/{inspection}` | `Inspections/Show.tsx` | Authorized roles | Evidence/result |
| SCR-098 | Violations List | `/violations` | `Violations/Index.tsx` | State/LGA/Enforcement roles | Search violations |
| SCR-099 | Violation Detail | `/violations/{violation}` | `Violations/Show.tsx` | Authorized roles | Resolve/status |

---

# 25. Incident Administration

| ID | Screen | Route | React Page | Primary Users | Main Actions |
|---|---|---|---|---|---|
| SCR-100 | Incident List | `/incidents` | `Incidents/Index.tsx` | Authorized operational roles | Search/filter |
| SCR-101 | Create Incident | `/incidents/create` | `Incidents/Create.tsx` | Authorized roles | Record incident |
| SCR-102 | Incident Detail | `/incidents/{incident}` | `Incidents/Show.tsx` | Authorized roles | Review/evidence/resolution |
| SCR-103 | Edit Incident Status | `/incidents/{incident}/manage` | `Incidents/Manage.tsx` | Supervisor roles | Escalate/resolve/close |

---

# 26. Complaint Screens

| ID | Screen | Route | React Page | Primary Users | Main Actions |
|---|---|---|---|---|---|
| SCR-104 | Complaint List | `/complaints` | `Complaints/Index.tsx` | Help Desk, Admin roles | Search/filter queue |
| SCR-105 | Register Complaint | `/complaints/create` | `Complaints/Create.tsx` | Help Desk | Record complaint |
| SCR-106 | Complaint Detail | `/complaints/{complaint}` | `Complaints/Show.tsx` | Help Desk/Supervisor | Assign, notes, resolve |
| SCR-107 | Public Complaint Form | `/public/complaints` | `Public/ComplaintForm.tsx` | Public | Submit complaint |
| SCR-108 | Complaint Submitted | `/public/complaints/success` | `Public/ComplaintSuccess.tsx` | Public | Show reference |

Anonymous complaint functionality is not required in Phase 1.

---

# 27. Notifications

| ID | Screen | Route | React Page | Primary Users | Main Actions |
|---|---|---|---|---|---|
| SCR-109 | Notification Center | `/notifications` | `Notifications/Index.tsx` | Authenticated users | Read/mark read |
| SCR-110 | Notification Preferences | `/account/notifications` | `Account/Notifications.tsx` | Authenticated users | Local preference settings |

External SMS/email provider configuration is handled under System Settings.

---

# 28. Reports

Use a central report framework rather than building unrelated report UIs.

| ID | Screen | Route | React Page | Primary Users | Main Actions |
|---|---|---|---|---|---|
| SCR-111 | Reports Center | `/reports` | `Reports/Index.tsx` | Authorized roles | Select report |
| SCR-112 | Report Viewer | `/reports/{reportType}` | `Reports/Show.tsx` | Authorized roles | Filter/view/export |
| SCR-113 | Saved/Generated Exports | `/reports/exports` | `Reports/Exports.tsx` | Authorized roles | Download completed files |

Supported Phase 1 report types:

```text
daily-revenue
monthly-revenue
revenue-by-park
revenue-by-lga
transactions
reconciliation
vehicles
drivers
operators
incidents
complaints
```

Do not create 11 separate page components unless a report genuinely needs a unique UI.

---

# 29. Audit Screens

| ID | Screen | Route | React Page | Primary Users | Main Actions |
|---|---|---|---|---|---|
| SCR-114 | Activity Audit Log | `/audit/activity` | `Audit/ActivityIndex.tsx` | Auditor, Super Admin | Search normal events |
| SCR-115 | Activity Audit Detail | `/audit/activity/{activity}` | `Audit/ActivityShow.tsx` | Auditor | View event |
| SCR-116 | Financial Audit Log | `/audit/financial` | `Audit/FinancialIndex.tsx` | Auditor, Finance Admin | Search critical events |
| SCR-117 | Financial Audit Detail | `/audit/financial/{event}` | `Audit/FinancialShow.tsx` | Auditor | Trace financial event/hash |

Financial audit pages are read-only.

---

# 30. System Settings

| ID | Screen | Route | React Page | Primary Users | Main Actions |
|---|---|---|---|---|---|
| SCR-118 | General Settings | `/settings/general` | `Settings/General.tsx` | Super Admin | System name/timezone/currency |
| SCR-119 | Branding Settings | `/settings/branding` | `Settings/Branding.tsx` | Super Admin | Government logo / powered-by |
| SCR-120 | Reference Settings | `/settings/references` | `Settings/References.tsx` | Super Admin | Ticket/receipt prefixes |
| SCR-121 | Payment Settings | `/settings/payments` | `Settings/Payments.tsx` | Super Admin, Finance Admin | Provider mode/non-secret config |
| SCR-122 | Notification Settings | `/settings/notifications` | `Settings/Notifications.tsx` | Super Admin | Email/SMS configuration |
| SCR-123 | QR & Verification Settings | `/settings/verification` | `Settings/Verification.tsx` | Super Admin | Public base URL/expiry |
| SCR-124 | Session & Security Settings | `/settings/security` | `Settings/Security.tsx` | Super Admin | Session timeout/security options |

Secret API keys must not be displayed back in plaintext.

---

# 31. Public/System Screens

| ID | Screen | Route | React Page | Users | Purpose |
|---|---|---|---|---|---|
| SCR-125 | 403 Forbidden | `/403` / framework | `Errors/403.tsx` | Any | Permission denied |
| SCR-126 | 404 Not Found | framework | `Errors/404.tsx` | Any | Unknown resource |
| SCR-127 | 419 Session Expired | framework | `Errors/419.tsx` | Auth users | Session/CSRF |
| SCR-128 | 500 Error | framework | `Errors/500.tsx` | Any | Friendly error |
| SCR-129 | Maintenance | framework | `Errors/Maintenance.tsx` | Any | Maintenance notice |

---

# 32. Sidebar Navigation

Recommended desktop sidebar:

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

Navigation items must be permission-aware.

Unauthorized routes must still be protected server-side.

---

# 33. Role Navigation Matrix

Legend:

```text
✓ primary access
V view/read access
— hidden/no access by default
```

| Module | Super Admin | State Admin | Executive | Finance Admin | Revenue Officer | Auditor | LGA Admin | Park Manager | Ticketing | Collection | Enforcement | Help Desk | Operator |
|---|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|
| State Dashboard | ✓ | ✓ | V | V | V | V | — | — | — | — | — | — | — |
| Executive Dashboard | ✓ | V | ✓ | V | — | V | — | — | — | — | — | — | — |
| LGAs | ✓ | ✓ | V | V | V | V | ✓ scoped | — | — | — | — | — | — |
| Parks | ✓ | ✓ | V | V | V | V | ✓ scoped | ✓ scoped | V scoped | V scoped | V scoped | V scoped | V scoped |
| Operators | ✓ | ✓ | V | V | V | V | ✓ scoped | ✓ scoped | V | V | V | V | ✓ own |
| Drivers | ✓ | ✓ | — | V | V | V | ✓ scoped | ✓ scoped | V | V | V | — | V own |
| Vehicles | ✓ | ✓ | — | V | V | V | ✓ scoped | ✓ scoped | V | V | V | — | V own |
| Tickets | ✓ | ✓ | V | ✓ | ✓ | V | ✓ scoped | ✓ scoped | ✓ | ✓ | V | — | V own |
| Payments | ✓ | V | V | ✓ | ✓ | V | V scoped | V scoped | V | ✓ | — | — | V own |
| Revenue Config | ✓ | V | — | ✓ | V | V | — | — | — | — | — | — | — |
| Ledger | ✓ | V | V | ✓ | V | ✓ | V scoped | — | — | — | — | — | — |
| Reconciliation | ✓ | V | V | ✓ | V | ✓ | V scoped | — | — | — | — | — | — |
| Enforcement | ✓ | V | — | — | — | V | V scoped | V scoped | — | — | ✓ | — | — |
| Incidents | ✓ | ✓ | V | — | — | V | ✓ scoped | ✓ scoped | — | — | ✓ | V | V own |
| Complaints | ✓ | V | — | — | — | V | V scoped | V scoped | — | — | V | ✓ | V own |
| Reports | ✓ | ✓ | V | ✓ | ✓ | ✓ | ✓ scoped | V scoped | V scoped | V scoped | V scoped | V scoped | V own |
| Audit | ✓ | V | — | V | — | ✓ | — | — | — | — | — | — | — |
| Users/RBAC | ✓ | limited | — | — | — | — | — | — | — | — | — | — | — |
| Settings | ✓ | limited | — | limited | — | V | — | — | — | — | — | — | — |

Final permissions are enforced through Spatie Permission and Laravel policies.

---

# 34. Standard List Page Pattern

Every high-volume list page should use the same pattern:

```text
Page Title                     [Primary Action]

Search
Filters
Date Range (where relevant)
Status
Scope Filters

------------------------------------------------------
| Column | Column | Column | Status | Actions |
------------------------------------------------------

Pagination
```

Required list behaviors:

- server-side pagination
- server-side filtering
- sortable approved columns
- empty state
- loading state
- error state
- permission-aware actions
- export only where authorized

---

# 35. Standard Detail Page Pattern

```text
Breadcrumbs
Title / Reference
Status Badge
Primary Actions

Summary Cards

Tabs:
  Overview
  Related Data
  Documents
  Activity
```

Do not create duplicate "view" pages that show the same information with a different visual style.

---

# 36. Ticket Issuance UX

Ticket issuance must be optimized for speed.

Recommended steps:

```text
STEP 1
Search vehicle / driver / operator

STEP 2
Confirm active assignment
Park
Route
Operator
Driver
Vehicle

STEP 3
Select revenue head

STEP 4
Backend resolves applicable fee

STEP 5
Review amount and details

STEP 6
Issue ticket

STEP 7
Proceed to payment
```

The amount returned by Laravel is authoritative.

React must not calculate the final fee independently.

---

# 37. Demo Payment UX

The demo screen should show a prominent badge:

```text
DEMO PAYMENT MODE
No real funds will be charged.
```

Presenter options:

```text
Simulate Successful Payment
Simulate Failed Payment
Simulate Pending Payment
```

Reversal must be demonstrated through a controlled supervisor/finance workflow, not a generic button available to collectors.

---

# 38. QR Verification UX

Valid result:

```text
✓ VALID

Ticket Reference
Vehicle
Park
Revenue Type
Amount
Issued At
Payment Status
```

Invalid result:

```text
✕ INVALID OR NOT FOUND
```

Reversed/expired:

```text
! NOT VALID FOR USE
Reason: Reversed / Expired / Cancelled
```

The QR result must be visually obvious to a field officer.

---

# 39. Reconciliation UX

The reconciliation dashboard should prioritize:

```text
Total Expected
Total Recorded
Matched
Exceptions
Difference
```

Exceptions should be filterable by:

```text
Amount Mismatch
Missing Payment
Missing Ticket
Duplicate Provider Reference
Missing Settlement
Reversal Exception
```

The resolution action requires a reason/note and must be audited.

---

# 40. Responsive Rules

## Desktop

Primary admin workflow.

Target:

```text
>= 1280px ideal
```

## Tablet

All admin pages must remain usable.

## Mobile

Admin pages remain functional but may stack cards/tables.

## Enforcement PWA

Mobile-first.

Large touch targets and minimal typing.

---

# 41. Accessibility Requirements

Phase 1 UI should provide:

- visible labels
- keyboard navigation on desktop
- meaningful focus states
- sufficient text contrast
- status indicators that do not depend only on color
- accessible form errors
- buttons with text/labels
- table headers
- responsive zoom behavior

---

# 42. Common Status Colors

The implementation may choose the exact visual tokens, but statuses must be semantically consistent.

```text
Active / Paid / Successful / Reconciled → success
Pending / Under Review → warning
Failed / Suspended / Exception → danger
Inactive / Expired / Cancelled → neutral
Information / Draft → info
```

Do not use different colors for the same status meaning on different pages.

---

# 43. Loading, Empty & Error States

Every data-driven screen must deliberately implement:

```text
loading
empty
error
success
```

Example:

Driver list with no records:

> No drivers found for the selected filters.

Do not leave blank tables without explanation.

---

# 44. Destructive Action Rules

Destructive/high-risk operations must require confirmation.

Examples:

```text
Suspend Driver
Suspend Vehicle
Cancel Ticket
Reverse Payment
Approve Refund
Approve Adjustment
Deactivate Fee
Deactivate Park
```

Financial actions additionally require:

- permission check
- reason
- audit log
- backend validation

---

# 45. Search Requirements

Global or module search should support relevant references:

```text
Driver Name
Phone
Driver Number
Vehicle Registration Number
Operator Number
Park Name
Ticket Reference
Payment Reference
Receipt Number
Complaint Reference
Incident Reference
```

Search must be server-side for high-volume tables.

---

# 46. Phase 1 Screen Count

The baseline contains:

```text
129 identified routes/screens
```

This count includes:

- full desktop admin screens
- public verification screens
- mobile field PWA screens
- error/system pages
- detail and workflow pages

Not every page has equal development complexity.

Reusable layouts/components should prevent duplication.

---

# 47. Development Grouping

Recommended frontend delivery sequence:

## Sprint Group A — Foundation

```text
SCR-001 to SCR-019
```

Authentication, dashboard shell, users, roles, scopes.

## Sprint Group B — Master Data

```text
SCR-020 to SCR-031
```

LGAs, parks, routes.

## Sprint Group C — Transport Registry

```text
SCR-032 to SCR-047
```

Operators, drivers, vehicles, assignments.

## Sprint Group D — Revenue & Ticketing

```text
SCR-048 to SCR-068
```

Revenue heads, fees, tickets, payments, receipts, public verification.

## Sprint Group E — Finance

```text
SCR-069 to SCR-084
```

Ledger, settlement, reconciliation, refunds, adjustments.

## Sprint Group F — Enforcement & Operations

```text
SCR-085 to SCR-110
```

Field PWA, inspections, violations, incidents, complaints, notifications.

## Sprint Group G — Reporting, Audit & Settings

```text
SCR-111 to SCR-129
```

Reports, audits, configuration and system pages.

---

# 48. Demo Presentation Navigation

For the Government demonstration, the presenter should be able to follow this exact route:

```text
SCR-001 Login
      ↓
SCR-007 State Dashboard
      ↓
SCR-020 LGA List
      ↓
SCR-022 LGA Detail
      ↓
SCR-024 Park List
      ↓
SCR-026 Park Detail
      ↓
SCR-038 Driver Detail / SCR-042 Vehicle Detail
      ↓
SCR-057 Issue Ticket
      ↓
SCR-063 Demo Payment
      ↓
SCR-064 Receipt
      ↓
SCR-067 Public Verification
      ↓
SCR-009 Revenue Dashboard
      ↓
SCR-077 Reconciliation Run
      ↓
SCR-078 Resolve/Review Exception
      ↓
SCR-116 Financial Audit
```

A second short demonstration can show:

```text
SCR-085 Field Home
      ↓
SCR-086 Scan QR
      ↓
SCR-087 Verification Result
      ↓
SCR-094 Field Inspection
      ↓
SCR-095 Report Incident
```

---

# 49. Pages Explicitly Not Required in Phase 1

Do not build pages for:

```text
Passenger booking
Ride hailing
Bus seat reservation
Driver navigation
Live GPS fleet tracking
Payroll
HR
Procurement
Union membership administration
Loans
Insurance marketplace
Digital wallet
Cryptocurrency
AI assistant
AI forecasting
Biometrics
Facial recognition
ANPR camera management
CCTV monitoring
Smart gates
Native Android/iOS applications
```

These are outside Project Scope v1.0.

---

# 50. Frontend Definition of Done

A screen is complete only when:

- route exists
- Laravel authorization is enforced
- page renders with real backend data or approved demo data
- loading state exists
- empty state exists
- validation errors display correctly
- error state is handled
- mobile/tablet behavior is acceptable
- actions respect permissions
- data tables paginate server-side where necessary
- related entities link correctly
- relevant audit events are generated
- success/failure flows have been tested

---

# SCREEN INVENTORY BASELINE

This document is the official **Screen & Page Inventory v1.0**.

If a new page is introduced during Phase 1 development, it must be tied to an existing scope requirement and this inventory must be versioned accordingly.
