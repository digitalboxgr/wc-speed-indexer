<?php
/**
 * Plugin Name: WC Speed Indexer & Dashboard
 * Description: Βελτιστοποίηση βάσης δεδομένων με custom indexes και Dashboard παρακολούθησης.
 * Version: 1.1
 * Author: Digitalbox.gr
 */

if (!defined('ABSPATH')) exit;

// 1. Δημιουργία των Indexes κατά την ενεργοποίηση
register_activation_hook(__FILE__, 'wcsi_apply_optimization');

function wcsi_apply_optimization() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "
    CREATE TABLE {$wpdb->options} (
        option_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        option_name varchar(191) NOT NULL DEFAULT '',
        option_value longtext NOT NULL,
        autoload varchar(20) NOT NULL DEFAULT 'yes',
        PRIMARY KEY  (option_id),
        UNIQUE KEY option_name (option_name),
        KEY autoload (autoload)
    ) $charset_collate;

    CREATE TABLE {$wpdb->postmeta} (
        meta_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        post_id bigint(20) unsigned NOT NULL DEFAULT '0',
        meta_key varchar(255) DEFAULT NULL,
        meta_value longtext,
        PRIMARY KEY  (meta_id),
        KEY post_id (post_id),
        KEY meta_key (meta_key(191)),
        KEY meta_key_value (meta_key(191), meta_value(191))
    ) $charset_collate;

    CREATE TABLE {$wpdb->termmeta} (
        meta_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        term_id bigint(20) unsigned NOT NULL DEFAULT '0',
        meta_key varchar(255) DEFAULT NULL,
        meta_value longtext,
        PRIMARY KEY  (meta_id),
        KEY term_id (term_id),
        KEY meta_key (meta_key(191)),
        KEY meta_key_value (meta_key(191), meta_value(191))
    ) $charset_collate;

    CREATE TABLE {$wpdb->term_relationships} (
        object_id bigint(20) unsigned NOT NULL DEFAULT '0',
        term_taxonomy_id bigint(20) unsigned NOT NULL DEFAULT '0',
        term_order int(11) NOT NULL DEFAULT '0',
        PRIMARY KEY  (object_id, term_taxonomy_id),
        KEY term_taxonomy_id (term_taxonomy_id),
        KEY term_taxonomy_id_object_id (term_taxonomy_id, object_id)
    ) $charset_collate;
    ";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
    
    update_option('wcsi_last_optimization', current_time('mysql'));
}

// 2. Δημιουργία του Admin Menu
add_action('admin_menu', 'wcsi_create_menu');

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

// 3. Το Dashboard Page
function wcsi_dashboard_page() {
    global $wpdb;
    $tables = [
        $wpdb->options => 'Autoload Options',
        $wpdb->postmeta => 'Product Metadata',
        $wpdb->termmeta => 'Attribute Metadata',
        $wpdb->term_relationships => 'Category/Attribute Links'
    ];
    ?>
    <div class="wrap">
        <h1>WC Database Indexer Dashboard</h1>
        <p>Βελτιστοποίηση των πινάκων για μεγάλα WooCommerce καταστήματα.</p>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Πίνακας</th>
                    <th>Εγγραφές (Rows)</th>
                    <th>Μέγεθος Index</th>
                    <th>Κατάσταση</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tables as $table => $desc) : 
                    $status = $wpdb->get_row("SHOW TABLE STATUS LIKE '$table'");
                    $indices = $wpdb->get_results("SHOW INDEX FROM $table");
                    $index_names = wp_list_pluck($indices, 'Key_name');
                    $has_custom = in_array('meta_key_value', $index_names) || in_array('autoload', $index_names) || in_array('term_taxonomy_id_object_id', $index_names);
                ?>
                <tr>
                    <td><strong><?php echo $table; ?></strong><br><small><?php echo $desc; ?></small></td>
                    <td><?php echo number_format($status->Rows); ?></td>
                    <td><?php echo size_format($status->Index_length); ?></td>
                    <td>
                        <?php if ($has_custom) : ?>
                            <span style="color:green">✅ Optimized</span>
                        <?php else : ?>
                            <span style="color:red">⚠️ Missing Indexes</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div style="margin-top:20px;">
            <form method="post" action="">
                <?php wp_nonce_field('wcsi_reindex'); ?>
                <input type="submit" name="reindex" class="button button-primary" value="Επαναφορά / Έλεγχος Indexes">
            </form>
        </div>
    </div>
    <?php
    if (isset($_POST['reindex']) && check_admin_referer('wcsi_reindex')) {
        wcsi_apply_optimization();
        echo "<div class='updated'><p>Τα indexes ελέγχθηκαν και εφαρμόστηκαν!</p></div>";
    }
}