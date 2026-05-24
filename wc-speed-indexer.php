<?php
/**
 * Plugin Name: WC Speed Indexer & Dashboard
 * Description: Database optimization helper with managed indexes, diagnostics, and DB server tuning recommendations for WordPress/WooCommerce sites.
 * Version: 1.5
 * Author: Digitalbox.gr
 * Text Domain: wc-speed-indexer
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * WC requires at least: 5.0
 */

if (!defined('ABSPATH')) {
    exit;
}

define('WCSI_VERSION', '1.5');
define('WCSI_NOTICE_TRANSIENT', 'wcsi_reindex_notice');

register_activation_hook(__FILE__, 'wcsi_apply_optimization');

add_action('admin_menu', 'wcsi_create_menu');
add_action('admin_init', 'wcsi_handle_reindex_action');
add_action('admin_notices', 'wcsi_render_admin_notice');
add_action('plugins_loaded', 'wcsi_load_textdomain');

function wcsi_load_textdomain() {
    load_plugin_textdomain(
        'wc-speed-indexer',
        false,
        dirname(plugin_basename(__FILE__)) . '/languages'
    );
}

/**
 * Returns the index definitions managed by the plugin.
 *
 * Indexes are intentionally added only to existing tables. Optional WooCommerce
 * and HPOS tables are skipped safely when the site does not use them.
 */
function wcsi_get_index_definitions() {
    global $wpdb;

    return [
        $wpdb->options => [
            'description' => __('Autoload Options', 'wc-speed-indexer'),
            'indexes'     => [
                [
                    'name'    => 'autoload',
                    'columns' => [
                        ['name' => 'autoload'],
                    ],
                ],
            ],
        ],
        $wpdb->postmeta => [
            'description' => __('Post/Product Metadata', 'wc-speed-indexer'),
            'indexes'     => [
                [
                    'name'    => 'meta_key_value',
                    'columns' => [
                        ['name' => 'meta_key', 'length' => 191],
                        ['name' => 'meta_value', 'length' => 191],
                    ],
                ],
                [
                    'name'    => 'post_id_meta_key',
                    'columns' => [
                        ['name' => 'post_id'],
                        ['name' => 'meta_key', 'length' => 191],
                    ],
                ],
            ],
        ],
        $wpdb->termmeta => [
            'description' => __('Term/Attribute Metadata', 'wc-speed-indexer'),
            'indexes'     => [
                [
                    'name'    => 'meta_key_value',
                    'columns' => [
                        ['name' => 'meta_key', 'length' => 191],
                        ['name' => 'meta_value', 'length' => 191],
                    ],
                ],
                [
                    'name'    => 'term_id_meta_key',
                    'columns' => [
                        ['name' => 'term_id'],
                        ['name' => 'meta_key', 'length' => 191],
                    ],
                ],
            ],
        ],
        $wpdb->commentmeta => [
            'description' => __('Comment/Review Metadata', 'wc-speed-indexer'),
            'indexes'     => [
                [
                    'name'    => 'comment_id_meta_key',
                    'columns' => [
                        ['name' => 'comment_id'],
                        ['name' => 'meta_key', 'length' => 191],
                    ],
                ],
                [
                    'name'    => 'meta_key_value',
                    'columns' => [
                        ['name' => 'meta_key', 'length' => 191],
                        ['name' => 'meta_value', 'length' => 191],
                    ],
                ],
            ],
        ],
        $wpdb->usermeta => [
            'description' => __('User/Customer Metadata', 'wc-speed-indexer'),
            'indexes'     => [
                [
                    'name'    => 'user_id_meta_key',
                    'columns' => [
                        ['name' => 'user_id'],
                        ['name' => 'meta_key', 'length' => 191],
                    ],
                ],
                [
                    'name'    => 'meta_key_value',
                    'columns' => [
                        ['name' => 'meta_key', 'length' => 191],
                        ['name' => 'meta_value', 'length' => 191],
                    ],
                ],
            ],
        ],
        $wpdb->term_relationships => [
            'description' => __('Category/Attribute Links', 'wc-speed-indexer'),
            'indexes'     => [
                [
                    'name'    => 'term_taxonomy_id_object_id',
                    'columns' => [
                        ['name' => 'term_taxonomy_id'],
                        ['name' => 'object_id'],
                    ],
                ],
            ],
        ],
        $wpdb->prefix . 'woocommerce_order_items' => [
            'description' => __('WooCommerce Order Items', 'wc-speed-indexer'),
            'indexes'     => [
                [
                    'name'    => 'order_id_order_item_type',
                    'columns' => [
                        ['name' => 'order_id'],
                        ['name' => 'order_item_type', 'length' => 191],
                    ],
                ],
            ],
        ],
        $wpdb->prefix . 'woocommerce_order_itemmeta' => [
            'description' => __('WooCommerce Order Item Metadata', 'wc-speed-indexer'),
            'indexes'     => [
                [
                    'name'    => 'order_item_id_meta_key',
                    'columns' => [
                        ['name' => 'order_item_id'],
                        ['name' => 'meta_key', 'length' => 191],
                    ],
                ],
                [
                    'name'    => 'meta_key_value',
                    'columns' => [
                        ['name' => 'meta_key', 'length' => 191],
                        ['name' => 'meta_value', 'length' => 191],
                    ],
                ],
            ],
        ],
        $wpdb->prefix . 'wc_orders_meta' => [
            'description' => __('WooCommerce HPOS Order Metadata', 'wc-speed-indexer'),
            'indexes'     => [
                [
                    'name'    => 'order_id_meta_key',
                    'columns' => [
                        ['name' => 'order_id'],
                        ['name' => 'meta_key', 'length' => 191],
                    ],
                ],
                [
                    'name'    => 'meta_key_value',
                    'columns' => [
                        ['name' => 'meta_key', 'length' => 191],
                        ['name' => 'meta_value', 'length' => 191],
                    ],
                ],
            ],
        ],
    ];
}

