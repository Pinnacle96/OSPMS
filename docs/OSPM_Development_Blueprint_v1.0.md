# OSPM DEVELOPMENT BLUEPRINT — VERSION 1.0

This package contains two authoritative development documents for the Osun State Park Management System Phase 1 build:

1. `OSPM_Database_Schema_v1.0.md`
2. `OSPM_Screen_Page_Inventory_v1.0.md`

Together with the previously approved Project Scope v1.0, these documents define:

- what the product does;
- how the data is structured;
- which screens must be built;
- the boundaries of Phase 1;
- the financial integrity rules;
- the development order.

---

# DOCUMENT 1 — DATABASE SCHEMA

# OSUN STATE PARK MANAGEMENT SYSTEM
## Database Schema Specification — Version 1.0

**Prepared by:** Pinnacle Tech Hub  
**Status:** Development Baseline  
**Applies to:** Phase 1 Demo / Presentation and Pilot-Ready Foundation  
**Backend:** Laravel Modular Monolith  
**Demo Database:** MySQL 8  
**Production Target:** PostgreSQL  

---

# 1. Purpose

This document translates the approved Project Scope v1.0 into the database structure required to build the Osun State Park Management System digital platform.

It is the authoritative database baseline for Phase 1 development.

The schema is designed to satisfy four rules:

1. Every important operational relationship must be represented explicitly.
2. Every financial transaction must be traceable and auditable.
3. The Phase 1 MySQL schema must migrate cleanly to PostgreSQL later.
4. Historical financial records must never change merely because current configuration changes.

---

# 2. Database Design Conventions

## 2.1 Primary Keys

Use internal numeric primary keys for efficient joins:

```text
id BIGINT UNSIGNED AUTO_INCREMENT
```

When migrated to PostgreSQL, use generated BIGINT identity columns.

Business-facing entities should also receive a non-sequential public identifier:

```text
public_id CHAR(26) UNIQUE
```

The recommended public identifier is a Laravel ULID.

Examples:

```text
01J9F6Y...
```

Internal numeric IDs must never be exposed as public verification tokens.

---

## 2.2 Business References

Human-readable records should use separate unique references.

Examples:

```text
Park: OSPM-PARK-0001
Driver: OSPM-DRV-000001
Vehicle: OSPM-VEH-000001
Operator: OSPM-OPR-000001
Ticket: OSPM-2026-000001
Payment: PAY-2026-000001
Receipt: RCP-2026-000001
Incident: INC-2026-000001
Complaint: CMP-2026-000001
```

Reference generation belongs to Laravel services, not React.

---

## 2.3 Money

Never use floating-point types for money.

Use:

```text
DECIMAL(15,2)
```

All financial records must also contain:

```text
currency CHAR(3) DEFAULT 'NGN'
```

---

## 2.4 Status Fields

Do not use database-specific ENUM types.

Use `VARCHAR` fields with Laravel backed enums and server-side validation.

This keeps the schema portable between MySQL and PostgreSQL.

---

## 2.5 Date and Time

Store timestamps consistently in UTC.

Display dates/times using the configured application timezone, initially:

```text
Africa/Lagos
```

Use Laravel `created_at` and `updated_at` timestamps on operational tables unless explicitly stated otherwise.

---

## 2.6 JSON

Use database JSON columns only for flexible metadata, provider payloads and snapshots.

Do not hide core relational data inside JSON.

MySQL `JSON` can later migrate to PostgreSQL `JSONB`.

---

## 2.7 Deletion

Master/operational tables may use soft deletion where appropriate.

Financial ledger tables must not use destructive deletion.

Financial corrections happen through reversals, refunds, adjustments and new ledger entries.

---

# 3. Domain Map

```text
IDENTITY
Users → Roles → Permissions → Access Scopes

GEOGRAPHY / OPERATIONS
LGA → Park → Route

TRANSPORT REGISTRY
Operator → Driver → Vehicle → Assignment

REVENUE
Revenue Head → Fee Configuration

TICKETING
Ticket → Payment → Receipt

FINANCE
Payment → Financial Transaction → Settlement → Reconciliation

ENFORCEMENT
Inspection → Violation
Incident

SUPPORT
Complaint → Complaint Notes

GOVERNANCE
Activity Log
Financial Audit Log
System Settings
```

---

# 4. Identity & Access Tables

## 4.1 `users`

Purpose: authenticated system users.

| Column | Type | Rules |
|---|---|---|
| id | BIGINT | PK |
| public_id | CHAR(26) | UNIQUE |
| name | VARCHAR(150) | required |
| email | VARCHAR(190) | UNIQUE, nullable where username login is used |
| username | VARCHAR(80) | UNIQUE, nullable |
| phone | VARCHAR(30) | nullable, indexed |
| password | VARCHAR(255) | required |
| status | VARCHAR(30) | active, inactive, suspended |
| email_verified_at | DATETIME | nullable |
| last_login_at | DATETIME | nullable |
| must_change_password | BOOLEAN | default false |
| remember_token | VARCHAR(100) | nullable |
| created_at | DATETIME | |
| updated_at | DATETIME | |
| deleted_at | DATETIME | nullable |

Indexes:

