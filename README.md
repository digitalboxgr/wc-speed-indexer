# WC Speed Indexer & Dashboard

WordPress/WooCommerce plugin for database index management, performance diagnostics, and DB server tuning recommendations.

The plugin is designed for WooCommerce stores and larger WordPress sites where slow queries often involve tables such as `wp_postmeta`, `wp_termmeta`, `wp_term_relationships`, `wp_options`, and WooCommerce order metadata tables.

## Features

- Adds or verifies managed indexes on selected WordPress/WooCommerce database tables.
- Adds an admin menu page named **DB Indexer**.
- Shows a dashboard with:
  - table name,
  - row count,
  - index size,
  - managed indexes,
  - missing/optimized status.
- Provides a manual recheck/apply indexes button.
- Shows admin notices for success or failure.
- Shows read-only diagnostics for managed tables, missing indexes, autoloaded options, object cache, and DB server tuning.
- Shows WooCommerce-specific diagnostics when WooCommerce is active:
  - product lookup table status,
  - product count vs lookup table row count,
  - pending product lookup update actions,
  - HPOS status.
- Safely skips optional WooCommerce/HPOS tables when they do not exist.
- Stores the last successful optimization timestamp in the `wcsi_last_optimization` option.
- Uses the `wc-speed-indexer` text domain and is ready for translation files under `/languages`.
- Does not apply index changes automatically on activation. Manual index changes require an explicit backup confirmation checkbox.

## Diagnostics

The dashboard includes read-only diagnostics to help identify whether a performance issue is likely database-related.

It shows:

- how many managed tables exist,
- how many managed indexes are missing,
- autoloaded options count and total size,
- whether persistent object cache is detected,
- the largest managed tables by rows, data size, and index size,
- simple health signals for missing indexes or very large tables,
- DB server type, MySQL or MariaDB,
- runtime database variables from `SHOW VARIABLES`,
- current values compared with common `[mysqld]` recommendations,
- best-effort CPU/RAM detection when the hosting environment allows it,
- a suggested hardware tuning profile when CPU/RAM can be detected,
- hardware tuning profiles for 2/4/8/16 core setups.
- WooCommerce product lookup table status when WooCommerce is active,
- HPOS status when WooCommerce is active.

Diagnostics do not change the database and do not replace a slow query log, Query Monitor, or hosting-level database monitoring. They are intended to show where to look first.

### About my.cnf / server.cnf

The plugin can read runtime values exposed by the database server through `SHOW VARIABLES`, but it usually cannot reliably find or read the actual configuration file, such as `my.cnf`, `50-server.cnf`, or `mysqld.cnf`, from WordPress/PHP.

Reading or editing those files usually requires SSH/root access or hosting control panel access. For that reason, the dashboard shows possible common paths and current runtime values, but it does not try to open, edit, or write server configuration files.

If the site runs on your own VPS/dedicated server and you can edit the database `.cnf` file, you can use the recommendations as a starting point for manual tuning. If the site is hosted on shared hosting and you cannot edit the `.cnf` file, these recommendations are informational only.

CPU/RAM detection is best-effort from the PHP/web server point of view. If MySQL/MariaDB runs on a separate database server, choose the profile based on the actual database server hardware, not necessarily what WordPress can detect.

## Managed Indexes

The plugin applies indexes with controlled `ALTER TABLE ADD INDEX` statements only when:

- the table exists,
- the index does not already exist,
- the table/index/column identifier passes safety validation.

| Table | Index |
| --- | --- |
| `wp_options` | `autoload` |
| `wp_postmeta` | `meta_key_value (meta_key, meta_value)` |
| `wp_postmeta` | `post_id_meta_key (post_id, meta_key)` |
| `wp_termmeta` | `meta_key_value (meta_key, meta_value)` |
| `wp_termmeta` | `term_id_meta_key (term_id, meta_key)` |
| `wp_commentmeta` | `comment_id_meta_key (comment_id, meta_key)` |
| `wp_commentmeta` | `meta_key_value (meta_key, meta_value)` |
| `wp_usermeta` | `user_id_meta_key (user_id, meta_key)` |
| `wp_usermeta` | `meta_key_value (meta_key, meta_value)` |
| `wp_term_relationships` | `term_taxonomy_id_object_id (term_taxonomy_id, object_id)` |
| `wp_woocommerce_order_items` | `order_id_order_item_type (order_id, order_item_type)` |
| `wp_woocommerce_order_itemmeta` | `order_item_id_meta_key (order_item_id, meta_key)` |
| `wp_woocommerce_order_itemmeta` | `meta_key_value (meta_key, meta_value)` |
| `wp_wc_orders_meta` | `order_id_meta_key (order_id, meta_key)` |
| `wp_wc_orders_meta` | `meta_key_value (meta_key, meta_value)` |

