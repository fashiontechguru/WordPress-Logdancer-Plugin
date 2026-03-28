# LOG DANCER V2 DATABASE SCHEMA

## Purpose

This document proposes the primary database schema for Log Dancer v2.

The design goal is to support:

- structured event storage
- fast admin filtering and pagination
- severity and event-type summaries
- safe retention cleanup
- future expansion without frequent schema rewrites

The recommended primary storage backend is a custom WordPress database table.

---

## Recommended Table Name

Use the WordPress prefix:

```text
{$wpdb->prefix}logdancer_events
```

Examples:
- `wp_logdancer_events`
- `wp_7_logdancer_events` on multisite per-site tables if you choose site-local storage

If you later want network-global storage on multisite, that should be a separate design decision.

---

## Core Table Definition

Recommended fields:

```text
id
event_uuid
created_at_utc
created_at_local
source_type
event_type
severity
message
file_path
file_basename
line_number
plugin_slug
theme_slug
request_context
request_uri
user_id
user_roles
site_id
memory_usage
peak_memory_usage
event_hash
extra_json
```

---

## Proposed SQL Schema

This is a draft schema, not final migration code.

```sql
CREATE TABLE {$wpdb->prefix}logdancer_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    event_uuid CHAR(36) NOT NULL,
    created_at_utc DATETIME NOT NULL,
    created_at_local DATETIME NULL,
    source_type VARCHAR(50) NOT NULL,
    event_type VARCHAR(100) NOT NULL,
    severity VARCHAR(20) NOT NULL,
    message TEXT NOT NULL,
    file_path TEXT NULL,
    file_basename VARCHAR(255) NULL,
    line_number INT UNSIGNED NULL,
    plugin_slug VARCHAR(191) NULL,
    theme_slug VARCHAR(191) NULL,
    request_context VARCHAR(50) NULL,
    request_uri TEXT NULL,
    user_id BIGINT UNSIGNED NULL,
    user_roles VARCHAR(255) NULL,
    site_id BIGINT UNSIGNED NULL,
    memory_usage BIGINT UNSIGNED NULL,
    peak_memory_usage BIGINT UNSIGNED NULL,
    event_hash CHAR(64) NULL,
    extra_json LONGTEXT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY event_uuid (event_uuid),
    KEY created_at_utc (created_at_utc),
    KEY severity (severity),
    KEY source_type (source_type),
    KEY event_type (event_type),
    KEY plugin_slug (plugin_slug),
    KEY theme_slug (theme_slug),
    KEY request_context (request_context),
    KEY user_id (user_id),
    KEY site_id (site_id),
    KEY event_hash (event_hash)
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

Use `dbDelta()` for installation.

---

## Column-by-Column Rationale

### `id`
Type:
- `BIGINT UNSIGNED AUTO_INCREMENT`

Purpose:
- Internal primary key for ordering, pagination, and joins if ever needed.

Why keep it:
- Faster and simpler for admin pagination than UUID alone.

---

### `event_uuid`
Type:
- `CHAR(36)`

Purpose:
- Stable external identifier for a single event.

Why useful:
- Better for exports
- Better for support references
- Safer to expose in URLs than sequential `id` if detail pages are ever added

Alternative:
- `CHAR(32)` if storing UUID without dashes
- `BINARY(16)` if you want a tighter schema, but that adds complexity

Recommendation:
- Keep `CHAR(36)` for simplicity

---

### `created_at_utc`
Type:
- `DATETIME`

Purpose:
- Canonical event timestamp for ordering, reporting, and retention cleanup.

Rule:
- This should be the authoritative time field.

---

### `created_at_local`
Type:
- `DATETIME NULL`

Purpose:
- Convenience field if you want precomputed site-local timestamps.

Could be omitted:
- Yes, if you prefer computing local time during display.

Recommendation:
- Optional. You can remove this if you want a leaner table.

---

### `source_type`
Type:
- `VARCHAR(50)`

Examples:
- `fatal`
- `plugin`
- `theme`
- `updater`
- `http_api`
- `cron`
- `database`
- `php`
- `auth`
- `system`

Purpose:
- Broad grouping for filtering and charts.

---

### `event_type`
Type:
- `VARCHAR(100)`

Examples:
- `plugin_activated`
- `plugin_deactivated`
- `plugin_updated`
- `theme_switched`
- `fatal_error`
- `http_request_failed`
- `cron_overdue`
- `database_error`

Purpose:
- More specific event taxonomy for reports and UI.

---

### `severity`
Type:
- `VARCHAR(20)`

Recommended allowed values:
- `debug`
- `info`
- `notice`
- `warning`
- `error`
- `critical`

Purpose:
- Filtering, alerting, dashboard counters

Could be ENUM:
- Yes, but `VARCHAR` is easier to evolve with WordPress plugin upgrades

Recommendation:
- Keep `VARCHAR(20)`

---

### `message`
Type:
- `TEXT`

Purpose:
- Human-readable event summary

Examples:
- `Plugin activated: woocommerce/woocommerce.php`
- `Fatal error detected during shutdown`
- `HTTP request failed: api.example.com timed out`

Important:
- This must already be redacted before insert.

---

### `file_path`
Type:
- `TEXT NULL`

Purpose:
- Optional source file location for PHP/fatal events

Privacy:
- Should be redacted or omitted depending on policy

Alternative:
- Store only basename in production-safe mode

---

### `file_basename`
Type:
- `VARCHAR(255) NULL`

Purpose:
- Fast, low-risk display field for UI filtering

Why separate it:
- Lets you hide full path but still display useful source hints

---

### `line_number`
Type:
- `INT UNSIGNED NULL`

Purpose:
- Source code line for PHP/fatal/database events where relevant

---

### `plugin_slug`
Type:
- `VARCHAR(191) NULL`

Purpose:
- Filter and correlate events by plugin

Examples:
- `woocommerce`
- `wordfence`
- `my-custom-plugin`

Note:
- Use 191 for index compatibility under older MySQL / utf8mb4 constraints

---

### `theme_slug`
Type:
- `VARCHAR(191) NULL`

Purpose:
- Filter events by theme when applicable

---

### `request_context`
Type:
- `VARCHAR(50) NULL`

Recommended values:
- `admin`
- `front`
- `ajax`
- `rest`
- `cron`
- `cli`
- `unknown`

Purpose:
- Helps administrators understand where a problem occurred

---

### `request_uri`
Type:
- `TEXT NULL`

Purpose:
- Optional route-level context

Privacy:
- Should be stripped of sensitive query strings before insert

Recommendation:
- Store path-only by default, or redacted full URI in advanced mode

---

### `user_id`
Type:
- `BIGINT UNSIGNED NULL`

Purpose:
- Identify actor for admin/plugin/theme/update actions

Privacy:
- Usually acceptable for an admin diagnostics plugin
- avoid storing usernames redundantly if not needed

---

### `user_roles`
Type:
- `VARCHAR(255) NULL`

Purpose:
- Quick context for actions triggered by privileged users

Format options:
- comma-separated role list
- JSON array

Recommendation:
- comma-separated string is fine for v2

---

### `site_id`
Type:
- `BIGINT UNSIGNED NULL`

Purpose:
- Useful for multisite awareness even if tables are site-local

---

### `memory_usage`
Type:
- `BIGINT UNSIGNED NULL`

Purpose:
- Raw bytes at event time

Why useful:
- Helps correlate out-of-memory conditions and noisy plugins

---

### `peak_memory_usage`
Type:
- `BIGINT UNSIGNED NULL`

Purpose:
- Peak memory at event time

---

### `event_hash`
Type:
- `CHAR(64) NULL`

Purpose:
- Deduplication and aggregation helper

Typical value:
- SHA-256 hash of normalized event signature

Possible signature inputs:
- event_type
- severity
- message template
- basename
- line number
- plugin slug
- request context

Use case:
- collapse repeated identical events
- support "count repeated occurrences" later

---

### `extra_json`
Type:
- `LONGTEXT NULL`

Purpose:
- Flexible extension field for structured metadata that does not deserve a dedicated column yet

Examples:
```json
{
  "http_host": "api.wordpress.org",
  "http_method": "POST",
  "response_code": 500,
  "wp_error_code": "http_request_failed",
  "upgrader_action": "update",
  "bulk": true
}
```

Recommendation:
- JSON-encode before insert
- keep it redacted
- keep top-level keys stable where possible

---

## Recommended Index Strategy

Minimum useful indexes:

- `PRIMARY KEY (id)`
- `UNIQUE KEY event_uuid (event_uuid)`
- `KEY created_at_utc (created_at_utc)`
- `KEY severity (severity)`
- `KEY source_type (source_type)`
- `KEY event_type (event_type)`

Useful secondary indexes:
- `KEY plugin_slug (plugin_slug)`
- `KEY theme_slug (theme_slug)`
- `KEY request_context (request_context)`
- `KEY user_id (user_id)`
- `KEY site_id (site_id)`
- `KEY event_hash (event_hash)`

Why not index everything:
- inserts should stay reasonably fast
- many low-selectivity indexes are wasted overhead

---

## Recommended Query Patterns

### 1. Recent events page
Typical filters:
- date range
- severity
- event_type
- source_type
- plugin_slug
- request_context

Typical sort:
- `ORDER BY created_at_utc DESC`

Typical pagination:
- `LIMIT X OFFSET Y`

---

### 2. Dashboard counters
Examples:
- total events last 24h
- critical events last 7d
- fatal events last 7d
- top 5 event types

These should use indexed columns only whenever possible.

---

### 3. Retention cleanup
Typical query:
- delete rows older than N days

Recommended filter:
- `created_at_utc < cutoff`

This makes the timestamp index important.

---

## Recommended Optional Secondary Table for Aggregation

This is optional and should not be built first.

Table name:
```text
{$wpdb->prefix}logdancer_event_rollups
```

Possible columns:
- `event_hash`
- `first_seen_utc`
- `last_seen_utc`
- `occurrence_count`
- `last_severity`
- `event_type`
- `source_type`

Purpose:
- fast summary counts for repeated events
- cleaner admin overview for noisy systems

Recommendation:
- do not build this in the first pass unless you know noise volume will be high

---

## Settings Table Usage

Do not create a dedicated settings table.

Use WordPress options:
- `logdancer_settings`
- `logdancer_version`

This is simpler and more WordPress-native.

---

## Migration Strategy

### Version tracking
Store plugin/schema version separately.

Recommended options:
- `logdancer_version`
- `logdancer_db_version`

### Migration rules
- If no table exists, create it.
- If DB version is older, run `dbDelta()` and any follow-up data fixes.
- Keep migrations idempotent.

---

## Data Retention Strategy

Recommended defaults:
- retain for 30 days
- configurable in settings

Cleanup methods:
1. scheduled WP-Cron cleanup
2. manual admin cleanup action
3. optional clear-by-filter action later

Important:
- cleanup should operate on `created_at_utc`
- large deletes may need batching on big sites

---

## Export Strategy

### CSV exports
Use:
- selected visible fields only
- flattened `extra_json` summary or omit it

Suggested CSV fields:
- event_uuid
- created_at_utc
- severity
- source_type
- event_type
- message
- file_basename
- line_number
- plugin_slug
- theme_slug
- request_context
- user_id

### JSON exports
Use:
- full structured event with `extra_json`
- same redaction policy as admin display unless verbose export explicitly allowed

---

## Multisite Options

You need to choose one of these approaches.

### Option A - Per-site tables
Examples:
- `wp_2_logdancer_events`
- `wp_3_logdancer_events`

Pros:
- simpler capability boundaries
- natural site isolation

Cons:
- no network-wide overview by default

### Option B - Network-wide table
Example:
- `wp_logdancer_events_network`

Pros:
- central monitoring

Cons:
- more complex permissions and filtering
- more care needed for site scoping

Recommendation:
- start with per-site tables unless your primary use case is network admin monitoring

---

## Privacy Notes

These columns deserve special policy review:

- `file_path`
- `request_uri`
- `user_id`
- `user_roles`
- `extra_json`

Recommended defaults:
- redact `file_path`
- store path-only or redacted `request_uri`
- store `user_id` only where useful
- keep `extra_json` tightly controlled

Never store raw:
- cookies
- authorization headers
- passwords
- nonces
- tokens
- secret keys

---

## Suggested PHP Insert Shape

A normalized PHP event record might look like this before insert:

```php
[
    'event_uuid'        => '550e8400-e29b-41d4-a716-446655440000',
    'created_at_utc'    => gmdate('Y-m-d H:i:s'),
    'created_at_local'  => current_time('mysql'),
    'source_type'       => 'plugin',
    'event_type'        => 'plugin_activated',
    'severity'          => 'info',
    'message'           => 'Plugin activated: woocommerce/woocommerce.php',
    'file_path'         => null,
    'file_basename'     => null,
    'line_number'       => null,
    'plugin_slug'       => 'woocommerce',
    'theme_slug'        => null,
    'request_context'   => 'admin',
    'request_uri'       => '/wp-admin/plugins.php',
    'user_id'           => 1,
    'user_roles'        => 'administrator',
    'site_id'           => 1,
    'memory_usage'      => memory_get_usage(true),
    'peak_memory_usage' => memory_get_peak_usage(true),
    'event_hash'        => hash('sha256', 'plugin_activated|woocommerce|admin'),
    'extra_json'        => wp_json_encode([
        'plugin_file' => 'woocommerce/woocommerce.php',
        'bulk'        => false,
    ]),
]
```

---

## Recommended MVP Schema

If you want the leanest viable v2, keep these columns only:

```text
id
event_uuid
created_at_utc
source_type
event_type
severity
message
file_basename
line_number
plugin_slug
theme_slug
request_context
user_id
event_hash
extra_json
```

That smaller schema is enough for:
- recent events view
- filtering
- plugin/theme correlation
- fatal diagnostics
- dedup groundwork

You can add `request_uri`, `file_path`, and memory metrics later if needed.

---

## Definition of Done

The schema is good enough when:

- event inserts are simple and reliable,
- recent-event queries are fast,
- the most useful filter fields are indexed,
- retention cleanup can run cheaply,
- privacy-sensitive fields remain optional and controlled,
- future features can be added via `extra_json` without breaking the table.