function wcsi_create_menu() {
    add_menu_page(
        'DB Indexer',
        'DB Indexer',
        'manage_options',
        'wcsi-dashboard',
        'wcsi_dashboard_page',
        'dashicons-database-export'
    );
}

function wcsi_handle_reindex_action() {
    if (!isset($_POST['wcsi_reindex'])) {
        return;
    }

    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('You do not have permission to run database optimization.', 'wc-speed-indexer'));
    }

    check_admin_referer('wcsi_reindex_action', 'wcsi_reindex_nonce');

    $result = wcsi_apply_optimization();
    set_transient(WCSI_NOTICE_TRANSIENT, $result, MINUTE_IN_SECONDS);

    wp_safe_redirect(
        add_query_arg(
            ['page' => 'wcsi-dashboard'],
            admin_url('admin.php')
        )
    );
    exit;
}

function wcsi_apply_optimization() {
    $summary = [
        'added'    => [],
        'existing' => [],
        'skipped'  => [],
        'failed'   => [],
    ];

    foreach (wcsi_get_index_definitions() as $table => $definition) {
        if (!wcsi_table_exists($table)) {
            $summary['skipped'][] = sprintf(
                /* translators: %s: database table name. */
                __('%s: table does not exist', 'wc-speed-indexer'),
                $table
            );
            continue;
        }

        $existing_indexes = wcsi_get_table_index_names($table);
        if (is_wp_error($existing_indexes)) {
            $summary['failed'][] = $existing_indexes->get_error_message();
            continue;
        }

        foreach ($definition['indexes'] as $index) {
            if (in_array($index['name'], $existing_indexes, true)) {
                $summary['existing'][] = sprintf('%s.%s', $table, $index['name']);
                continue;
            }

            $added = wcsi_add_index($table, $index);
            if (is_wp_error($added)) {
                $summary['failed'][] = $added->get_error_message();
                continue;
            }

            $summary['added'][] = sprintf('%s.%s', $table, $index['name']);
        }
    }

    if (empty($summary['failed'])) {
        update_option('wcsi_last_optimization', current_time('mysql'));
    }

    return $summary;
}

function wcsi_table_exists($table) {
    return null !== wcsi_get_table_status($table);
}

function wcsi_get_table_status($table) {
    global $wpdb;

    return $wpdb->get_row($wpdb->prepare('SHOW TABLE STATUS WHERE Name = %s', $table));
}

function wcsi_get_table_index_names($table) {
    global $wpdb;

    $quoted_table = wcsi_quote_identifier($table);
    if (is_wp_error($quoted_table)) {
        return $quoted_table;
    }

    $indexes = $wpdb->get_results("SHOW INDEX FROM {$quoted_table}");
    if ($wpdb->last_error) {
        return new WP_Error(
            'wcsi_show_index_failed',
            sprintf(
                /* translators: 1: database table name, 2: database error message. */
                __('Could not read indexes for %1$s: %2$s', 'wc-speed-indexer'),
                $table,
                $wpdb->last_error
            )
        );
    }

    return array_unique(wp_list_pluck($indexes, 'Key_name'));
}

function wcsi_get_diagnostics() {
    $table_rows          = [];
    $total_rows          = 0;
    $total_data_length   = 0;
    $total_index_length  = 0;
    $existing_tables     = 0;
    $missing_tables      = 0;
    $missing_indexes     = 0;
    $managed_index_count = 0;

    foreach (wcsi_get_index_definitions() as $table => $definition) {
        $managed = wp_list_pluck($definition['indexes'], 'name');
        $managed_index_count += count($managed);

        $status = wcsi_get_table_status($table);
        if (!$status) {
            $missing_tables++;
            $table_rows[] = [
                'table'        => $table,
                'description'  => $definition['description'],
                'exists'       => false,
                'rows'         => null,
                'data_length'  => null,
                'index_length' => null,
                'missing'      => $managed,
            ];
            $missing_indexes += count($managed);
            continue;
        }

        $existing_tables++;
        $index_names = wcsi_get_table_index_names($table);
        $index_names = is_wp_error($index_names) ? [] : $index_names;
        $missing     = array_values(array_diff($managed, $index_names));

        $rows         = isset($status->Rows) ? (int) $status->Rows : 0;
        $data_length  = isset($status->Data_length) ? (int) $status->Data_length : 0;
        $index_length = isset($status->Index_length) ? (int) $status->Index_length : 0;

        $total_rows         += $rows;
        $total_data_length  += $data_length;
        $total_index_length += $index_length;
        $missing_indexes    += count($missing);

        $table_rows[] = [
            'table'        => $table,
            'description'  => $definition['description'],
            'exists'       => true,
            'rows'         => $rows,
            'data_length'  => $data_length,
            'index_length' => $index_length,
            'missing'      => $missing,
        ];
    }

    return [
        'tables'              => $table_rows,
        'existing_tables'     => $existing_tables,
        'missing_tables'      => $missing_tables,
        'managed_indexes'     => $managed_index_count,
        'missing_indexes'     => $missing_indexes,
        'total_rows'          => $total_rows,
        'total_data_length'   => $total_data_length,
        'total_index_length'  => $total_index_length,
        'autoload'            => wcsi_get_autoload_diagnostics(),
        'db_server'           => wcsi_get_db_server_diagnostics(),
        'persistent_cache'    => wp_using_ext_object_cache(),
        'savequeries_enabled' => defined('SAVEQUERIES') && SAVEQUERIES,
    ];
}

