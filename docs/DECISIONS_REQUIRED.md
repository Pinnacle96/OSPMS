# Decisions requiring clarification

No material conflict blocks Milestones 0–3.

Route geography is derived through approved ParkRoute relationships rather than adding an unapproved LGA column; see ADR-007. Milestone 3 management permissions follow the existing granular authorization approach.

Before enabling delegated administration, define the exact “limited” user/RBAC access for State Administrator in Screen Inventory §33. Foundation defaults reserve user and role administration for explicitly permitted accounts; see ADR-003.

MySQL 8 validation is resolved using an isolated MySQL 8.4.11 instance on port 3308.

Repository license metadata was not supplied. It remains unset; Composer's strict schema validation reports this non-runtime warning. No license policy has been invented.
