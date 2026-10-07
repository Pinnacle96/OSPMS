# OSUN STATE PARK MANAGEMENT SYSTEM
## Digital Implementation Platform — Project Scope & Development Specification

**Prepared by:** Pinnacle Tech Hub  
**Document Version:** 1.0  
**Status:** Development Baseline  
**Primary Purpose:** Demo / Government Presentation, built on a production-ready foundation

---

## 1. Purpose of This Document

This document defines the complete functional and technical scope for the digital platform being developed by Pinnacle Tech Hub to demonstrate how technology can support the **Osun State Park Management System**.

It will serve as the project's **single source of truth** throughout development.

Development decisions must remain within this scope.

Any feature, workflow, integration or architectural change not defined here must first be reviewed and deliberately added to the scope before implementation.

The purpose is to prevent scope creep, inconsistent workflows, duplicated features, unnecessary complexity, premature infrastructure decisions, conflicting database structures and development drift.

---

## 2. Project Definition

The project is a centralized digital platform for administering motor parks, registered commercial transport operators, drivers, vehicles, digital tickets, payments, receipts, revenue monitoring, reconciliation and compliance activities.

The platform will demonstrate how the Osun State Government can obtain visibility into:

- Registered parks
- Registered operators
- Registered drivers
- Registered commercial vehicles
- Routes
- Approved ticket/fee categories
- Digital transactions
- Payments
- Receipts
- Revenue
- Reconciliation
- Compliance
- Enforcement activities
- Incidents
- Complaints
- Reports
- Audit history

The system is **not intended to replace Government authority**.

Government remains responsible for policy, regulation, approved fees, enforcement authority, institutional structure, public revenue and data ownership.

Pinnacle Tech Hub provides the technology infrastructure.

---

## 3. Product Vision

The system should make it possible to trace a transport-related transaction from the participant who initiated it through payment and eventual reconciliation.

The core relationship is:

**LGA → Park → Operator → Vehicle → Driver → Route → Ticket → Payment → Receipt → Reconciliation**

The core financial principle is:

> **Every authorized revenue transaction must be identifiable, traceable and auditable.**

---

## 4. Project Phases

| Phase | Purpose | Infrastructure |
|---|---|---|
| Phase 1 | Demo / Presentation | Shared hosting |
| Phase 2 | Government Pilot | VPS / Cloud |
| Phase 3 | Statewide Deployment | Production cloud/VPS architecture |

The immediate development target is **Phase 1**.

However, the application architecture and database must be designed so the same system can move into Phase 2 without being rewritten.

---

## 5. Phase 1 Objective

The demo must convincingly demonstrate the entire operational chain:

**Registration → Park Management → Ticket Generation → Payment → Digital Receipt → QR Verification → Revenue Dashboard → Reconciliation → Enforcement Verification → Reporting**

The demo must feel like a functioning government platform rather than a collection of mock screens.

Synthetic/demo records will be used.

No genuine citizen or Government financial data is required for the demo.

---

## 6. Approved Technology Stack

| Component | Technology |
|---|---|
| Backend | Laravel |
| Architecture | Modular Monolith |
| Frontend | React |
| Language | TypeScript |
| Laravel/React Bridge | Inertia.js |
| UI | Tailwind CSS |
| Demo Database | MySQL 8 |
| Production Database | PostgreSQL |
| Authentication | Laravel authentication + Sanctum where API access is required |
| Authorization | Spatie Laravel Permission |
| Audit Logging | Spatie Activitylog + custom financial audit records |
| Demo Queues | Database Queue |
| Production Queues | Redis + Laravel Horizon |
| Demo Cache | File/Database |
| Production Cache | Redis |
| File Storage | Cloudflare R2 / S3-compatible storage |
| Charts | Recharts |
| Field Application | React PWA |
| Native Mobile | Flutter only if later required |
| Edge/CDN | Cloudflare |
| Version Control | GitHub |
| Demo Hosting | Shared hosting |
| Production Hosting | VPS / Cloud |
| Production Web Server | Nginx + PHP-FPM |
| CI/CD | GitHub Actions after pilot approval |

No alternative frontend/backend framework should be introduced without a deliberate architectural decision.

---

## 7. Architecture Rule

