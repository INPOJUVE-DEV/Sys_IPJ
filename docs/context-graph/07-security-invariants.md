# Security Invariants

## Core principles

- Sys_IPJ is the primary operational system for beneficiary registration.
- Administrative routes must remain protected by role middleware.
- Public API surfaces must remain rate-limited.
- Sensitive operational changes should remain auditable.

## Authentication

- Laravel Breeze is the base authentication layer.
- Spatie Permission manages authorization.
- Role checks must exist before operational actions.

## Data integrity

- Beneficiary records should preserve CURP consistency.
- Municipio and seccional mappings must remain internally consistent.
- Assignment flows must preserve operational traceability.

## Infrastructure

- External MySQL connectivity must be validated before app bootstrap.
- APP_KEY must exist in all deployed environments.
- Production cache commands should execute after deploy.

## Integration

- API_TJ integrations should only consume minimum required data.
- Cross-system identity should rely on CURP.
- Staging flows should remain auditable before final insertion.