```text
UNIQUE(public_id)
UNIQUE(email)
UNIQUE(username)
INDEX(status)
INDEX(phone)
```

---

## 4.2 Spatie Permission Tables

Use the standard `spatie/laravel-permission` schema:

```text
roles
permissions
model_has_roles
model_has_permissions
role_has_permissions
```

Do not replace the package structure with custom hardcoded role columns.

---

## 4.3 `user_lga_access`

Purpose: associates users with LGAs they are permitted to administer or view.

| Column | Type | Rules |
|---|---|---|
| id | BIGINT | PK |
| user_id | BIGINT | FK users |
| lga_id | BIGINT | FK lgas |
| access_level | VARCHAR(30) | manage, view |
| created_at | DATETIME | |

Constraint:

```text
UNIQUE(user_id, lga_id)
```

---

## 4.4 `user_park_access`

| Column | Type | Rules |
|---|---|---|
| id | BIGINT | PK |
| user_id | BIGINT | FK users |
| park_id | BIGINT | FK parks |
| access_level | VARCHAR(30) | manage, view |
| created_at | DATETIME | |

Constraint:

```text
UNIQUE(user_id, park_id)
```

---

## 4.5 `user_operator_access`

| Column | Type | Rules |
|---|---|---|
| id | BIGINT | PK |
| user_id | BIGINT | FK users |
| operator_id | BIGINT | FK operators |
| access_level | VARCHAR(30) | manage, view |
| created_at | DATETIME | |

Constraint:

```text
UNIQUE(user_id, operator_id)
```

---

## 4.6 `login_activities`

Purpose: security history for login/logout activity.

| Column | Type |
|---|---|
| id | BIGINT PK |
| user_id | BIGINT FK users |
| session_identifier | VARCHAR(190), nullable |
| event | VARCHAR(30) |
| ip_address | VARCHAR(45), nullable |
| user_agent | TEXT, nullable |
| occurred_at | DATETIME |
| metadata | JSON, nullable |

Events:

```text
login_success
login_failed
logout
password_reset
session_revoked
```

---

# 5. Geography & Park Administration

## 5.1 `lgas`

| Column | Type | Rules |
|---|---|---|
| id | BIGINT | PK |
| public_id | CHAR(26) | UNIQUE |
| code | VARCHAR(30) | UNIQUE |
| name | VARCHAR(150) | UNIQUE |
| administrative_contact_name | VARCHAR(150) | nullable |
| administrative_contact_phone | VARCHAR(30) | nullable |
| administrative_contact_email | VARCHAR(190) | nullable |
| status | VARCHAR(30) | active, inactive |
| created_at | DATETIME | |
| updated_at | DATETIME | |
| deleted_at | DATETIME | nullable |

---

## 5.2 `parks`

| Column | Type | Rules |
|---|---|---|
| id | BIGINT | PK |
| public_id | CHAR(26) | UNIQUE |
| park_code | VARCHAR(50) | UNIQUE |
| lga_id | BIGINT | FK lgas |
| name | VARCHAR(190) | required |
| address | TEXT | required |
| latitude | DECIMAL(10,7) | nullable |
| longitude | DECIMAL(10,7) | nullable |
| category | VARCHAR(50) | nullable |
| contact_phone | VARCHAR(30) | nullable |
| status | VARCHAR(30) | pending, active, suspended, inactive |
| activated_at | DATETIME | nullable |
| created_by | BIGINT | FK users, nullable |
| updated_by | BIGINT | FK users, nullable |
| created_at | DATETIME | |
| updated_at | DATETIME | |
| deleted_at | DATETIME | nullable |

Indexes:

```text
INDEX(lga_id, status)
INDEX(name)
INDEX(park_code)
```

---

## 5.3 `routes`

| Column | Type | Rules |
|---|---|---|
| id | BIGINT | PK |
| public_id | CHAR(26) | UNIQUE |
| route_code | VARCHAR(50) | UNIQUE |
| origin | VARCHAR(190) | required |
| destination | VARCHAR(190) | required |
| description | TEXT | nullable |
| status | VARCHAR(30) | active, inactive |
| created_by | BIGINT | FK users, nullable |
| created_at | DATETIME | |
| updated_at | DATETIME | |
| deleted_at | DATETIME | nullable |

---

## 5.4 `park_route`

Purpose: many-to-many relationship between parks and approved routes.

| Column | Type |
|---|---|
| id | BIGINT PK |
| park_id | BIGINT FK parks |
| route_id | BIGINT FK routes |
| status | VARCHAR(30) |
| created_at | DATETIME |

Constraint:

```text
UNIQUE(park_id, route_id)
```

---

# 6. Transport Registry

## 6.1 `operators`

| Column | Type | Rules |
|---|---|---|
| id | BIGINT | PK |
| public_id | CHAR(26) | UNIQUE |
| operator_number | VARCHAR(50) | UNIQUE |
| name | VARCHAR(190) | required |
| registration_number | VARCHAR(100) | nullable |
| contact_person | VARCHAR(150) | nullable |
| phone | VARCHAR(30) | nullable |
| email | VARCHAR(190) | nullable |
| address | TEXT | nullable |
| status | VARCHAR(30) | pending, approved, suspended, inactive |
| registered_at | DATETIME | nullable |
| approved_at | DATETIME | nullable |
| approved_by | BIGINT | FK users, nullable |
| created_by | BIGINT | FK users, nullable |
| created_at | DATETIME | |
| updated_at | DATETIME | |
| deleted_at | DATETIME | nullable |