The system will begin as a **modular monolith**.

It will **not** begin as microservices.

Conceptual backend domains:

```text
Identity
Parks
LGAs
Operators
Drivers
Vehicles
Routes
Ticketing
Revenue
Payments
Reconciliation
Enforcement
Incidents
Complaints
Notifications
Reporting
Audit
System Administration
```

Each domain should remain logically separated even though they exist inside one Laravel application.

This allows individual services to be extracted later if scale requires it.

---

## 8. User Roles

| Role | Primary Responsibility |
|---|---|
| Super Administrator | Full system administration |
| State Administrator | Statewide operational administration |
| Executive Viewer | Read-only executive dashboard |
| Finance Administrator | Revenue and reconciliation oversight |
| Revenue Officer | Revenue monitoring |
| Auditor | Audit and transaction inspection |
| LGA Administrator | LGA-level administration |
| Park Manager | Individual park administration |
| Ticketing Officer | Ticket generation |
| Collection Agent | Approved payment/ticketing operations |
| Enforcement Officer | Verification and field enforcement |
| Help Desk Officer | Complaint/support management |
| Transport Operator | Operator-level access |

Roles must use granular permissions rather than hardcoded role checks.

Example permissions:

```text
view_state_dashboard
view_state_revenue
view_lga_revenue
view_park_revenue

create_driver
edit_driver
approve_driver
suspend_driver

create_vehicle
approve_vehicle

create_operator
approve_operator

issue_ticket
verify_ticket

view_payment
reverse_transaction
approve_refund

reconcile_transaction

manage_park

view_audit_log
manage_users
manage_roles
```

---

## 9. Authentication Module

The application must support secure login.

Phase 1 requirements:

- Email/username + password authentication
- Password reset
- Active/inactive user status
- Role assignment
- Permission assignment
- Login logging
- Logout
- Session management

Optional two-factor authentication may be prepared architecturally but is not required for the initial demo.

---

## 10. Government Administration Module

This is the highest administrative layer.

Authorized administrators must be able to manage:

- LGAs
- Parks
- Revenue heads
- Ticket types
- System users
- Roles
- Permissions
- System configurations
- Operational status

State administrators should also be able to view statewide statistics.

---

## 11. LGA Management Module

All LGAs participating in the system must exist as structured records.

LGA records contain:

```text
LGA Name
LGA Code
Status
Administrative Contact
Number of Parks
Registered Operators
Registered Vehicles
Registered Drivers
Revenue Statistics
```

An LGA administrator must only have access to authorized information belonging to that LGA.

---

## 12. Park Management Module

Each recognized motor park must receive a unique system identity.

Required park information:

```text
Park ID
Park Name
LGA
Address
GPS coordinates
Park Category
Operational Status
Assigned Manager
Approved Routes
Registered Operators
Registered Vehicles
Registered Drivers
Applicable Revenue Heads
```

Possible status:

```text
Pending
Active
Suspended
Inactive
```

Park managers must only see their assigned park unless additional permission is granted.

---

## 13. Transport Operator Module

The system must register recognized transport operators.

Operator information includes:

```text
Operator ID
Name
Contact Person
Telephone
Email
Address
Park
LGA
Registration Date
Status
Vehicles
Drivers
Routes
Documents
```

Statuses:

```text
Pending
Approved
Suspended
Inactive
```

An operator may own/manage multiple vehicles and drivers.

---

## 14. Driver Registry

Each commercial driver must have a unique driver record.

Required fields:

```text
Driver ID
Full Name
Photograph
Telephone
Residential Address
Driver Licence Number
Licence Expiry
Assigned Operator
Assigned Vehicle
Assigned Park
Approved Route
Registration Date
Status
```

Optional demo information may include:

```text
Next of Kin
Identification Document
Emergency Contact
```

Driver statuses:

```text
Pending
Active
Suspended
Expired
Blacklisted
Inactive
```

Blacklisting should exist structurally but must not automatically determine guilt or legal violation.

---

## 15. Vehicle Registry

Every registered commercial vehicle receives a unique record.

Fields:

```text
Vehicle ID
Registration Number
Vehicle Type
Make
Model
Colour
Year
Owner
Operator
Assigned Driver
Assigned Park
Approved Route
Roadworthiness Expiry
Insurance Expiry
Registration Status
```

