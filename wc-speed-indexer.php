<?php
/**
 * Plugin Name: WC Speed Indexer & Dashboard
 * Description: Βελτιστοποίηση βάσης δεδομένων με custom indexes και Dashboard παρακολούθησης.
 * Version: 1.3
 * Author: Digitalbox.gr
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * WC requires at least: 5.0
 */

if (!defined('ABSPATH')) {
    exit;
}

define('WCSI_VERSION', '1.3');
define('WCSI_NOTICE_TRANSIENT', 'wcsi_reindex_notice');

register_activation_hook(__FILE__, 'wcsi_apply_optimization');

add_action('admin_menu', 'wcsi_create_menu');
add_action('admin_init', 'wcsi_handle_reindex_action');
add_action('admin_notices', 'wcsi_render_admin_notice');

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
            'description' => 'Autoload Options',
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
            'description' => 'Post/Product Metadata',
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
            'description' => 'Term/Attribute Metadata',
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
            'description' => 'Comment/Review Metadata',
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
            'description' => 'User/Customer Metadata',
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
            'description' => 'Category/Attribute Links',
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
            'description' => 'WooCommerce Order Items',
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
            'description' => 'WooCommerce Order Item Metadata',
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
            'description' => 'WooCommerce HPOS Order Metadata',
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
            $summary['skipped'][] = sprintf('%s: table does not exist', $table);
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
            sprintf('Could not read indexes for %s: %s', $table, $wpdb->last_error)
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
        'persistent_cache'    => wp_using_ext_object_cache(),
        'savequeries_enabled' => defined('SAVEQUERIES') && SAVEQUERIES,
    ];
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
            sprintf('Could not add index %s on %s: %s', $index['name'], $table, $wpdb->last_error)
        );
    }

    return true;
}

function wcsi_quote_identifier($identifier) {
    if (!is_string($identifier) || !preg_match('/^[A-Za-z0-9_]+$/', $identifier)) {
        return new WP_Error(
            'wcsi_invalid_identifier',
            sprintf('Unsafe database identifier rejected: %s', is_scalar($identifier) ? (string) $identifier : 'non-scalar')
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
            'Database index check completed with %1$d error(s). Added %2$d new index(es).',
            $failed_count,
            $added_count
        );
    } else {
        $message = sprintf(
            'Database indexes checked successfully. Added %1$d new index(es).',
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
    <?php
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
        <p><?php echo esc_html__('Βελτιστοποίηση indexes για μεγάλα WordPress και WooCommerce sites.', 'wc-speed-indexer'); ?></p>

        <?php if ($last_optimization) : ?>
            <p>
                <strong><?php echo esc_html__('Τελευταίος επιτυχής έλεγχος:', 'wc-speed-indexer'); ?></strong>
                <?php echo esc_html($last_optimization); ?>
            </p>
        <?php endif; ?>

        <?php wcsi_render_diagnostics_panel($diagnostics); ?>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php echo esc_html__('Πίνακας', 'wc-speed-indexer'); ?></th>
                    <th><?php echo esc_html__('Εγγραφές (Rows)', 'wc-speed-indexer'); ?></th>
                    <th><?php echo esc_html__('Μέγεθος Index', 'wc-speed-indexer'); ?></th>
                    <th><?php echo esc_html__('Managed Indexes', 'wc-speed-indexer'); ?></th>
                    <th><?php echo esc_html__('Κατάσταση', 'wc-speed-indexer'); ?></th>
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
                <input type="submit" name="wcsi_reindex" class="button button-primary" value="<?php echo esc_attr__('Επαναφορά / Έλεγχος Indexes', 'wc-speed-indexer'); ?>">
            </form>
        </div>
    </div>
    <?php
}