---

## 6.2 `operator_park`

| Column | Type |
|---|---|
| id | BIGINT PK |
| operator_id | BIGINT FK operators |
| park_id | BIGINT FK parks |
| status | VARCHAR(30) |
| approved_at | DATETIME, nullable |
| created_at | DATETIME |

Constraint:

```text
UNIQUE(operator_id, park_id)
```

---

## 6.3 `operator_route`

| Column | Type |
|---|---|
| id | BIGINT PK |
| operator_id | BIGINT FK operators |
| route_id | BIGINT FK routes |
| park_id | BIGINT FK parks, nullable |
| status | VARCHAR(30) |
| approved_at | DATETIME, nullable |
| created_at | DATETIME |

Recommended unique rule:

```text
UNIQUE(operator_id, route_id, park_id)
```

---

## 6.4 `drivers`

| Column | Type | Rules |
|---|---|---|
| id | BIGINT | PK |
| public_id | CHAR(26) | UNIQUE |
| driver_number | VARCHAR(50) | UNIQUE |
| first_name | VARCHAR(100) | required |
| middle_name | VARCHAR(100) | nullable |
| last_name | VARCHAR(100) | required |
| phone | VARCHAR(30) | indexed |
| email | VARCHAR(190) | nullable |
| residential_address | TEXT | nullable |
| licence_number | VARCHAR(100) | nullable, indexed |
| licence_expiry | DATE | nullable |
| emergency_contact_name | VARCHAR(150) | nullable |
| emergency_contact_phone | VARCHAR(30) | nullable |
| next_of_kin | VARCHAR(150) | nullable |
| status | VARCHAR(30) | pending, active, suspended, expired, blacklisted, inactive |
| registered_at | DATETIME | nullable |
| approved_at | DATETIME | nullable |
| approved_by | BIGINT | FK users, nullable |
| created_by | BIGINT | FK users, nullable |
| created_at | DATETIME | |
| updated_at | DATETIME | |
| deleted_at | DATETIME | nullable |

Do not store the current vehicle/operator relationship directly as the only source of truth. Use `driver_assignments`.

---

## 6.5 `vehicles`

| Column | Type | Rules |
|---|---|---|
| id | BIGINT | PK |
| public_id | CHAR(26) | UNIQUE |
| vehicle_number | VARCHAR(50) | UNIQUE internal number |
| registration_number | VARCHAR(30) | UNIQUE, indexed |
| vehicle_type | VARCHAR(50) | bus, minibus, taxi, tricycle, motorcycle, other |
| make | VARCHAR(100) | nullable |
| model | VARCHAR(100) | nullable |
| colour | VARCHAR(50) | nullable |
| manufacture_year | SMALLINT | nullable |
| owner_name | VARCHAR(190) | nullable |
| owner_phone | VARCHAR(30) | nullable |
| roadworthiness_expiry | DATE | nullable |
| insurance_expiry | DATE | nullable |
| status | VARCHAR(30) | pending, active, suspended, expired, inactive |
| registered_at | DATETIME | nullable |
| approved_at | DATETIME | nullable |
| approved_by | BIGINT | FK users, nullable |
| created_by | BIGINT | FK users, nullable |
| created_at | DATETIME | |
| updated_at | DATETIME | |
| deleted_at | DATETIME | nullable |

---

## 6.6 `driver_assignments`

Purpose: maintains current and historical operational assignment relationships.

| Column | Type | Rules |
|---|---|---|
| id | BIGINT | PK |
| driver_id | BIGINT | FK drivers |
| vehicle_id | BIGINT | FK vehicles |
| operator_id | BIGINT | FK operators |
| park_id | BIGINT | FK parks |
| route_id | BIGINT | FK routes, nullable |
| starts_at | DATETIME | required |
| ends_at | DATETIME | nullable |
| is_primary | BOOLEAN | default true |
| status | VARCHAR(30) | active, ended, suspended |
| assigned_by | BIGINT | FK users, nullable |
| created_at | DATETIME | |
| updated_at | DATETIME | |

Application rule:

> A driver may have historical assignments, but only one active primary assignment at a time unless Government policy later requires otherwise.

Indexes:

```text
INDEX(driver_id, status)
INDEX(vehicle_id, status)
INDEX(operator_id, status)
INDEX(park_id, status)
```

---

## 6.7 `media_attachments`

Purpose: reusable file storage metadata for photographs, documents and evidence.

| Column | Type |
|---|---|
| id | BIGINT PK |
| public_id | CHAR(26) UNIQUE |
| attachable_type | VARCHAR(190) |
| attachable_id | BIGINT |
| category | VARCHAR(50) |
| disk | VARCHAR(50) |
| path | VARCHAR(500) |
| original_name | VARCHAR(255), nullable |
| mime_type | VARCHAR(100) |
| size_bytes | BIGINT |
| file_hash | VARCHAR(128), nullable |
| uploaded_by | BIGINT FK users, nullable |
| created_at | DATETIME |

Examples of `category`:

```text
driver_photo
identity_document
vehicle_photo
vehicle_document
incident_evidence
complaint_evidence
```

---

# 7. Revenue Configuration

## 7.1 `revenue_heads`

| Column | Type | Rules |
|---|---|---|
| id | BIGINT | PK |
| public_id | CHAR(26) | UNIQUE |
| code | VARCHAR(50) | UNIQUE |
| name | VARCHAR(190) | required |
| description | TEXT | nullable |
| frequency | VARCHAR(50) | daily, per_entry, per_trip, one_time, periodic, other |
| status | VARCHAR(30) | active, inactive |
| created_by | BIGINT | FK users, nullable |
| created_at | DATETIME | |
| updated_at | DATETIME | |
| deleted_at | DATETIME | nullable |

---

## 7.2 `fee_configurations`

Purpose: determines the applicable amount while preserving fee history.

| Column | Type | Rules |
|---|---|---|
| id | BIGINT | PK |
| public_id | CHAR(26) | UNIQUE |
| revenue_head_id | BIGINT | FK revenue_heads |
| amount | DECIMAL(15,2) | required |
| currency | CHAR(3) | default NGN |
| vehicle_type | VARCHAR(50) | nullable |
| lga_id | BIGINT | FK lgas, nullable |
| park_id | BIGINT | FK parks, nullable |
| route_id | BIGINT | FK routes, nullable |
| priority | INT | default 0 |
| effective_from | DATETIME | required |
| effective_to | DATETIME | nullable |
| status | VARCHAR(30) | draft, active, expired, inactive |
| approved_by | BIGINT | FK users, nullable |
| created_by | BIGINT | FK users, nullable |
| created_at | DATETIME | |
| updated_at | DATETIME | |

Interpretation:

- all scope columns null = statewide/default
- `lga_id` set = LGA-specific
- `park_id` set = park-specific
- `route_id` set = route-specific
- `vehicle_type` set = vehicle category-specific

Application services determine the most specific active fee.

Historical tickets must store the amount snapshot and never recalculate from the latest configuration.

---

# 8. Ticketing

## 8.1 `tickets`

| Column | Type | Rules |
|---|---|---|
| id | BIGINT | PK |
| public_id | CHAR(26) | UNIQUE |
| ticket_reference | VARCHAR(80) | UNIQUE |
| revenue_head_id | BIGINT | FK revenue_heads |
| fee_configuration_id | BIGINT | FK fee_configurations |
| lga_id | BIGINT | FK lgas |
| park_id | BIGINT | FK parks |
| operator_id | BIGINT | FK operators, nullable |
| driver_id | BIGINT | FK drivers, nullable |
| vehicle_id | BIGINT | FK vehicles, nullable |
| route_id | BIGINT | FK routes, nullable |
| fee_code_snapshot | VARCHAR(50) | required |
| fee_name_snapshot | VARCHAR(190) | required |
| amount | DECIMAL(15,2) | immutable snapshot |
| currency | CHAR(3) | default NGN |
| issued_by | BIGINT | FK users |
| issued_at | DATETIME | required |
| expires_at | DATETIME | nullable |
| ticket_status | VARCHAR(30) | pending, paid, expired, cancelled, reversed |
| payment_status | VARCHAR(30) | unpaid, pending, paid, failed, reversed, refunded |
| verification_token | VARCHAR(128) | UNIQUE |
| context_snapshot | JSON | nullable |
| created_at | DATETIME | |
| updated_at | DATETIME | |

Critical indexes:

```text
UNIQUE(ticket_reference)
UNIQUE(verification_token)
INDEX(issued_at)
INDEX(lga_id, issued_at)
INDEX(park_id, issued_at)
INDEX(vehicle_id, issued_at)
INDEX(driver_id, issued_at)
INDEX(ticket_status, payment_status)
```

Do not soft-delete tickets.

---

# 9. Payments, Receipts & Ledger

## 9.1 `payments`

| Column | Type | Rules |
|---|---|---|
| id | BIGINT | PK |
| public_id | CHAR(26) | UNIQUE |
| payment_reference | VARCHAR(80) | UNIQUE |
| ticket_id | BIGINT | FK tickets |
| provider | VARCHAR(50) | demo, paystack, flutterwave, government, bank |
| provider_reference | VARCHAR(190) | nullable, indexed |
| channel | VARCHAR(50) | cashless_pos, transfer, ussd, gateway, demo, other |
| amount | DECIMAL(15,2) | required |
| currency | CHAR(3) | default NGN |
| status | VARCHAR(30) | pending, successful, failed, reversed, refunded |
| idempotency_key | VARCHAR(190) | nullable, UNIQUE |
| initiated_at | DATETIME | required |
| paid_at | DATETIME | nullable |
| failed_at | DATETIME | nullable |
| reversed_at | DATETIME | nullable |
| provider_metadata | JSON | nullable |
| created_by | BIGINT | FK users, nullable |
| created_at | DATETIME | |
| updated_at | DATETIME | |

Application rule:

> A successful payment transition must be handled transactionally and idempotently.

---

## 9.2 `receipts`