function wcsi_get_db_server_diagnostics() {
    global $wpdb;

    $variable_names = array_values(
        array_unique(
            array_merge(
                [
                    'version',
                    'version_comment',
                    'default_storage_engine',
                    'innodb_file_per_table',
                    'innodb_flush_log_at_trx_commit',
                    'innodb_flush_method',
                    'innodb_log_file_size',
                    'innodb_redo_log_capacity',
                    'max_connections',
                    'wait_timeout',
                    'interactive_timeout',
                    'innodb_buffer_pool_size',
                    'innodb_buffer_pool_instances',
                    'innodb_log_buffer_size',
                    'tmp_table_size',
                    'max_heap_table_size',
                    'innodb_thread_concurrency',
                    'datadir',
                    'basedir',
                    'socket',
                    'pid_file',
                ],
                array_keys(wcsi_get_common_db_recommendations()),
                wcsi_get_profile_variable_names()
            )
        )
    );

    $variables       = wcsi_get_mysql_variables($variable_names);
    $version         = isset($variables['version']) ? $variables['version'] : $wpdb->db_version();
    $version_comment = isset($variables['version_comment']) ? $variables['version_comment'] : '';
    $server_type     = false !== stripos($version . ' ' . $version_comment, 'mariadb') ? 'MariaDB' : 'MySQL';

    $hardware          = wcsi_get_server_hardware_diagnostics();
    $suggested_profile = wcsi_suggest_db_tuning_profile($hardware);

    return [
        'server_type'        => $server_type,
        'version'            => $version,
        'version_comment'    => $version_comment,
        'variables'          => $variables,
        'common'             => wcsi_get_common_db_recommendations(),
        'profiles'           => wcsi_get_db_tuning_profiles(),
        'hardware'           => $hardware,
        'suggested_profile'  => $suggested_profile,
        'hosting_note'       => __('If this site runs on your own VPS/dedicated server and you have access to the database .cnf file, you can use these recommendations as a starting point for manual tuning. If the site is on shared hosting and you cannot edit the database .cnf file, these recommendations are informational only.', 'wc-speed-indexer'),
        'config_file_status' => __('Not exposed to WordPress/PHP by MySQL or MariaDB.', 'wc-speed-indexer'),
        'config_file_note'   => __('Runtime values are visible through SHOW VARIABLES, but the actual my.cnf/server.cnf path and file contents usually require SSH/root access or hosting control panel access.', 'wc-speed-indexer'),
        'possible_files'     => [
            '/etc/my.cnf',
            '/etc/mysql/my.cnf',
            '/etc/mysql/mariadb.conf.d/50-server.cnf',
            '/etc/mysql/mysql.conf.d/mysqld.cnf',
            '/usr/local/etc/my.cnf',
        ],
    ];
}

function wcsi_get_server_hardware_diagnostics() {
    $cpu_cores    = wcsi_detect_cpu_cores();
    $memory_bytes = wcsi_detect_total_memory_bytes();
    $sources      = [];
    $notes        = [];

    if (null !== $cpu_cores) {
        $sources[] = 'CPU: /proc/cpuinfo or nproc';
    } else {
        $notes[] = __('CPU cores could not be detected from PHP.', 'wc-speed-indexer');
    }

    if (null !== $memory_bytes) {
        $sources[] = 'RAM: /proc/meminfo';
    } else {
        $notes[] = __('Total server RAM could not be detected from PHP.', 'wc-speed-indexer');
    }

    $notes[] = __('Detected hardware is the web/PHP server view. If MySQL/MariaDB runs on a separate database server, choose the profile based on the database server hardware instead.', 'wc-speed-indexer');

    return [
        'cpu_cores'    => $cpu_cores,
        'memory_bytes' => $memory_bytes,
        'sources'      => $sources,
        'notes'        => $notes,
    ];
}

function wcsi_detect_cpu_cores() {
    $cpuinfo = '/proc/cpuinfo';
    if (is_readable($cpuinfo)) {
        $contents = file_get_contents($cpuinfo);
        if (false !== $contents && preg_match_all('/^processor\s*:/m', $contents, $matches)) {
            $count = count($matches[0]);
            if ($count > 0) {
                return $count;
            }
        }
    }

    if (wcsi_can_call_function('shell_exec')) {
        $output = shell_exec('nproc 2>/dev/null');
        $count  = is_string($output) ? absint(trim($output)) : 0;
        if ($count > 0) {
            return $count;
        }
    }

    return null;
}

function wcsi_detect_total_memory_bytes() {
    $meminfo = '/proc/meminfo';
    if (!is_readable($meminfo)) {
        return null;
    }

    $contents = file_get_contents($meminfo);
    if (false === $contents || !preg_match('/^MemTotal:\s+([0-9]+)\s+kB/im', $contents, $matches)) {
        return null;
    }

    return (int) $matches[1] * 1024;
}

function wcsi_can_call_function($function_name) {
    if (!function_exists($function_name)) {
        return false;
    }

    $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));

    return !in_array($function_name, $disabled, true);
}

