
# LOG DANCER V2 REFACTOR PUNCH LIST

## Goal
Refactor Log Dancer from a proof-of-concept PHP error logger into a safer, more useful,
administrator-focused WordPress observability plugin that:

1. Does not destabilize WordPress or other plugins
2. Does not expose sensitive information unnecessarily
3. Provides meaningful under-the-hood transparency
4. Is maintainable and extensible
5. Follows WordPress conventions for lifecycle, security, and admin UX

---

# Phase 0 — Define the Target Product

Objective: Lock scope before touching code.

Tasks:
- Define a precise product purpose statement
- Decide plugin role (diagnostics vs debugging vs audit tool)
- Define supported environments (Apache, nginx, IIS, multisite)
- Define data sensitivity policy
- Define retention policy (14–30 days recommended)
- Define feature flags (fatal errors, plugin events, cron failures, etc.)

Deliverable:
LOGDANCER_V2_SCOPE.md

---

# Phase 1 — Restructure Plugin File Layout

Recommended structure:

logdancer/
    logdancer.php
    uninstall.php
    readme.txt
    assets/
    includes/
        class-logdancer-plugin.php
        class-logdancer-activator.php
        class-logdancer-deactivator.php
        class-logdancer-admin.php
        class-logdancer-logger.php
        class-logdancer-storage.php
        class-logdancer-redactor.php
        class-logdancer-events.php
        class-logdancer-shutdown-monitor.php
        class-logdancer-settings.php
        class-logdancer-exporter.php
        helpers.php
    admin/
        views/
            dashboard.php
            settings.php
            log-table.php

Tasks:
- Create bootstrap plugin file
- Move lifecycle logic to classes
- Separate logging, storage, UI, and event handling

Deliverable:
New plugin scaffold

---

# Phase 2 — Clean Up Lifecycle Management

Tasks:
- Merge activation hooks
- Move destructive cleanup to uninstall.php
- Preserve settings during deactivation
- Add versioned options
- Add migration logic

Deliverables:
Unified activation callback
Safe deactivation
uninstall.php

---

# Phase 3 — Remove or Contain Global Error Handling

Tasks:
- Remove default set_error_handler()
- Make PHP warning capture opt-in
- Preserve previous handler if advanced mode enabled
- Avoid suppressing native PHP error handling

Deliverable:
Safe runtime behavior

---

# Phase 4 — Add Fatal Shutdown Capture

Tasks:
- Register shutdown handler
- Use error_get_last()
- Capture fatal-like errors
- Log minimal metadata
- Prevent recursive failures

Deliverable:
class-logdancer-shutdown-monitor.php

---

# Phase 5 — Structured Logging Schema

Suggested event record fields:

- id
- timestamp_utc
- source_type
- event_type
- severity
- message
- file
- line
- plugin_slug
- request_context
- request_uri
- user_id
- memory_usage
- extra_json

Tasks:
- Define event categories
- Define severity levels
- Implement structured logger

Deliverable:
Standard event schema

---

# Phase 6 — Harden Log Storage

Tasks:
- Prefer DB table storage
- Implement file locking
- Add log rotation
- Implement retention cleanup
- Add protection files (.htaccess, web.config, index.php)

Deliverable:
Hardened storage layer

---

# Phase 7 — Data Redaction & Privacy Controls

Tasks:
- Build redaction class
- Redact tokens, cookies, nonces
- Strip query strings
- Anonymize IP addresses
- Implement privacy mode settings

Deliverable:
class-logdancer-redactor.php

---

# Phase 8 — Observe WordPress Events

Capture:

Plugin lifecycle:
- activation
- deactivation
- updates

Theme events:
- theme switch
- theme updates

System events:
- HTTP API failures
- cron anomalies
- database errors (optional)

Deliverable:
class-logdancer-events.php

---

# Phase 9 — Admin Dashboard

Tabs:

1. Overview
2. Recent Events
3. Filters/Search
4. Settings
5. Health Check
6. Export

Tasks:
- Add admin menu
- Add capability checks
- Add filters and pagination
- Add log viewer
- Add export/clear controls

Deliverable:
Admin UI implementation

---

# Phase 10 — Settings Architecture

Example settings:

logdancer_settings = [
  enabled,
  capture_fatals,
  capture_plugin_events,
  capture_theme_events,
  capture_http_api_failures,
  capture_cron_anomalies,
  capture_php_warnings_advanced,
  privacy_mode,
  retention_days,
  max_log_size_mb
]

Tasks:
- Single options array
- Validation callbacks
- Migration support

Deliverable:
Settings system

---

# Phase 11 — Export Tools

Tasks:
- CSV export
- JSON export
- Protected download endpoints
- Filter by date/type
- Diagnostic bundle generator

Deliverable:
Exporter class

---

# Phase 12 — Health Checks

Checks:

- storage writable
- log protection enabled
- cron status
- plugin version state
- fatal error detection status

Deliverable:
Health check panel

---

# Phase 13 — Security Hardening

Tasks:
- Capability checks
- Nonce protection
- Escape output
- Sanitize inputs
- Prevent log injection
- Prevent unauthorized exports

Deliverable:
Security audit pass

---

# Phase 14 — Performance & Noise Control

Tasks:
- Deduplicate repeated events
- Aggregate recurring errors
- Add rate limiting
- Add pagination
- Index DB tables

Deliverable:
Efficient logging system

---

# Phase 15 — Multisite Compatibility

Tasks:
- Decide per-site vs network logs
- Define admin visibility
- Test across Apache, nginx, IIS

Deliverable:
Compatibility spec

---

# Phase 16 — Testing Plan

Manual tests:

- Activation / deactivation
- Storage permissions
- Fatal error simulation
- Plugin updates
- Theme switch
- HTTP API failures

Automated tests:

- settings validation
- redaction
- logging pipeline
- rotation logic

Deliverable:
TEST_PLAN.md

---

# Phase 17 — Documentation

Tasks:
- Rewrite plugin readme
- Add admin help text
- Provide server configuration notes
- Add changelog

Deliverable:
Complete documentation

---

# Phase 18 — Optional Future Features

Possible expansions:

- File integrity monitoring
- Slack/email alerting
- REST monitoring API
- WP-CLI integration
- Diagnostic bundles
- Change timeline view

---

# Recommended Implementation Order

Stage 1 — Safety
- plugin restructure
- remove global error handler
- fatal shutdown capture
- structured logger
- hardened storage

Stage 2 — Utility
- WordPress event hooks
- admin dashboard
- settings system

Stage 3 — Polish
- exports
- health checks
- privacy controls
- multisite support

---

# Minimum Viable V2

- Safe bootstrap
- Fatal shutdown logging
- Structured event storage
- Admin event viewer
- Plugin/theme lifecycle logging
- Basic export
- Privacy redaction

---

# Success Criteria

Refactor is complete when:

- Plugin no longer replaces PHP error handling
- Captures meaningful operational events
- Logs stored securely
- Admin UI provides visibility
- Sensitive data protected