The real table prefix is read from `$wpdb`, so the site does not need to use the default `wp_` prefix.

## Installation

1. Upload the plugin folder to:

   ```text
   wp-content/plugins/wc-speed-indexer
   ```

2. Make sure the main plugin file is:

   ```text
   wc-speed-indexer.php
   ```

3. Activate the plugin from the WordPress admin.
4. Open **DB Indexer** from the admin menu.

## Usage

When the plugin is activated, it does not apply index changes automatically. Open the dashboard, review the diagnostics, take a database backup, then manually confirm the index recheck/apply action.

To manually recheck indexes:

1. Open the WordPress admin.
2. Go to **DB Indexer**.
3. Confirm that you have a recent database backup.
4. Click **Recheck / Apply Indexes**.

## Important Notes

- Take a full database backup before using this on a production site.
- On large WooCommerce stores, creating indexes can take time and temporarily increase database load.
- Test first on a staging environment when possible.
- Index changes require manual confirmation from the dashboard.
- The plugin does not remove indexes on deactivation.
- The plugin does not perform full query profiling. It checks for specific managed indexes and read-only diagnostics.
- Optional WooCommerce/HPOS indexes are applied only when the corresponding tables already exist.
- DB server tuning recommendations are informational unless you control the server configuration.

## Future Improvements

- Split the plugin into smaller classes if more functionality is added.
- Add query profiling based on real slow queries before adding more aggressive indexes.
- Add a WP-CLI command for safer reindexing on large production sites.
- Add a dry-run mode to show what would be added before changing the database.
- Add a generated `.pot` file and optional bundled translations.

## Requirements

- WordPress 5.8 or newer
- WooCommerce
- MySQL or MariaDB
- WordPress administrator permissions
- PHP 7.4 or newer

## Development

The current plugin is intentionally simple and lives in one main PHP file:

```text
wc-speed-indexer.php
```

Syntax check:

```bash
php -l wc-speed-indexer.php
```

## Translation

The plugin uses the `wc-speed-indexer` text domain.

Translation files should live under:

```text
languages/
```

Recommended translation workflow:

```bash
wp i18n make-pot . languages/wc-speed-indexer.pot
```

## Changelog

### 1.6

- Added WooCommerce product lookup table diagnostics.
- Added HPOS status diagnostics when WooCommerce is active.
- Stopped applying index changes automatically on plugin activation.
- Added a backup warning and required confirmation checkbox before manual index changes.
- Added an activation notice explaining the manual review/apply flow.

### 1.5

- Added hosting/shared-server guidance to the DB Server Tuning panel.
- Added best-effort CPU/RAM detection from PHP.
- Added suggested hardware profile when CPU/RAM data is available.
- Added a warning that detected hardware reflects the web/PHP server view and may not match a separate DB server.

### 1.4

- Added MySQL/MariaDB detection.
- Added DB server runtime variable diagnostics.
- Added comparison against common `[mysqld]` recommendations.
- Added hardware tuning profiles for 2/4/8/16 core setups.
- Added a note about the limitations of reading `my.cnf`/`server.cnf` from WordPress/PHP.

### 1.3

- Added read-only diagnostics panel.
- Added autoloaded options size check.
- Added persistent object cache signal.
- Added largest managed tables summary.
- Added health signals for missing indexes and very large tables.

### 1.2

- Added capability checks to manual reindex.
- Added escaping to admin output.
- Added safer SQL identifier handling.
- Moved reindex handler before dashboard rendering.
- Added table existence checks.
- Added admin notices.
- Added WordPress/WooCommerce/PHP compatibility metadata.
- Added additional conservative indexes for core meta, WooCommerce order item, and HPOS order meta tables.

### 1.1

- Added admin dashboard.
- Added manual reindex button.
- Added last optimization timestamp.

## License

No license has been defined yet. If this repository is public on GitHub, add a `LICENSE` file.
