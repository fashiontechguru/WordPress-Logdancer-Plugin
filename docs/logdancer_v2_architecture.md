# LOG DANCER V2 ARCHITECTURE

## Purpose

Log Dancer v2 is a WordPress observability plugin for administrators. Its purpose is to surface operational events inside WordPress without destabilizing runtime behavior or exposing sensitive data unnecessarily.

The architecture is designed around five principles:

1. Observe WordPress-native events first.
2. Capture fatal errors safely at shutdown.
3. Centralize logging through one structured write pipeline.
4. Redact sensitive data before persistence.
5. Keep admin visibility high and runtime side effects low.

---

## Top-Level Design

The plugin should be organized into these layers:

1. Bootstrap layer
2. Lifecycle layer
3. Runtime orchestration layer
4. Event capture layer
5. Logging and storage layer
6. Redaction and policy layer
7. Admin UI layer
8. Export and maintenance layer

Data should flow in one direction:

Event source -> Event builder -> Redactor -> Logger -> Storage -> Admin UI / Export

No component should write directly to storage except the logger/storage layer.

---

## Recommended File Tree

```text
logdancer/
    logdancer.php
    uninstall.php
    readme.txt
    assets/
        css/
            admin.css
        js/
            admin.js
    includes/
        class-logdancer-plugin.php
        class-logdancer-activator.php
        class-logdancer-deactivator.php
        class-logdancer-settings.php
        class-logdancer-policy.php
        class-logdancer-redactor.php
        class-logdancer-request-context.php
        class-logdancer-logger.php
        class-logdancer-storage.php
        class-logdancer-db-storage.php
        class-logdancer-file-storage.php
        class-logdancer-rotation.php
        class-logdancer-retention.php
        class-logdancer-shutdown-monitor.php
        class-logdancer-events.php
        class-logdancer-health-check.php
        class-logdancer-admin.php
        class-logdancer-admin-table.php
        class-logdancer-exporter.php
        class-logdancer-installer.php
        helpers.php
    admin/
        views/
            page-overview.php
            page-events.php
            page-settings.php
            page-health.php
            page-export.php
```

---

## Class Responsibilities

### 1. `logdancer.php`
This is the only file WordPress needs to load directly.

Responsibilities:
- Prevent direct access.
- Define plugin constants.
- Require core class files.
- Register activation and deactivation hooks.
- Instantiate the main plugin class.
- Call the main plugin boot method.

Should not contain:
- UI rendering logic
- storage logic
- event capture logic
- direct SQL
- file writes beyond bootstrap emergency cases

Suggested constants:
- `LOGDANCER_VERSION`
- `LOGDANCER_FILE`
- `LOGDANCER_DIR`
- `LOGDANCER_URL`
- `LOGDANCER_BASENAME`

---

### 2. `LogDancer_Plugin`
This is the orchestrator.

Responsibilities:
- Build dependencies.
- Register hooks.
- Initialize admin and runtime subsystems.
- Keep one authoritative copy of settings in memory.
- Decide which features are enabled.

Suggested methods:
- `boot()`
- `register_runtime_hooks()`
- `register_admin_hooks()`
- `build_services()`
- `is_admin_request()`
- `is_feature_enabled($feature)`

This class should not contain business logic for logging or storage itself. It should wire components together.

---

### 3. `LogDancer_Activator`
Responsibilities:
- Validate PHP and WordPress requirements.
- Create default settings.
- Create database tables if DB storage is enabled.
- Create filesystem paths if file storage is enabled.
- Create protection files if file storage is used.
- Store plugin version.

Suggested methods:
- `activate()`
- `check_requirements()`
- `create_default_settings()`
- `prepare_storage()`
- `write_protection_files()`

---

### 4. `LogDancer_Deactivator`
Responsibilities:
- Stop scheduled maintenance hooks if any.
- Leave settings and data in place.
- Avoid destructive cleanup.

Suggested methods:
- `deactivate()`
- `clear_scheduled_tasks()`

Important rule:
Deactivation should not delete logs or settings. That belongs in uninstall.

---

### 5. `uninstall.php`
Responsibilities:
- Remove settings options.
- Remove custom DB tables if user opted into full cleanup.
- Remove log files if user opted into full cleanup.