| Column | Type | Rules |
|---|---|---|
| id | BIGINT | PK |
| public_id | CHAR(26) | UNIQUE |
| receipt_number | VARCHAR(80) | UNIQUE |
| payment_id | BIGINT | FK payments, UNIQUE |
| ticket_id | BIGINT | FK tickets |
| verification_token | VARCHAR(128) | UNIQUE |
| issued_at | DATETIME | required |
| rendered_file_path | VARCHAR(500) | nullable |
| created_at | DATETIME | |

One successful payment should produce one canonical receipt.

---

## 9.3 `financial_transactions`

Purpose: immutable financial ledger.

| Column | Type | Rules |
|---|---|---|
| id | BIGINT | PK |
| public_id | CHAR(26) | UNIQUE |
| transaction_reference | VARCHAR(80) | UNIQUE |
| ticket_id | BIGINT | FK tickets, nullable |
| payment_id | BIGINT | FK payments, nullable |
| parent_transaction_id | BIGINT | self FK, nullable |
| transaction_type | VARCHAR(30) | payment, reversal, refund, adjustment |
| direction | VARCHAR(10) | credit, debit |
| amount | DECIMAL(15,2) | positive absolute amount |
| currency | CHAR(3) | default NGN |
| occurred_at | DATETIME | required |
| description | VARCHAR(255) | nullable |
| source | VARCHAR(50) | system, payment_gateway, administrator |
| created_by | BIGINT | FK users, nullable |
| metadata | JSON | nullable |
| created_at | DATETIME | |

Rules:

- never update amount after creation
- never hard delete
- corrections create additional ledger entries
- reversal/refund entries reference the original transaction

---

# 10. Settlement & Reconciliation

## 10.1 `settlements`

| Column | Type |
|---|---|
| id | BIGINT PK |
| public_id | CHAR(26) UNIQUE |
| settlement_reference | VARCHAR(80) UNIQUE |
| provider | VARCHAR(50) |
| provider_settlement_reference | VARCHAR(190), nullable |
| period_start | DATETIME |
| period_end | DATETIME |
| gross_amount | DECIMAL(15,2) |
| provider_fees | DECIMAL(15,2) default 0 |
| net_amount | DECIMAL(15,2) |
| currency | CHAR(3) default NGN |
| government_account_reference | VARCHAR(190), nullable |
| status | VARCHAR(30) |
| settled_at | DATETIME, nullable |
| metadata | JSON, nullable |
| created_at | DATETIME |
| updated_at | DATETIME |

Statuses:

```text
pending
processing
settled
exception
reversed
```

---

## 10.2 `settlement_items`

| Column | Type |
|---|---|
| id | BIGINT PK |
| settlement_id | BIGINT FK settlements |
| financial_transaction_id | BIGINT FK financial_transactions |
| amount | DECIMAL(15,2) |
| status | VARCHAR(30) |
| created_at | DATETIME |

Constraint:

```text
UNIQUE(settlement_id, financial_transaction_id)
```

---

## 10.3 `reconciliation_runs`

| Column | Type |
|---|---|
| id | BIGINT PK |
| public_id | CHAR(26) UNIQUE |
| reconciliation_reference | VARCHAR(80) UNIQUE |
| period_start | DATETIME |
| period_end | DATETIME |
| lga_id | BIGINT FK lgas, nullable |
| park_id | BIGINT FK parks, nullable |
| provider | VARCHAR(50), nullable |
| status | VARCHAR(30) |
| started_by | BIGINT FK users |
| started_at | DATETIME |
| completed_at | DATETIME, nullable |
| summary | JSON, nullable |
| created_at | DATETIME |

Statuses:

```text
queued
running
completed
completed_with_exceptions
failed
```

---

## 10.4 `reconciliation_items`

| Column | Type |
|---|---|
| id | BIGINT PK |
| reconciliation_run_id | BIGINT FK reconciliation_runs |
| ticket_id | BIGINT FK tickets, nullable |
| payment_id | BIGINT FK payments, nullable |
| financial_transaction_id | BIGINT FK financial_transactions, nullable |
| settlement_item_id | BIGINT FK settlement_items, nullable |
| expected_amount | DECIMAL(15,2) |
| actual_amount | DECIMAL(15,2) |
| difference_amount | DECIMAL(15,2) |
| status | VARCHAR(30) |
| exception_type | VARCHAR(80), nullable |
| resolution_note | TEXT, nullable |
| resolved_by | BIGINT FK users, nullable |
| resolved_at | DATETIME, nullable |
| created_at | DATETIME |
| updated_at | DATETIME |

Statuses:

```text
unreconciled
matched
exception
under_review
reconciled
```

Possible exception types:

```text
payment_without_ticket
ticket_without_payment
duplicate_provider_reference
amount_mismatch
missing_ledger_entry
missing_settlement
reversal_exception
unknown
```

---

# 11. Refunds & Adjustments

## 11.1 `refunds`

| Column | Type |
|---|---|
| id | BIGINT PK |
| public_id | CHAR(26) UNIQUE |
| refund_reference | VARCHAR(80) UNIQUE |
| payment_id | BIGINT FK payments |
| ticket_id | BIGINT FK tickets |
| amount | DECIMAL(15,2) |
| currency | CHAR(3) |
| reason | TEXT |
| status | VARCHAR(30) |
| requested_by | BIGINT FK users |
| approved_by | BIGINT FK users, nullable |
| rejected_by | BIGINT FK users, nullable |
| provider_reference | VARCHAR(190), nullable |
| requested_at | DATETIME |
| processed_at | DATETIME, nullable |
| created_at | DATETIME |
| updated_at | DATETIME |