function wcsi_suggest_db_tuning_profile(array $hardware) {
    $cores = isset($hardware['cpu_cores']) ? (int) $hardware['cpu_cores'] : 0;
    $ram   = isset($hardware['memory_bytes']) ? (int) $hardware['memory_bytes'] : 0;

    if ($cores <= 0 || $ram <= 0) {
        return null;
    }

    $gb = 1073741824;

    if ($cores >= 16 && $ram >= 32 * $gb) {
        return '16 cores / 32GB RAM';
    }

    if ($cores >= 8 && $ram >= 16 * $gb) {
        return '8 cores / 16GB RAM';
    }

    if ($cores >= 4 && $ram >= 8 * $gb) {
        return '4 cores / 8GB RAM';
    }

    if ($cores >= 2 && $ram >= 4 * $gb) {
        return '2 cores / 4GB RAM';
    }

    return null;
}

function wcsi_get_mysql_variables(array $names) {
    global $wpdb;

    if (empty($names)) {
        return [];
    }

    $placeholders = implode(', ', array_fill(0, count($names), '%s'));
    $rows         = $wpdb->get_results($wpdb->prepare("SHOW VARIABLES WHERE Variable_name IN ({$placeholders})", $names));
    $variables    = [];

    foreach ($rows as $row) {
        $variables[$row->Variable_name] = $row->Value;
    }

    return $variables;
}

function wcsi_get_common_db_recommendations() {
    return [
        'default_storage_engine'         => 'InnoDB',
        'innodb_file_per_table'          => '1',
        'innodb_flush_log_at_trx_commit' => '2',
        'innodb_flush_method'            => 'O_DIRECT',
        'innodb_log_file_size'           => '256M',
        'max_connections'                => '150',
        'wait_timeout'                   => '600',
        'interactive_timeout'            => '600',
    ];
}

function wcsi_get_db_tuning_profiles() {
    return [
        '2 cores / 4GB RAM' => [
            'innodb_buffer_pool_size'      => '2.5G',
            'innodb_buffer_pool_instances' => '1',
            'innodb_log_buffer_size'       => '16M',
            'tmp_table_size'               => '32M',
            'max_heap_table_size'          => '32M',
        ],
        '4 cores / 8GB RAM' => [
            'innodb_buffer_pool_size'      => '5.5G',
            'innodb_buffer_pool_instances' => '5',
            'innodb_log_buffer_size'       => '32M',
            'tmp_table_size'               => '64M',
            'max_heap_table_size'          => '64M',
        ],
        '8 cores / 16GB RAM' => [
            'innodb_buffer_pool_size'      => '12G',
            'innodb_buffer_pool_instances' => '8',
            'innodb_log_buffer_size'       => '64M',
            'tmp_table_size'               => '128M',
            'max_heap_table_size'          => '128M',
            'innodb_thread_concurrency'    => '8',
        ],
        '16 cores / 32GB RAM' => [
            'innodb_buffer_pool_size'      => '24G',
            'innodb_buffer_pool_instances' => '16',
            'innodb_log_buffer_size'       => '128M',
            'tmp_table_size'               => '256M',
            'max_heap_table_size'          => '256M',
            'innodb_thread_concurrency'    => '16',
        ],
    ];
}

function wcsi_get_profile_variable_names() {
    $names = [];

    foreach (wcsi_get_db_tuning_profiles() as $profile) {
        $names = array_merge($names, array_keys($profile));
    }

    return array_values(array_unique($names));
}

function wcsi_compare_db_value($current, $recommended) {
    if (null === $current || '' === $current) {
        return 'missing';
    }

    $current_size     = wcsi_mysql_size_to_bytes($current);
    $recommended_size = wcsi_mysql_size_to_bytes($recommended);

    if (null !== $current_size && null !== $recommended_size) {
        return $current_size === $recommended_size ? 'match' : 'diff';
    }

    $current_normalized     = wcsi_normalize_db_value($current);
    $recommended_normalized = wcsi_normalize_db_value($recommended);

    return $current_normalized === $recommended_normalized ? 'match' : 'diff';
}

function wcsi_mysql_size_to_bytes($value) {
    if (!is_scalar($value)) {
        return null;
    }

    $value = trim((string) $value);
    if ('' === $value) {
        return null;
    }

    if (ctype_digit($value)) {
        return (int) $value;
    }

    if (!preg_match('/^([0-9]+(?:\.[0-9]+)?)([KMGTP])$/i', $value, $matches)) {
        return null;
    }

    $number = (float) $matches[1];
    $unit   = strtoupper($matches[2]);
    $scale  = [
        'K' => 1024,
        'M' => 1048576,
        'G' => 1073741824,
        'T' => 1099511627776,
        'P' => 1125899906842624,
    ];

    return (int) round($number * $scale[$unit]);
}

function wcsi_normalize_db_value($value) {
    $value = strtolower(trim((string) $value));

    if ('on' === $value || 'yes' === $value || 'true' === $value) {
        return '1';
    }

    if ('off' === $value || 'no' === $value || 'false' === $value) {
        return '0';
    }

    return $value;
}

function wcsi_format_db_value($value) {
    $bytes = wcsi_mysql_size_to_bytes($value);
    if (null !== $bytes && $bytes >= 1024) {
        return size_format($bytes);
    }

    return (string) $value;
}