This should include:
- capability checks not needed because uninstall is invoked by WordPress internals,
- strong `defined('WP_UNINSTALL_PLUGIN')` guard.

---

### 6. `LogDancer_Settings`
Responsibilities:
- Provide defaults.
- Load current settings.
- Sanitize settings input.
- Merge saved settings with defaults.
- Handle migrations between versions.

Suggested methods:
- `defaults()`
- `get()`
- `get_all()`
- `sanitize($input)`
- `migrate($stored_version, $current_version)`

Suggested option name:
- `logdancer_settings`

Suggested secondary option:
- `logdancer_version`

---

### 7. `LogDancer_Policy`
Responsibilities:
- Convert settings into runtime policy decisions.
- Answer questions like:
  - should this event type be recorded?
  - should file paths be redacted?
  - should IPs be stored?
  - should exports include full detail?

Suggested methods:
- `allow_event_type($type)`
- `should_redact_paths()`
- `should_redact_query_strings()`
- `should_store_ip()`
- `should_capture_advanced_php_warnings()`

This avoids scattering policy decisions across unrelated classes.

---

### 8. `LogDancer_Request_Context`
Responsibilities:
- Build a normalized request context object for each event.

Context fields might include:
- request type: admin, front, ajax, rest, cron, cli
- current user ID
- current user role(s)
- request URI
- site ID
- multisite state
- current screen ID if admin
- memory usage snapshot

Suggested methods:
- `build_context()`
- `detect_request_type()`
- `get_current_user_data()`
- `get_request_uri()`

This class should avoid collecting sensitive data beyond what policy allows.

---

### 9. `LogDancer_Redactor`
Responsibilities:
- Sanitize and redact event data before persistence.

What it should handle:
- query string stripping
- nonce masking
- token masking
- API key masking
- cookie stripping
- auth header stripping
- path redaction
- optional email masking
- optional IP anonymization

Suggested methods:
- `redact_event(array $event)`
- `redact_message($message)`
- `redact_path($path)`
- `redact_request_uri($uri)`
- `redact_headers(array $headers)`

This class should be called for every event before storage.

---

### 10. `LogDancer_Logger`
This is the central write pipeline.

Responsibilities:
- Accept normalized event arrays.
- Validate required keys.
- Apply redaction.
- Add common metadata.
- Pass the event to storage.
- Handle storage failures gracefully.

Suggested methods:
- `log(array $event)`
- `build_event($event_type, $severity, $message, array $extra = [])`
- `normalize(array $event)`
- `validate(array $event)`

Important rule:
No event source should bypass this class.

---

### 11. `LogDancer_Storage`
This is an interface-like abstraction or base class.

Responsibilities:
- Define the persistence contract.

Suggested methods:
- `write(array $event)`
- `query(array $filters = [])`
- `count(array $filters = [])`
- `delete(array $filters = [])`
- `health_check()`

Concrete implementations:
- `LogDancer_DB_Storage`
- `LogDancer_File_Storage`

Recommended default:
Database storage for browsing and filtering in wp-admin.

---

### 12. `LogDancer_DB_Storage`
Responsibilities:
- Create and manage the custom event table.
- Insert structured rows.
- Support filtered queries for admin UI.
- Support pagination and counts.
- Support cleanup by age.

Suggested methods:
- `create_table()`
- `write(array $event)`
- `query(array $filters)`
- `count(array $filters)`
- `delete_older_than($days)`

Important implementation notes:
- Use `$wpdb`.
- Escape and prepare properly.
- Add indexes for timestamp, severity, event_type, source_type.

---

### 13. `LogDancer_File_Storage`
Use only if file mirroring or alternate storage is explicitly enabled.

Responsibilities:
- Write JSON Lines or structured file records.
- Ensure directory exists.
- Use locking.
- Coordinate with rotation and retention classes.
- Report protection state.

Suggested methods:
- `write(array $event)`
- `get_log_path()`
- `ensure_directory()`
- `ensure_protection_files()`
- `health_check()`

Recommended file format:
JSON Lines, one record per line.

---

### 14. `LogDancer_Rotation`
Responsibilities:
- Decide when file logs should rotate.
- Rename archives safely.
- Trigger archive creation based on size or age.

Suggested methods:
- `should_rotate($path)`
- `rotate($path)`
- `archive_name()`

---