Statuses:

```text
requested
approved
rejected
processing
successful
failed
```

A successful refund creates a debit `financial_transactions` row.

---

## 11.2 `financial_adjustments`

| Column | Type |
|---|---|
| id | BIGINT PK |
| public_id | CHAR(26) UNIQUE |
| adjustment_reference | VARCHAR(80) UNIQUE |
| original_transaction_id | BIGINT FK financial_transactions |
| adjustment_type | VARCHAR(30) |
| amount | DECIMAL(15,2) |
| currency | CHAR(3) |
| reason | TEXT |
| status | VARCHAR(30) |
| requested_by | BIGINT FK users |
| approved_by | BIGINT FK users, nullable |
| requested_at | DATETIME |
| approved_at | DATETIME, nullable |
| created_at | DATETIME |
| updated_at | DATETIME |

An approved adjustment must create a new ledger transaction rather than mutate the original.

---

## 11.3 `webhook_events`

Purpose: idempotent processing of payment provider callbacks.

| Column | Type |
|---|---|
| id | BIGINT PK |
| provider | VARCHAR(50) |
| provider_event_id | VARCHAR(190) |
| event_type | VARCHAR(100) |
| payload | JSON |
| signature_valid | BOOLEAN |
| status | VARCHAR(30) |
| processed_at | DATETIME, nullable |
| error_message | TEXT, nullable |
| created_at | DATETIME |

Constraint:

```text
UNIQUE(provider, provider_event_id)
```

This table can remain unused in pure demo mode but should exist before live payment integration.

---

# 12. Enforcement

## 12.1 `inspections`

| Column | Type |
|---|---|
| id | BIGINT PK |
| public_id | CHAR(26) UNIQUE |
| inspection_reference | VARCHAR(80) UNIQUE |
| officer_user_id | BIGINT FK users |
| park_id | BIGINT FK parks, nullable |
| operator_id | BIGINT FK operators, nullable |
| driver_id | BIGINT FK drivers, nullable |
| vehicle_id | BIGINT FK vehicles, nullable |
| ticket_id | BIGINT FK tickets, nullable |
| inspection_type | VARCHAR(50) |
| result | VARCHAR(50) |
| notes | TEXT, nullable |
| latitude | DECIMAL(10,7), nullable |
| longitude | DECIMAL(10,7), nullable |
| occurred_at | DATETIME |
| created_at | DATETIME |

---

## 12.2 `violations`

| Column | Type |
|---|---|
| id | BIGINT PK |
| public_id | CHAR(26) UNIQUE |
| violation_reference | VARCHAR(80) UNIQUE |
| inspection_id | BIGINT FK inspections, nullable |
| park_id | BIGINT FK parks, nullable |
| operator_id | BIGINT FK operators, nullable |
| driver_id | BIGINT FK drivers, nullable |
| vehicle_id | BIGINT FK vehicles, nullable |
| category | VARCHAR(80) |
| description | TEXT |
| status | VARCHAR(30) |
| issued_by | BIGINT FK users |
| issued_at | DATETIME |
| resolved_by | BIGINT FK users, nullable |
| resolved_at | DATETIME, nullable |
| resolution | TEXT, nullable |
| created_at | DATETIME |
| updated_at | DATETIME |

---

# 13. Incident Management

## 13.1 `incidents`

| Column | Type |
|---|---|
| id | BIGINT PK |
| public_id | CHAR(26) UNIQUE |
| incident_reference | VARCHAR(80) UNIQUE |
| category | VARCHAR(80) |
| park_id | BIGINT FK parks |
| reporter_user_id | BIGINT FK users, nullable |
| operator_id | BIGINT FK operators, nullable |
| driver_id | BIGINT FK drivers, nullable |
| vehicle_id | BIGINT FK vehicles, nullable |
| description | TEXT |
| status | VARCHAR(30) |
| occurred_at | DATETIME |
| resolution | TEXT, nullable |
| resolved_by | BIGINT FK users, nullable |
| resolved_at | DATETIME, nullable |
| created_at | DATETIME |
| updated_at | DATETIME |

Statuses:

```text
reported
under_review
escalated
resolved
closed
```

Evidence uses `media_attachments`.

---

# 14. Complaint Management

## 14.1 `complaints`

| Column | Type |
|---|---|
| id | BIGINT PK |
| public_id | CHAR(26) UNIQUE |
| complaint_reference | VARCHAR(80) UNIQUE |
| complainant_name | VARCHAR(190) |
| complainant_phone | VARCHAR(30), nullable |
| complainant_email | VARCHAR(190), nullable |
| category | VARCHAR(80) |
| park_id | BIGINT FK parks, nullable |
| operator_id | BIGINT FK operators, nullable |
| driver_id | BIGINT FK drivers, nullable |
| vehicle_id | BIGINT FK vehicles, nullable |
| description | TEXT |
| source | VARCHAR(30) |
| status | VARCHAR(30) |
| assigned_to | BIGINT FK users, nullable |
| submitted_by | BIGINT FK users, nullable |
| resolution | TEXT, nullable |
| resolved_at | DATETIME, nullable |
| created_at | DATETIME |
| updated_at | DATETIME |