Vehicle types may include:

```text
Bus
Minibus
Taxi
Tricycle
Motorcycle
Other
```

Actual Government categories can later replace the demo values.

---

## 16. Route Management

The system must support routes.

A route contains:

```text
Route ID
Origin
Destination
Associated Park
LGA
Status
```

One park can have multiple routes.

One operator can operate multiple approved routes.

---

## 17. Revenue Head Management

Revenue must not be hardcoded directly into tickets.

The system must have a dedicated Revenue Head module.

Example:

```text
Revenue Head:
Daily Park Ticket

Code:
DPT

Applicable Vehicle:
Commercial Bus

Amount:
Configured Amount

Applicable Park:
Specified Parks / Statewide

Frequency:
Daily

Status:
Active
```

Government administrators must control revenue configuration.

---

## 18. Fee Configuration

Revenue heads and their applicable fees must be configurable.

The system should support:

```text
Fixed amount
Vehicle category
Park
LGA
Route
Effective date
Expiry date
Status
```

Historical fee configurations must remain traceable.

Changing a fee must not modify old transactions.

---

## 19. Ticketing Module

The ticketing module is a core feature.

Each ticket must have:

```text
Ticket ID
Unique Reference
Revenue Head
Driver
Vehicle
Operator
Park
LGA
Amount
Issued By
Issued At
Payment Status
QR Verification Token
Ticket Status
```

Possible ticket statuses:

```text
Pending
Paid
Expired
Cancelled
Reversed
```

Ticket references should follow a recognizable convention, for example:

```text
OSPM-2026-000001
```

Final format can be adjusted before production.

---

## 20. QR Verification

Every valid ticket should have a unique QR code.

The QR code must point to a verification endpoint.

Example:

```text
/verify/ticket/{secure-token}
```

The public verification page should show only safe information.

Example:

```text
OSUN STATE PARK MANAGEMENT SYSTEM

VALID TICKET

Ticket Reference
Vehicle
Park
Ticket Type
Amount
Issued Date
Payment Status
```

Sensitive user information must not be exposed publicly.

---

## 21. Payment Module

Payment architecture must remain provider-independent.

The backend should expose a payment interface conceptually similar to:

```text
PaymentGatewayInterface
```

Possible implementations later:

```text
DemoPaymentGateway
PaystackGateway
FlutterwaveGateway
GovernmentGateway
BankGateway
```

The rest of the application must not depend directly on Paystack or Flutterwave.

---

## 22. Demo Payment Mode

Phase 1 must include a controlled demo gateway.

It must allow the developer/presenter to simulate:

```text
Successful Payment
Failed Payment
Pending Payment
Reversed Payment
```

This ensures the presentation can work even without internet connectivity or third-party payment services.

---

## 23. Payment Record

A payment should not be the same database entity as a ticket.

Required payment information:

```text
Payment ID
Ticket
Transaction Reference
Payment Provider
Provider Reference
Amount
Payment Channel
Status
Initiated At
Paid At
Metadata
```

Statuses:

```text
Pending
Successful
Failed
Reversed
Refunded
```

---

## 24. Digital Receipt Module

Successful payments generate digital receipts.

Receipt content:

```text
Osun State Park Management System

Transaction Reference
Ticket Reference
Driver/Operator
Vehicle
Park
Revenue Type
Amount
Payment Method
Payment Date
QR Verification
```

Receipt formats:

- Web receipt
- Printable receipt
- PDF receipt may be added if useful

---

## 25. Transaction Ledger

The financial architecture must preserve a clear transaction ledger.

Important entities must remain distinct:

```text
Ticket
Payment
Transaction
Settlement
Reconciliation
Adjustment
Refund
```

The application must never rely only on a mutable `amount/status` field as its financial history.

---

## 26. Revenue Dashboard

State-level dashboard should display:

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

Charts should include:

- Revenue over time
- Revenue by LGA
- Revenue by park
- Revenue by revenue head
- Payment channel distribution
- Transaction status

---

## 27. LGA Dashboard

LGA administrators must view only authorized information from their LGA.

Dashboard:

```text
Total Parks
Vehicles
Drivers
Operators
Today's Revenue
Monthly Revenue
Transactions
Pending Reconciliation
```

---

## 28. Park Dashboard

Park managers should view:

```text
Drivers
Vehicles
Operators
Routes
Tickets
Today's Collections
Transaction History
Compliance Alerts
Recent Incidents
```

---

## 29. Reconciliation Module

Reconciliation must compare records such as:

**Ticket Issued → Payment Received → Provider Reference → Expected Revenue → Recorded Revenue**

Possible reconciliation states:

```text
Unreconciled
Matched
Exception
Under Review
Reconciled
```

Exceptions include:

- Payment with no ticket
- Ticket without payment
- Duplicate payment reference
- Amount mismatch
- Reversal
- Unrecognized transaction

---

## 30. Reconciliation Workflow

Normal transaction:

```text
Ticket Generated
        ↓
Payment Initiated
        ↓
Payment Confirmed
        ↓
Transaction Recorded
        ↓
Receipt Generated
        ↓
Reconciliation
        ↓
Reconciled
```

Exception:

```text
Transaction
     ↓
Mismatch Detected
     ↓
Exception Queue
     ↓
Finance Review
     ↓
Resolution
```

---

## 31. Enforcement PWA

The first field application will be a React Progressive Web App.

It must support:

- QR ticket scan
- Ticket verification
- Vehicle lookup
- Driver lookup
- Operator lookup
- Basic compliance status
- Incident recording
- Evidence upload
- Offline-friendly interface where practical

Native Android/iOS development is not included in Phase 1.

---

## 32. Incident Management

Authorized users must be able to record incidents.

Fields:

```text
Incident ID
Category
Park
Date
Time
Reporter
Driver
Vehicle
Operator
Description
Evidence
Status
Resolution
```

Incident categories may include:

```text
Dispute
Unauthorized Collection
Safety Issue
Traffic Obstruction
Violence
Vehicle Incident
Ticket Dispute
Other
```

---

## 33. Complaint Management

The system must support complaint registration.

Complaint workflow:

```text
Submitted
↓
Received
↓
Assigned
↓
Under Review
↓
Resolved
↓
Closed
```

Complaint fields:

```text
Reference Number
Complainant
Telephone
Category
Park
Description
Evidence
Assigned Officer
Status
Resolution
```

Anonymous complaints may later be considered but are not required in the initial demo.

---

## 34. Notification System

The application should have an internal notification system.

Examples:

- New registration
- Payment successful
- Reconciliation exception
- Licence expiry
- Vehicle document expiry
- Incident assigned
- Complaint assigned
- Account approval

Phase 1 email/SMS delivery can be simulated or selectively enabled.

External notification providers must remain configurable.

---

## 35. Reporting Module

Reports must support filtering by:

```text
Date
LGA
Park
Revenue Head
Operator
Vehicle
Driver
Transaction Status
Payment Channel
```

Initial reports:

- Daily revenue report
- Monthly revenue report
- Revenue by park
- Revenue by LGA
- Transaction report
- Reconciliation report
- Vehicle report
- Driver report
- Operator report
- Incident report
- Complaint report

---

## 36. Export

Authorized reports should support export to:

```text
CSV
Excel
PDF
```

Export permissions must be controlled.

---

## 37. Audit Trail

Important system activities must be logged.

Examples:

```text
User Login
User Creation
Role Modification
Driver Creation
Driver Approval
Vehicle Registration
Ticket Generation
Payment Confirmation
Payment Reversal
Fee Change
Transaction Adjustment
Reconciliation
System Configuration Change
```

Financial audit history must not be casually deletable.

---

## 38. System Settings

Administrators require a settings section for:

```text
System Name
Government Logo
Contact Information
Reference Prefixes
Receipt Format
Currency
Timezone
Payment Configuration
Notification Configuration
QR Settings
Session Configuration
```

Avoid hardcoding settings that Government may later modify.

---

## 39. Data Ownership

The application architecture must recognize:

- Government owns Government operational data
- Government owns Government financial data
- Pinnacle Tech Hub operates as the technology solution provider
- The software architecture must support export and migration of Government data

---

## 40. Core Database Entities