### 15. `LogDancer_Retention`
Responsibilities:
- Delete old file archives.
- Delete old DB rows.
- Run scheduled cleanup.

Suggested methods:
- `cleanup_files($days)`
- `cleanup_db($days)`
- `run()`

Cleanup trigger:
- daily WP-Cron task

---

### 16. `LogDancer_Shutdown_Monitor`
Responsibilities:
- Register a shutdown function.
- Capture fatal-like errors using `error_get_last()`.
- Record a critical event with minimal dependencies.

Suggested methods:
- `register()`
- `handle_shutdown()`
- `is_fatal_error(array $error)`
- `build_fatal_event(array $error)`

Important constraints:
- Must not assume full WordPress environment is healthy.
- Must not create recursion if logging itself errors.
- Must avoid large or complex calls.

---

### 17. `LogDancer_Events`
Responsibilities:
- Register WordPress-native hooks for operational events.
- Build event records and send them to the logger.

Suggested categories:
- plugin lifecycle
- theme lifecycle
- updater events
- HTTP API failures
- cron anomalies
- optional database errors
- optional auth/admin operational events

Suggested methods:
- `register_hooks()`
- `capture_plugin_activation(...)`
- `capture_plugin_deactivation(...)`
- `capture_theme_switch(...)`
- `capture_upgrader_process_complete(...)`
- `capture_http_api_debug(...)`
- `capture_cron_issue(...)`

This class should not write to storage directly.

---

### 18. `LogDancer_Health_Check`
Responsibilities:
- Evaluate environment health.
- Report findings to admin UI.

Check categories:
- DB table exists
- storage writable
- protection files present
- cron running
- retention scheduled
- advanced mode enabled
- recent fatal activity
- plugin version migrations pending

Suggested methods:
- `run_all()`
- `check_storage()`
- `check_protection()`
- `check_cron()`
- `check_schema()`

---

### 19. `LogDancer_Admin`
Responsibilities:
- Register admin menus.
- Register settings.
- Route admin pages.
- Handle action submissions like export and clear.
- Enforce capability checks and nonces.

Suggested methods:
- `register_menu()`
- `register_settings()`
- `render_overview_page()`
- `render_events_page()`
- `render_settings_page()`
- `render_health_page()`
- `render_export_page()`
- `handle_actions()`

Suggested capability:
- `manage_options`

---

### 20. `LogDancer_Admin_Table`
Responsibilities:
- Render recent events in a paginated table.
- Support filters for severity, type, source, date range.

This can extend `WP_List_Table` if desired.

Suggested methods:
- `prepare_items()`
- `get_columns()`
- `get_sortable_columns()`
- `column_default($item, $column_name)`
- `get_bulk_actions()`

---

### 21. `LogDancer_Exporter`
Responsibilities:
- Export filtered event sets.
- Support CSV for summaries and JSON for full fidelity.
- Enforce capability checks and nonce validation.
- Apply export-aware redaction policy.

Suggested methods:
- `export_csv(array $filters)`
- `export_json(array $filters)`
- `stream_download($filename, $content_type, $content)`

---

### 22. `helpers.php`
Responsibilities:
- Hold narrow, reusable, low-risk helper functions only.

Examples:
- safe array get helper
- small formatting helpers
- version compare wrappers

Do not let this become a dumping ground for major logic.

---

## Runtime Execution Model

### Boot Sequence

1. WordPress loads `logdancer.php`.
2. Constants are defined.
3. Core classes are included.
4. Activation/deactivation hooks are registered.
5. `LogDancer_Plugin` is instantiated.
6. The plugin loads settings.
7. Services are built.
8. Runtime hooks are registered.
9. Admin hooks are registered if in wp-admin.
10. Shutdown monitor is registered if fatal capture is enabled.

This order matters. Settings and policy should exist before hooks are registered so hook decisions are not scattered.

---

## Data Flow

### Event Path

1. A WordPress hook fires or shutdown monitor detects a fatal.
2. An event source class builds a raw event array.
3. The event array is passed to the logger.
4. The logger normalizes and validates it.
5. The redactor sanitizes it according to policy.
6. The storage backend persists it.
7. Admin UI queries storage and displays redacted records.
8. Exporter streams filtered records out on demand.

This flow should be strict. Nothing else should write events directly.

---

## Event Schema

