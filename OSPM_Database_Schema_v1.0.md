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