| Domain | Core Entities |
|---|---|
| Identity | users, roles, permissions |
| Geography | lgas |
| Parks | parks |
| Operators | operators |
| Drivers | drivers |
| Vehicles | vehicles |
| Routes | routes |
| Revenue | revenue_heads, fee_configurations |
| Ticketing | tickets |
| Payments | payments |
| Finance | transactions, settlements, reconciliations |
| Enforcement | inspections, violations |
| Operations | incidents |
| Support | complaints |
| Communications | notifications |
| Audit | activity_logs, financial_audit_logs |

Exact table naming may evolve technically, but the domain model should remain intact.

---

## 41. Key Relationships

```text
LGA
 └── Parks

Park
 ├── Operators
 ├── Drivers
 ├── Vehicles
 ├── Routes
 └── Tickets

Operator
 ├── Drivers
 └── Vehicles

Driver
 ├── Vehicle
 ├── Operator
 └── Park

Vehicle
 ├── Driver
 ├── Operator
 ├── Park
 └── Tickets

Revenue Head
 └── Fee Configuration
       └── Tickets

Ticket
 └── Payment
       └── Transaction
             └── Reconciliation
```

---

## 42. Important Financial Rule

Previously completed financial records must not change because a new fee is configured later.

Example:

January:

```text
Daily Ticket = ₦500
```

February:

```text
Daily Ticket = ₦700
```

A January ticket must permanently preserve:

```text
₦500
```

even though the current fee becomes ₦700.

---

## 43. Deletion Policy

Sensitive operational and financial records should normally use:

```text
soft deletion
status changes
archival
```

rather than permanent deletion.

Financial transactions must never disappear merely because someone clicks delete.

---

## 44. Demo Data

Phase 1 will use realistic synthetic data.

Recommended seed data:

```text
Multiple LGAs
10–20 demonstration parks
50+ operators
200+ drivers
250+ vehicles
Several routes
Multiple revenue heads
Thousands of transactions
Successful/failed/pending payments
Reconciliation exceptions
Incidents
Complaints
```

The demo should look populated and operational.

---

## 45. Demo Presentation Scenario

The system must be capable of demonstrating this exact journey.

1. Administrator logs in.
2. State dashboard displays statewide activity.
3. Administrator opens an LGA.
4. A motor park is opened.
5. Registered operators, drivers and vehicles are shown.
6. A vehicle/driver is selected.
7. A ticket is generated.
8. A payment is simulated.
9. A digital receipt and QR code are generated.
10. An enforcement officer scans the QR.
11. The ticket appears as valid.
12. Revenue dashboard updates.
13. Finance officer views the transaction.
14. Transaction is reconciled.
15. Audit history shows the full lifecycle.

If the system can demonstrate this smoothly, the principal product story is complete.

---

## 46. UI Design Direction

The product should visually resemble a modern Government financial/operations platform.

Design qualities:

- Professional
- Clean
- Data focused
- Responsive
- Accessible
- Minimal animation
- Fast
- Clear hierarchy
- Government-facing rather than consumer-SaaS styling

Pinnacle branding may appear subtly in the demo as:

**Technology Solution by Pinnacle Tech Hub**

but it should not overwhelm the Osun State Park Management System identity.

---

## 47. Desktop First, Mobile Responsive

The primary administrative platform is desktop-first.

However, all pages must remain functional on tablets and mobile devices.

The enforcement module should be specifically mobile-first.

---

## 48. Security Requirements

Even the demo architecture should follow secure practices.

Requirements:

- HTTPS
- CSRF protection
- SQL injection protection through Laravel ORM/query binding
- Input validation
- Secure password hashing
- Role-based access
- Permission checks
- Rate limiting
- Secure file uploads
- Audit logging
- Session expiration
- API authentication
- Secure environment secrets
- No credentials committed to GitHub

Production later adds:

- MFA
- WAF
- Redis session management
- Central monitoring
- Advanced logging
- Intrusion detection
- Automated backups

---

## 49. File Upload Security

Uploads must validate:

- File type
- File size
- Extension
- MIME type
- User permission

Files should be renamed using secure generated identifiers.

Do not trust the original filename.

---

## 50. Performance Requirements

The demo should feel immediate.