Recommended canonical keys:

```text
id
timestamp_utc
timestamp_local
source_type
event_type
severity
message
file
file_basename
line
plugin_slug
theme_slug
request_context
request_uri
user_id
user_roles
site_id
memory_usage
peak_memory_usage
extra_json
```

Recommended controlled vocabularies:

### `source_type`
- php
- fatal
- plugin
- theme
- updater
- cron
- http_api
- database
- auth
- filesystem
- system

### `severity`
- debug
- info
- notice
- warning
- error
- critical

### `request_context`
- admin
- front
- ajax
- rest
- cron
- cli
- unknown

---

## Settings Model

One option array is preferable.

Suggested structure:

```php
[
    'enabled' => true,
    'storage_mode' => 'database',
    'capture_fatals' => true,
    'capture_plugin_events' => true,
    'capture_theme_events' => true,
    'capture_updater_events' => true,
    'capture_http_api_failures' => true,
    'capture_cron_anomalies' => true,
    'capture_db_errors' => false,
    'capture_auth_events' => false,
    'capture_php_warnings_advanced' => false,
    'privacy_mode' => 'balanced',
    'redact_paths' => true,
    'redact_query_strings' => true,
    'anonymize_ip' => true,
    'retention_days' => 30,
    'max_log_size_mb' => 5,
    'full_cleanup_on_uninstall' => false,
]
```

---

## Admin Information Architecture

Recommended admin pages:

### 1. Overview
Purpose:
- Fast snapshot of recent health and activity.

Widgets:
- events in last 24h
- critical events in last 7d
- last fatal event
- top event types
- storage health
- cron health

### 2. Events
Purpose:
- Paginated list of recent records.

Filters:
- date range
- severity
- event type
- source type
- request context
- plugin slug

### 3. Settings
Purpose:
- Feature flags and privacy controls.

Sections:
- capture settings
- storage settings
- privacy settings
- retention settings
- advanced mode warnings

### 4. Health
Purpose:
- Self-diagnostics and environment checks.

### 5. Export
Purpose:
- Download filtered CSV or JSON data.
- Clear records with confirmation.

---

## Security Boundaries

These are architectural rules, not optional suggestions.

1. Do not expose log files over the web intentionally.
2. Do not make exports accessible without `manage_options`.
3. Do not trust request data.
4. Do not store secrets unless explicitly necessary.
5. Do not let the UI render raw unescaped event content.
6. Do not replace PHP's error handler by default.
7. Do not delete data on deactivation.
8. Do not let event sources write directly to storage.

---

## Performance Boundaries

1. Do not write protection files on every normal page load.
2. Do not log extremely noisy classes of events without throttling.
3. Do not run large DB queries without pagination.
4. Do not load full event bodies on overview widgets unless necessary.
5. Use indexes on event timestamp, severity, and type fields.
6. Aggregate or deduplicate repeated identical events where practical.

---

## Recommended MVP Architecture

If you want the smallest solid v2, build only:

- `logdancer.php`
- `class-logdancer-plugin.php`
- `class-logdancer-activator.php`
- `class-logdancer-deactivator.php`
- `class-logdancer-settings.php`
- `class-logdancer-policy.php`
- `class-logdancer-redactor.php`
- `class-logdancer-request-context.php`
- `class-logdancer-logger.php`
- `class-logdancer-db-storage.php`
- `class-logdancer-shutdown-monitor.php`
- `class-logdancer-events.php`
- `class-logdancer-admin.php`
- `uninstall.php`

That is enough for:
- safe bootstrap
- fatal capture
- plugin/theme/update event logging
- admin viewer
- structured DB storage
- basic settings and privacy controls

---

## Build Order

1. Bootstrap and constants
2. Settings and policy
3. Logger and DB storage
4. Activator and uninstall
5. Shutdown fatal monitor
6. WordPress event hooks
7. Admin events page
8. Health checks
9. Export
10. Optional file storage and advanced PHP warning capture

---

## Definition of Done

The architecture is implemented correctly when:

- the plugin boots from a thin bootstrap file,
- each subsystem has a clear responsibility,
- event capture never bypasses the logger,
- redaction occurs before persistence,
- admins can review recent events safely,
- retention and cleanup are controlled,
- no default behavior destabilizes WordPress runtime.