Statuses:

```text
submitted
received
assigned
under_review
resolved
closed
```

Sources:

```text
public_web
staff
help_desk
field
```

---

## 14.2 `complaint_notes`

| Column | Type |
|---|---|
| id | BIGINT PK |
| complaint_id | BIGINT FK complaints |
| user_id | BIGINT FK users |
| note | TEXT |
| is_internal | BOOLEAN default true |
| created_at | DATETIME |

Evidence uses `media_attachments`.

---

# 15. Notifications

## 15.1 `notifications`

Use Laravel's standard database notification table.

Core fields:

```text
id UUID/CHAR
type
notifiable_type
notifiable_id
data JSON
read_at
created_at
updated_at
```

Examples:

- registration approval
- payment success
- reconciliation exception
- licence expiry
- document expiry
- complaint assignment
- incident assignment

---

# 16. Audit & Governance

## 16.1 `activity_log`

Use Spatie Activitylog.

The activity log covers normal application events such as:

```text
user_created
driver_created
driver_approved
vehicle_registered
fee_configuration_changed
ticket_created
complaint_assigned
```

Configure activity logging deliberately; do not log secrets or full passwords/tokens.

---

## 16.2 `financial_audit_logs`

Purpose: append-only high-value financial event audit trail.

| Column | Type |
|---|---|
| id | BIGINT PK |
| event_id | CHAR(26) UNIQUE |
| actor_user_id | BIGINT FK users, nullable |
| event_type | VARCHAR(80) |
| entity_type | VARCHAR(190) |
| entity_id | BIGINT |
| business_reference | VARCHAR(190), nullable |
| amount | DECIMAL(15,2), nullable |
| currency | CHAR(3), nullable |
| ip_address | VARCHAR(45), nullable |
| user_agent | TEXT, nullable |
| payload | JSON, nullable |
| previous_hash | VARCHAR(128), nullable |
| entry_hash | VARCHAR(128) |
| occurred_at | DATETIME |

Recommended approach:

Create a hash chain for financial audit events:

```text
entry_hash = HASH(previous_hash + normalized event payload)
```

This does not replace database security, but provides additional tamper evidence.

Never expose a delete function for this table through the application UI.

---

# 17. System Configuration

## 17.1 `system_settings`

| Column | Type |
|---|---|
| id | BIGINT PK |
| setting_group | VARCHAR(80) |
| setting_key | VARCHAR(120) UNIQUE |
| value | JSON |
| is_public | BOOLEAN default false |
| updated_by | BIGINT FK users, nullable |
| created_at | DATETIME |
| updated_at | DATETIME |

Examples:

```text
system.name
system.currency
system.timezone
branding.government_logo
branding.powered_by
ticket.reference_prefix
receipt.reference_prefix
ticket.default_expiry_minutes
qr.public_base_url
session.idle_timeout
```

Secrets such as payment private keys must remain in environment/secrets management, not ordinary database settings.

---

# 18. Technical Reliability Tables

## 18.1 `idempotency_keys`

Recommended for payment/ticket action safety.

| Column | Type |
|---|---|
| id | BIGINT PK |
| idempotency_key | VARCHAR(190) UNIQUE |
| operation | VARCHAR(100) |
| user_id | BIGINT FK users, nullable |
| request_hash | VARCHAR(128) |
| response_status | INT, nullable |
| response_payload | JSON, nullable |
| expires_at | DATETIME |
| created_at | DATETIME |

---

## 18.2 Laravel Framework Tables

Use Laravel-standard tables as needed:

```text
jobs
job_batches
failed_jobs
sessions
cache
cache_locks
password_reset_tokens
personal_access_tokens
```

`personal_access_tokens` is required when Sanctum API token access is enabled.

---

# 19. Core Relationship Diagram

```text
lgas
 └── parks
      ├── park_route ── routes
      ├── operator_park ── operators
      ├── driver_assignments
      │     ├── drivers
      │     ├── vehicles
      │     ├── operators
      │     └── routes
      │
      └── tickets
            ├── revenue_heads
            ├── fee_configurations
            ├── drivers
            ├── vehicles
            ├── operators
            └── payments
                  ├── receipts
                  └── financial_transactions
                        ├── settlement_items ── settlements
                        ├── refunds
                        ├── financial_adjustments
                        └── reconciliation_items
                                  └── reconciliation_runs
```

---

# 20. Financial Integrity Rules

The following rules are mandatory.

## Rule 1 — Snapshot the fee

A ticket stores the amount/name/code that applied when the ticket was issued.

Never calculate historical ticket value using the current fee configuration.

## Rule 2 — Payment is separate from ticket

A ticket can exist without a successful payment.

A payment represents an attempt to settle a ticket.

## Rule 3 — Receipt follows successful payment

Do not create a valid receipt for a failed/pending payment.

## Rule 4 — Ledger is append-only

A successful payment creates a credit ledger entry.

Refunds/reversals create new debit entries.

Never overwrite the original transaction.

## Rule 5 — Idempotency