function wcsi_get_autoload_diagnostics() {
    global $wpdb;

    $autoload_values = ['yes', 'on', 'auto', 'auto-on'];
    $placeholders    = implode(', ', array_fill(0, count($autoload_values), '%s'));
    $sql             = "
        SELECT COUNT(*) AS option_count, COALESCE(SUM(LENGTH(option_value)), 0) AS total_bytes
        FROM {$wpdb->options}
        WHERE autoload IN ({$placeholders})
    ";
    $result          = $wpdb->get_row($wpdb->prepare($sql, $autoload_values));

    if (!$result) {
        return [
            'option_count' => 0,
            'total_bytes'  => 0,
            'level'        => 'unknown',
            'message'      => __('Could not read autoloaded options.', 'wc-speed-indexer'),
        ];
    }

    $total_bytes = (int) $result->total_bytes;

    if ($total_bytes >= 5242880) {
        $level   = 'critical';
        $message = __('Very high autoload size. This can slow every uncached request.', 'wc-speed-indexer');
    } elseif ($total_bytes >= 1048576) {
        $level   = 'warning';
        $message = __('Autoload size is worth reviewing.', 'wc-speed-indexer');
    } else {
        $level   = 'good';
        $message = __('Autoload size looks reasonable.', 'wc-speed-indexer');
    }

    return [
        'option_count' => (int) $result->option_count,
        'total_bytes'  => $total_bytes,
        'level'        => $level,
        'message'      => $message,
    ];
}

function wcsi_add_index($table, array $index) {
    global $wpdb;

    $quoted_table = wcsi_quote_identifier($table);
    $quoted_index = wcsi_quote_identifier($index['name']);

    if (is_wp_error($quoted_table)) {
        return $quoted_table;
    }

    if (is_wp_error($quoted_index)) {
        return $quoted_index;
    }

    $columns = [];
    foreach ($index['columns'] as $column) {
        $quoted_column = wcsi_quote_identifier($column['name']);
        if (is_wp_error($quoted_column)) {
            return $quoted_column;
        }

        $length = isset($column['length']) ? absint($column['length']) : 0;
        $columns[] = $length > 0 ? sprintf('%s(%d)', $quoted_column, $length) : $quoted_column;
    }

    $sql    = sprintf('ALTER TABLE %s ADD INDEX %s (%s)', $quoted_table, $quoted_index, implode(', ', $columns));
    $result = $wpdb->query($sql);

    if (false === $result) {
        return new WP_Error(
            'wcsi_add_index_failed',
            sprintf(
                /* translators: 1: index name, 2: database table name, 3: database error message. */
                __('Could not add index %1$s on %2$s: %3$s', 'wc-speed-indexer'),
                $index['name'],
                $table,
                $wpdb->last_error
            )
        );
    }

    return true;
}

function wcsi_quote_identifier($identifier) {
    if (!is_string($identifier) || !preg_match('/^[A-Za-z0-9_]+$/', $identifier)) {
        return new WP_Error(
            'wcsi_invalid_identifier',
            sprintf(
                /* translators: %s: rejected database identifier. */
                __('Unsafe database identifier rejected: %s', 'wc-speed-indexer'),
                is_scalar($identifier) ? (string) $identifier : __('non-scalar', 'wc-speed-indexer')
            )
        );
    }

    return '`' . $identifier . '`';
}

function wcsi_render_admin_notice() {
    if (!isset($_GET['page']) || 'wcsi-dashboard' !== sanitize_key(wp_unslash($_GET['page']))) {
        return;
    }

    $notice = get_transient(WCSI_NOTICE_TRANSIENT);
    if (!$notice || !is_array($notice)) {
        return;
    }

    delete_transient(WCSI_NOTICE_TRANSIENT);

    $failed_count = count($notice['failed']);
    $added_count  = count($notice['added']);
    $class        = $failed_count > 0 ? 'notice notice-error is-dismissible' : 'notice notice-success is-dismissible';

    if ($failed_count > 0) {
        $message = sprintf(
            /* translators: 1: error count, 2: added index count. */
            __('Database index check completed with %1$d error(s). Added %2$d new index(es).', 'wc-speed-indexer'),
            $failed_count,
            $added_count
        );
    } else {
        $message = sprintf(
            /* translators: %d: added index count. */
            __('Database indexes checked successfully. Added %1$d new index(es).', 'wc-speed-indexer'),
            $added_count
        );
    }

    echo '<div class="' . esc_attr($class) . '"><p>' . esc_html($message) . '</p>';

    if ($failed_count > 0) {
        echo '<ul>';
        foreach ($notice['failed'] as $error) {
            echo '<li>' . esc_html($error) . '</li>';
        }
        echo '</ul>';
    }

    echo '</div>';
}