Typical dashboard/application pages should target loading within approximately:

**1–3 seconds under normal conditions.**

Tables must use pagination.

Large reports must not attempt to load entire datasets into memory.

Background jobs should handle expensive exports/reports where necessary.

---

## 51. Search

The system must provide search for:

- Drivers
- Vehicles
- Operators
- Parks
- Tickets
- Transactions
- Complaints
- Incidents

Primary search identifiers include:

```text
Name
Telephone
Driver ID
Vehicle Number
Operator ID
Ticket Reference
Transaction Reference
```

---

## 52. Pagination

Any potentially large dataset must use pagination.

Never render:

```text
10,000 drivers
20,000 vehicles
100,000 transactions
```

on a single page.

---

## 53. API Design

Even though Inertia.js will power the main interface, important backend functionality should remain service-oriented.

The Laravel backend should be capable of exposing API endpoints later for:

- Flutter applications
- Third-party integrations
- Government systems
- Payment providers
- Verification applications

Do not embed core business logic only inside React components.

---

## 54. Business Logic Rule

React handles presentation.

Laravel handles business rules.

For example, React must **not** independently decide:

```text
ticket amount
revenue allocation
whether a transaction is valid
whether a ticket can be reversed
```

Those decisions belong to Laravel.

---

## 55. Validation Rule

All critical validation must happen server-side even if client-side validation also exists.

Never rely exclusively on React validation.

---

## 56. Hosting — Phase 1

Demo infrastructure:

```text
Cloudflare
     ↓
Shared Hosting
     ↓
Laravel
     ↓
Inertia + React
     ↓
MySQL
```

Supporting:

```text
Cloudflare R2
Database Queue
Cron Scheduler
```

---

## 57. Hosting — Pilot

After approval:

```text
Cloudflare
      ↓
VPS / Cloud
      ↓
Nginx
      ↓
PHP-FPM
      ↓
Laravel
      ↓
PostgreSQL
      ↓
Redis
      ↓
Horizon
      ↓
Object Storage
```

The application code remains fundamentally the same.

---

## 58. Backup

Phase 1:

- Daily database backup where feasible

Phase 2:

- Automated database backups
- Offsite backups
- Object storage versioning
- Disaster recovery policy

---

## 59. Features Explicitly OUT OF SCOPE for Phase 1

This section is especially important for preventing drift.

Phase 1 will **not** include:

- Microservices
- Kubernetes
- Native Android app
- Native iOS app
- Flutter application
- Live Government revenue collection
- Integration with real Government treasury infrastructure
- Biometric authentication
- Facial recognition
- ANPR/license plate cameras
- CCTV analytics
- GPS fleet tracking
- Passenger booking
- Ride-hailing
- Bus reservation
- Driver navigation
- Vehicle IoT hardware
- Smart physical gates
- NFC cards
- Automatic barriers
- AI fraud detection
- Machine-learning forecasting
- Blockchain
- Cryptocurrency
- Digital wallet balances
- Loan functionality
- Insurance sales
- Driver payroll
- Union membership administration
- NURTW internal administration
- Payroll/HR
- Government accounting ERP
- Procurement management

These may be considered later but must not enter the initial build casually.

---

## 60. AI Scope

No AI capability is required for the core Phase 1 demonstration.

Future possibilities such as anomaly detection, forecasting or intelligent reporting should not distract development from the fundamental system.

The first priority is accurate transactional data.

---

## 61. Revenue Sharing

The platform should be architecturally capable of handling configurable financial allocation if Government ultimately requires it.

However:

- No assumed statutory percentage should be hardcoded.
- No political or legal interpretation of revenue allocation should be embedded in the application.
- Any final revenue-sharing configuration must be based on Government-approved rules.

---

## 62. Government Naming Rule

The official public-facing project name for development and presentation will remain:

# OSUN STATE PARK MANAGEMENT SYSTEM

Do not casually rename it to:

- Integrated Transport Platform
- Smart Mobility Platform
- Transport Management System
- Park & Revenue System
- or another invented Government program name

Individual internal software modules may have technical names, but the overall product remains aligned with the existing Government initiative.

---

## 63. Environment Separation

The project must use:

```text
Local Development
Demo / Staging
Production
```