A repeated payment webhook/request must not create duplicate financial records.

## Rule 6 — Reconciliation is explicit

A successful payment is not automatically equivalent to a reconciled settlement.

## Rule 7 — No hard delete of finance

Tickets, payments, receipts, financial transactions, settlements and reconciliation records must not be hard-deleted through the application.

---

# 21. Key Unique Constraints

At minimum:

```text
users.email
users.username
lgas.code
parks.park_code
routes.route_code
operators.operator_number
drivers.driver_number
vehicles.vehicle_number
vehicles.registration_number
revenue_heads.code
tickets.ticket_reference
tickets.verification_token
payments.payment_reference
payments.idempotency_key
receipts.receipt_number
receipts.payment_id
financial_transactions.transaction_reference
settlements.settlement_reference
reconciliation_runs.reconciliation_reference
refunds.refund_reference
financial_adjustments.adjustment_reference
incidents.incident_reference
complaints.complaint_reference
```

---

# 22. High-Value Indexes

Indexes should be added for common filtering.

```text
tickets(issued_at)
tickets(park_id, issued_at)
tickets(lga_id, issued_at)
tickets(vehicle_id, issued_at)
tickets(driver_id, issued_at)
tickets(ticket_status, payment_status)

payments(status, paid_at)
payments(provider, provider_reference)

financial_transactions(occurred_at)
financial_transactions(transaction_type, occurred_at)

reconciliation_items(status)
reconciliation_runs(status, period_start, period_end)

drivers(phone)
drivers(licence_number)

vehicles(registration_number)
vehicles(status)

complaints(status, created_at)
incidents(status, occurred_at)
```

Do not add indexes blindly. Confirm query plans when production data volume grows.

---

# 23. Foreign Key Behavior

Recommended rules:

- master records referenced by financial data: `RESTRICT`
- optional operational relationships: `SET NULL` only where history remains understandable
- pivot/access relationships: `CASCADE` where deleting the pivot has no historical financial effect

Do not cascade-delete financial records.

---

# 24. Migration Order

Recommended Laravel migration order:

```text
01 users / auth framework
02 roles / permissions
03 lgas
04 parks
05 routes
06 park_route
07 operators
08 operator_park
09 operator_route
10 drivers
11 vehicles
12 driver_assignments
13 user access tables
14 media_attachments
15 revenue_heads
16 fee_configurations
17 tickets
18 payments
19 receipts
20 financial_transactions
21 settlements
22 settlement_items
23 reconciliation_runs
24 reconciliation_items
25 refunds
26 financial_adjustments
27 webhook_events
28 inspections
29 violations
30 incidents
31 complaints
32 complaint_notes
33 notifications
34 activity_log
35 financial_audit_logs
36 system_settings
37 login_activities
38 idempotency_keys
39 framework queue/cache/session tables
```

---

# 25. Seeder Strategy

Phase 1 should provide deterministic seeders for:

```text
roles
permissions
LGAs
demo users
parks
routes
operators
drivers
vehicles
assignments
revenue heads
fee configurations
tickets
payments
receipts
financial transactions
reconciliation runs
reconciliation exceptions
incidents
complaints
```

Recommended demo scale:

```text
10–20 parks
50+ operators
200+ drivers
250+ vehicles
5–10 revenue heads
2,000–5,000 tickets
mixed successful/failed/pending payments
multiple reconciliation exceptions
```

The seeder must never use real citizen personal data.

---

# 26. MySQL → PostgreSQL Compatibility Rules

To avoid rewriting the application later:

1. Do not use MySQL ENUM.
2. Do not depend on unsigned integer behavior in business logic.
3. Do not use vendor-specific SQL unless isolated.
4. Use Laravel query builder/Eloquent.
5. Use JSON only where appropriate.
6. Store booleans as Laravel booleans.
7. Use migrations rather than manual schema changes.
8. Do not depend on case-insensitive string behavior.
9. Add explicit unique/index constraints.
10. Keep money as decimals.

---

# 27. Scope Protection

Tables must not be added casually.

If a developer proposes a new table, ask:

1. Which Project Scope requirement does it satisfy?
2. Can the requirement be modeled using an existing domain?
3. Does it duplicate another source of truth?
4. Does it affect financial or historical integrity?
5. Is it Phase 1 or a future enhancement?

Any material schema addition must update this document to Version 1.1 or later.

---

# 28. Database Completion Criteria

The Phase 1 database foundation is complete when:

- all migrations execute from a clean database
- all foreign keys succeed
- seeders populate a realistic demo environment
- RBAC and scopes restrict data correctly
- park/operator/driver/vehicle relationships are queryable
- ticket creation snapshots the correct fee
- payment simulation produces valid financial records
- receipt generation works
- QR verification resolves safely
- financial ledger remains immutable
- reconciliation can detect both matches and exceptions
- audit records capture critical events
- reports can filter efficiently by date, LGA and park

---

# DATABASE BASELINE

This schema is **Database Baseline v1.0** for the Osun State Park Management System Phase 1 build.

Any change affecting financial entities, user access scope, ticketing, payments, reconciliation or audit history must be treated as an architectural change and reviewed before implementation.


---

# DOCUMENT 2 — SCREEN & PAGE INVENTORY

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