function wcsi_render_diagnostics_panel(array $diagnostics) {
    $autoload = $diagnostics['autoload'];
    ?>
    <h2><?php echo esc_html__('Diagnostics', 'wc-speed-indexer'); ?></h2>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;margin:16px 0;">
        <div class="postbox" style="padding:12px;">
            <h3 style="margin-top:0;"><?php echo esc_html__('Managed Tables', 'wc-speed-indexer'); ?></h3>
            <p style="font-size:24px;margin:0;"><?php echo esc_html(number_format_i18n((int) $diagnostics['existing_tables'])); ?></p>
            <p style="margin-bottom:0;color:#646970;">
                <?php
                printf(
                    esc_html__('%1$s optional/missing table(s)', 'wc-speed-indexer'),
                    esc_html(number_format_i18n((int) $diagnostics['missing_tables']))
                );
                ?>
            </p>
        </div>

        <div class="postbox" style="padding:12px;">
            <h3 style="margin-top:0;"><?php echo esc_html__('Managed Indexes', 'wc-speed-indexer'); ?></h3>
            <p style="font-size:24px;margin:0;"><?php echo esc_html(number_format_i18n((int) $diagnostics['managed_indexes'])); ?></p>
            <p style="margin-bottom:0;color:<?php echo $diagnostics['missing_indexes'] > 0 ? '#b32d2e' : '#008a20'; ?>;">
                <?php
                printf(
                    esc_html__('%1$s missing index(es)', 'wc-speed-indexer'),
                    esc_html(number_format_i18n((int) $diagnostics['missing_indexes']))
                );
                ?>
            </p>
        </div>

        <div class="postbox" style="padding:12px;">
            <h3 style="margin-top:0;"><?php echo esc_html__('Autoloaded Options', 'wc-speed-indexer'); ?></h3>
            <p style="font-size:24px;margin:0;"><?php echo esc_html(size_format((int) $autoload['total_bytes'])); ?></p>
            <p style="margin-bottom:0;color:<?php echo esc_attr(wcsi_get_level_color($autoload['level'])); ?>;">
                <?php echo esc_html($autoload['message']); ?>
            </p>
            <p style="margin-bottom:0;color:#646970;">
                <?php
                printf(
                    esc_html__('%1$s autoloaded option(s)', 'wc-speed-indexer'),
                    esc_html(number_format_i18n((int) $autoload['option_count']))
                );
                ?>
            </p>
        </div>

        <div class="postbox" style="padding:12px;">
            <h3 style="margin-top:0;"><?php echo esc_html__('Object Cache', 'wc-speed-indexer'); ?></h3>
            <p style="font-size:24px;margin:0;">
                <?php echo $diagnostics['persistent_cache'] ? esc_html__('Active', 'wc-speed-indexer') : esc_html__('Not detected', 'wc-speed-indexer'); ?>
            </p>
            <p style="margin-bottom:0;color:<?php echo $diagnostics['persistent_cache'] ? '#008a20' : '#b32d2e'; ?>;">
                <?php
                echo $diagnostics['persistent_cache']
                    ? esc_html__('Persistent object cache is enabled.', 'wc-speed-indexer')
                    : esc_html__('Persistent object cache can help busy WooCommerce sites.', 'wc-speed-indexer');
                ?>
            </p>
        </div>
    </div>

    <table class="wp-list-table widefat fixed striped" style="margin-bottom:20px;">
        <thead>
            <tr>
                <th><?php echo esc_html__('Largest Managed Tables', 'wc-speed-indexer'); ?></th>
                <th><?php echo esc_html__('Rows', 'wc-speed-indexer'); ?></th>
                <th><?php echo esc_html__('Data', 'wc-speed-indexer'); ?></th>
                <th><?php echo esc_html__('Indexes', 'wc-speed-indexer'); ?></th>
                <th><?php echo esc_html__('Signal', 'wc-speed-indexer'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php
            $tables = array_filter(
                $diagnostics['tables'],
                static function ($table) {
                    return !empty($table['exists']);
                }
            );
            usort(
                $tables,
                static function ($a, $b) {
                    return (int) $b['data_length'] <=> (int) $a['data_length'];
                }
            );
            $tables = array_slice($tables, 0, 5);
            ?>
            <?php foreach ($tables as $table) : ?>
                <?php $signal = wcsi_get_table_signal($table); ?>
                <tr>
                    <td>
                        <strong><?php echo esc_html($table['table']); ?></strong><br>
                        <small><?php echo esc_html($table['description']); ?></small>
                    </td>
                    <td><?php echo esc_html(number_format_i18n((int) $table['rows'])); ?></td>
                    <td><?php echo esc_html(size_format((int) $table['data_length'])); ?></td>
                    <td><?php echo esc_html(size_format((int) $table['index_length'])); ?></td>
                    <td style="color:<?php echo esc_attr(wcsi_get_level_color($signal['level'])); ?>;">
                        <?php echo esc_html($signal['message']); ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <?php if ($diagnostics['savequeries_enabled']) : ?>
        <p style="color:#646970;">
            <?php echo esc_html__('SAVEQUERIES is enabled. For live slow query analysis, use Query Monitor or your hosting database slow query log.', 'wc-speed-indexer'); ?>
        </p>
    <?php endif; ?>

    <?php wcsi_render_db_server_tuning_panel($diagnostics['db_server']); ?>
    <?php
}

function wcsi_render_db_server_tuning_panel(array $db_server) {
    $variables         = $db_server['variables'];
    $hardware          = $db_server['hardware'];
    $suggested_profile = $db_server['suggested_profile'];
    ?>
    <h2><?php echo esc_html__('DB Server Tuning', 'wc-speed-indexer'); ?></h2>

    <div class="notice notice-info inline" style="margin:12px 0;">
        <p><?php echo esc_html($db_server['hosting_note']); ?></p>
    </div>

    <table class="widefat striped" style="margin-bottom:16px;">
        <tbody>
            <tr>
                <th scope="row"><?php echo esc_html__('Server', 'wc-speed-indexer'); ?></th>
                <td>
                    <?php echo esc_html($db_server['server_type']); ?>
                    <?php if (!empty($db_server['version'])) : ?>
                        <?php echo esc_html(' ' . $db_server['version']); ?>
                    <?php endif; ?>
                    <?php if (!empty($db_server['version_comment'])) : ?>
                        <br><small><?php echo esc_html($db_server['version_comment']); ?></small>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php echo esc_html__('Config file', 'wc-speed-indexer'); ?></th>
                <td>
                    <strong><?php echo esc_html($db_server['config_file_status']); ?></strong><br>
                    <span style="color:#646970;"><?php echo esc_html($db_server['config_file_note']); ?></span><br>
                    <small>
                        <?php
                        printf(
                            esc_html__('Common possible paths: %s', 'wc-speed-indexer'),
                            esc_html(implode(', ', $db_server['possible_files']))
                        );
                        ?>
                    </small>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php echo esc_html__('Detected hardware', 'wc-speed-indexer'); ?></th>
                <td>
                    <?php if (null !== $hardware['cpu_cores'] || null !== $hardware['memory_bytes']) : ?>
                        <?php if (null !== $hardware['cpu_cores']) : ?>
                            <strong><?php echo esc_html(number_format_i18n((int) $hardware['cpu_cores'])); ?></strong>
                            <?php echo esc_html__('CPU core(s)', 'wc-speed-indexer'); ?>
                        <?php endif; ?>

                        <?php if (null !== $hardware['cpu_cores'] && null !== $hardware['memory_bytes']) : ?>
                            <?php echo esc_html__(' / ', 'wc-speed-indexer'); ?>
                        <?php endif; ?>

                        <?php if (null !== $hardware['memory_bytes']) : ?>
                            <strong><?php echo esc_html(size_format((int) $hardware['memory_bytes'])); ?></strong>
                            <?php echo esc_html__('RAM', 'wc-speed-indexer'); ?>
                        <?php endif; ?>

                        <?php if (!empty($hardware['sources'])) : ?>
                            <br><small><?php echo esc_html(implode(', ', $hardware['sources'])); ?></small>
                        <?php endif; ?>
                    <?php else : ?>
                        <?php echo esc_html__('Could not detect CPU/RAM from PHP.', 'wc-speed-indexer'); ?>
                    <?php endif; ?>

                    <?php foreach ($hardware['notes'] as $note) : ?>
                        <br><small style="color:#646970;"><?php echo esc_html($note); ?></small>
                    <?php endforeach; ?>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php echo esc_html__('Suggested hardware profile', 'wc-speed-indexer'); ?></th>
                <td>
                    <?php if ($suggested_profile) : ?>
                        <strong><?php echo esc_html($suggested_profile); ?></strong>
                    <?php else : ?>
                        <?php echo esc_html__('Not enough hardware data. Pick the profile that matches the actual database server.', 'wc-speed-indexer'); ?>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php echo esc_html__('Runtime paths', 'wc-speed-indexer'); ?></th>
                <td>
                    <?php
                    $runtime_paths = [];
                    foreach (['datadir', 'basedir', 'socket', 'pid_file'] as $path_key) {
                        if (!empty($variables[$path_key])) {
                            $runtime_paths[] = $path_key . ': ' . $variables[$path_key];
                        }
                    }
                    echo esc_html(!empty($runtime_paths) ? implode(' | ', $runtime_paths) : __('Not available.', 'wc-speed-indexer'));
                    ?>
                </td>
            </tr>
        </tbody>
    </table>

    <h3><?php echo esc_html__('Common [mysqld] Recommendations', 'wc-speed-indexer'); ?></h3>
    <table class="wp-list-table widefat fixed striped" style="margin-bottom:20px;">
        <thead>
            <tr>
                <th><?php echo esc_html__('Variable', 'wc-speed-indexer'); ?></th>
                <th><?php echo esc_html__('Current', 'wc-speed-indexer'); ?></th>
                <th><?php echo esc_html__('Recommended', 'wc-speed-indexer'); ?></th>
                <th><?php echo esc_html__('Status', 'wc-speed-indexer'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($db_server['common'] as $name => $recommended) : ?>
                <?php
                $current = isset($variables[$name]) ? $variables[$name] : null;
                $status  = wcsi_compare_db_value($current, $recommended);
                ?>
                <tr>
                    <td><code><?php echo esc_html($name); ?></code></td>
                    <td><?php echo null === $current ? '&mdash;' : esc_html(wcsi_format_db_value($current)); ?></td>
                    <td><code><?php echo esc_html($recommended); ?></code></td>
                    <td style="color:<?php echo esc_attr('match' === $status ? '#008a20' : '#b32d2e'); ?>;">
                        <?php echo esc_html(wcsi_get_db_status_label($status)); ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <h3><?php echo esc_html__('Hardware Profiles', 'wc-speed-indexer'); ?></h3>
    <p style="color:#646970;">
        <?php echo esc_html__('WordPress cannot reliably detect total server RAM/CPU on most hosting setups. Pick the profile that matches the actual database server hardware.', 'wc-speed-indexer'); ?>
    </p>

    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th><?php echo esc_html__('Variable', 'wc-speed-indexer'); ?></th>
                <th><?php echo esc_html__('Current', 'wc-speed-indexer'); ?></th>
                <?php foreach (array_keys($db_server['profiles']) as $profile_name) : ?>
                    <th style="<?php echo $profile_name === $suggested_profile ? 'background:#f0f6fc;' : ''; ?>">
                        <?php echo esc_html($profile_name); ?>
                        <?php if ($profile_name === $suggested_profile) : ?>
                            <br><small><?php echo esc_html__('Suggested', 'wc-speed-indexer'); ?></small>
                        <?php endif; ?>
                    </th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach (wcsi_get_profile_variable_names() as $variable_name) : ?>
                <tr>
                    <td><code><?php echo esc_html($variable_name); ?></code></td>
                    <td>
                        <?php
                        echo isset($variables[$variable_name])
                            ? esc_html(wcsi_format_db_value($variables[$variable_name]))
                            : '&mdash;';
                        ?>
                    </td>
                    <?php foreach ($db_server['profiles'] as $profile_name => $profile) : ?>
                        <td style="<?php echo $profile_name === $suggested_profile ? 'background:#f0f6fc;' : ''; ?>">
                            <?php if (isset($profile[$variable_name])) : ?>
                                <code><?php echo esc_html($profile[$variable_name]); ?></code>
                            <?php else : ?>
                                &mdash;
                            <?php endif; ?>
                        </td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php
}

function wcsi_get_db_status_label($status) {
    if ('match' === $status) {
        return __('OK', 'wc-speed-indexer');
    }

    if ('missing' === $status) {
        return __('Not available', 'wc-speed-indexer');
    }

    return __('Review', 'wc-speed-indexer');
}

function wcsi_get_level_color($level) {
    if ('critical' === $level || 'warning' === $level) {
        return '#b32d2e';
    }

    if ('good' === $level) {
        return '#008a20';
    }

    return '#646970';
}

function wcsi_get_table_signal(array $table) {
    if (!empty($table['missing'])) {
        return [
            'level'   => 'warning',
            'message' => sprintf(
                /* translators: %d: missing index count. */
                _n('%d missing managed index.', '%d missing managed indexes.', count($table['missing']), 'wc-speed-indexer'),
                count($table['missing'])
            ),
        ];
    }

    if ((int) $table['rows'] >= 1000000 || (int) $table['data_length'] >= 1073741824) {
        return [
            'level'   => 'warning',
            'message' => __('Large table. Review slow queries before adding more indexes.', 'wc-speed-indexer'),
        ];
    }

    return [
        'level'   => 'good',
        'message' => __('No obvious index issue in managed checks.', 'wc-speed-indexer'),
    ];
}

function wcsi_dashboard_page() {
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('You do not have permission to view this page.', 'wc-speed-indexer'));
    }

    $last_optimization = get_option('wcsi_last_optimization');
    $diagnostics       = wcsi_get_diagnostics();
    ?>
    <div class="wrap">
        <h1><?php echo esc_html__('WC Database Indexer Dashboard', 'wc-speed-indexer'); ?></h1>
        <p><?php echo esc_html__('Index optimization and diagnostics for large WordPress and WooCommerce sites.', 'wc-speed-indexer'); ?></p>

        <?php if ($last_optimization) : ?>
            <p>
                <strong><?php echo esc_html__('Last successful check:', 'wc-speed-indexer'); ?></strong>
                <?php echo esc_html($last_optimization); ?>
            </p>
        <?php endif; ?>

        <?php wcsi_render_diagnostics_panel($diagnostics); ?>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php echo esc_html__('Table', 'wc-speed-indexer'); ?></th>
                    <th><?php echo esc_html__('Rows', 'wc-speed-indexer'); ?></th>
                    <th><?php echo esc_html__('Index Size', 'wc-speed-indexer'); ?></th>
                    <th><?php echo esc_html__('Managed Indexes', 'wc-speed-indexer'); ?></th>
                    <th><?php echo esc_html__('Status', 'wc-speed-indexer'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach (wcsi_get_index_definitions() as $table => $definition) : ?>
                    <?php
                    $table_exists = wcsi_table_exists($table);
                    $status       = $table_exists ? wcsi_get_table_status($table) : null;
                    $index_names  = $table_exists ? wcsi_get_table_index_names($table) : [];
                    $index_names  = is_wp_error($index_names) ? [] : $index_names;
                    $managed      = wp_list_pluck($definition['indexes'], 'name');
                    $missing      = array_values(array_diff($managed, $index_names));
                    ?>
                    <tr>
                        <td>
                            <strong><?php echo esc_html($table); ?></strong><br>
                            <small><?php echo esc_html($definition['description']); ?></small>
                        </td>
                        <td>
                            <?php echo $status ? esc_html(number_format_i18n((int) $status->Rows)) : '&mdash;'; ?>
                        </td>
                        <td>
                            <?php echo $status ? esc_html(size_format((int) $status->Index_length)) : '&mdash;'; ?>
                        </td>
                        <td>
                            <?php echo esc_html(implode(', ', $managed)); ?>
                        </td>
                        <td>
                            <?php if (!$table_exists) : ?>
                                <span style="color:#646970"><?php echo esc_html__('Skipped - table missing', 'wc-speed-indexer'); ?></span>
                            <?php elseif (!empty($missing)) : ?>
                                <span style="color:#b32d2e"><?php echo esc_html__('Missing:', 'wc-speed-indexer'); ?></span>
                                <?php echo esc_html(implode(', ', $missing)); ?>
                            <?php else : ?>
                                <span style="color:#008a20"><?php echo esc_html__('Optimized', 'wc-speed-indexer'); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div style="margin-top:20px;">
            <form method="post" action="<?php echo esc_url(admin_url('admin.php?page=wcsi-dashboard')); ?>">
                <?php wp_nonce_field('wcsi_reindex_action', 'wcsi_reindex_nonce'); ?>
                <input type="submit" name="wcsi_reindex" class="button button-primary" value="<?php echo esc_attr__('Recheck / Apply Indexes', 'wc-speed-indexer'); ?>">
            </form>
        </div>
    </div>
    <?php
}