These environments must have separate:

- Environment variables
- Database credentials
- API keys
- Payment credentials
- Storage configuration

---

## 64. Git Strategy

Recommended:

```text
main
develop
feature/*
fix/*
```

`main` represents stable deployable code.

`develop` contains integrated development.

Features should be developed through branches such as:

```text
feature/driver-registry
feature/ticketing
feature/reconciliation
```

---

## 65. Development Order

### Phase A — Foundation

- Project setup
- Authentication
- RBAC
- Layout
- Users
- Audit framework

### Phase B — Master Data

- LGAs
- Parks
- Routes
- Revenue heads
- Fee configurations

### Phase C — Transport Registry

- Operators
- Drivers
- Vehicles

### Phase D — Ticketing

- Ticket creation
- QR generation
- Ticket verification

### Phase E — Financial

- Payment simulation
- Transactions
- Receipts
- Reconciliation

### Phase F — Dashboards

- State dashboard
- LGA dashboard
- Park dashboard
- Revenue dashboard

### Phase G — Enforcement

- Verification PWA
- Driver lookup
- Vehicle lookup
- Incident reporting

### Phase H — Operations

- Complaints
- Notifications
- Reports
- Exports

### Phase I — Demo Preparation

- Synthetic data
- Presentation workflow
- Performance optimization
- UI polish
- Security review
- Deployment

---

## 66. MVP Completion Criteria

Phase 1 is considered complete when the following scenario works reliably:

> An authorized user can register or retrieve a park, operator, driver and vehicle; issue a valid configured ticket; complete a simulated payment; generate a digital receipt and QR verification; see the financial transaction reflected on the relevant dashboards; reconcile the transaction; and review the corresponding audit history.

Everything else supports this core journey.

---

## 67. Definition of Done

A feature is not considered complete merely because the page exists.

A feature is done only when:

- UI is complete
- Server-side validation works
- Authorization is applied
- Business rules work
- Database relationships are correct
- Errors are handled
- Audit events are recorded where appropriate
- Responsive behavior works
- Demo data works
- Successful and failure states are tested
- The feature has been reviewed within the overall workflow

---

## 68. Scope Change Rule

When someone suggests:

> “Why don't we also add...”

the immediate question is:

> **“Is it included in Project Scope v1.0?”**

If yes, implement it.

If no, classify it as:

```text
Future Enhancement
Scope Change Request
Phase 2
```

Do not silently add it.

---

## 69. Product Priority

When making development choices, priorities should remain:

1. Financial integrity
2. Data integrity
3. Security
4. Operational correctness
5. Auditability
6. Ease of use
7. Performance
8. Visual polish

Fancy features come after these.

---

## 70. Final Development Principle

The project must never become merely a collection of dashboards.

Everything must connect.

A park must connect to an LGA.

A vehicle must connect to its operating context.

A driver must connect to the relevant vehicle/operator/park.

A ticket must connect to a configured revenue obligation.

A payment must connect to a ticket.

A transaction must connect to the payment.

A receipt must prove the transaction.

Reconciliation must confirm the financial record.

Audit history must explain what happened.

That is what turns the platform from a UI demo into a credible **government operational system**.

---

# PROJECT BASELINE

For development purposes, the following is frozen as **Scope Version 1.0**:

- **Product:** Osun State Park Management System Digital Platform
- **Technology Provider:** Pinnacle Tech Hub
- **Architecture:** Laravel Modular Monolith
- **Frontend:** React + TypeScript + Inertia + Tailwind
- **Demo Database:** MySQL
- **Production Database:** PostgreSQL
- **Initial Mobile Strategy:** PWA
- **Demo Hosting:** Shared Hosting
- **Production Migration:** VPS/Cloud
- **Primary Demo Workflow:** Registration → Ticket → Payment → Receipt → QR Verification → Revenue → Reconciliation → Audit
- **Current Build Target:** Government presentation and pilot-ready foundation
- **Out-of-Scope Features:** Must not enter development without an approved scope revision

---

## Scope Governance Statement

This document is the official development baseline for Version 1.0.

Any functional, architectural, financial, security, operational or interface requirement outside this document must be documented and approved as a scope revision before implementation.
