# Decisions requiring clarification

No material conflict blocks Milestones 0–7.

Route geography is derived through approved ParkRoute relationships rather than adding an unapproved LGA column; see ADR-007. Milestone 3 management permissions follow the existing granular authorization approach.

Before enabling delegated administration, define the exact “limited” user/RBAC access for State Administrator in Screen Inventory §33. Foundation defaults reserve user and role administration for explicitly permitted accounts; see ADR-003.

MySQL 8 validation is resolved using an isolated MySQL 8.4.11 instance on port 3308.

Repository license metadata was not supplied. It remains unset; Composer's strict schema validation reports this non-runtime warning. No license policy has been invented.

Ticket expiry duration was not specified. Milestone 7 leaves expiry unset by default and supports a configurable duration for new tickets; confirm Government validity policy before enabling it. This does not block issuance or safe verification. See ADR-010.
