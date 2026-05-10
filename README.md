# WC Speed Indexer & Dashboard

WordPress/WooCommerce plugin για βασική βελτιστοποίηση επιλεγμένων database indexes και απλό admin dashboard παρακολούθησης.

Το plugin στοχεύει σε WooCommerce sites με μεγάλο όγκο προϊόντων, metadata, attributes και κατηγοριών, όπου συχνά εμφανίζονται αργά queries σε πίνακες όπως `wp_postmeta`, `wp_termmeta`, `wp_term_relationships` και `wp_options`.

## Τι κάνει

- Προσθέτει ή ελέγχει indexes σε βασικούς WordPress/WooCommerce πίνακες.
- Δημιουργεί admin menu με τίτλο **DB Indexer**.
- Εμφανίζει dashboard με:
  - όνομα πίνακα,
  - αριθμό εγγραφών,
  - μέγεθος indexes,
  - managed indexes,
  - ένδειξη αν υπάρχουν ή λείπουν indexes.
- Παρέχει χειροκίνητο κουμπί για επανέλεγχο/εφαρμογή indexes.
- Εμφανίζει admin notices για επιτυχία ή αποτυχία.
- Εμφανίζει read-only diagnostics για managed tables, missing indexes, autoloaded options και object cache.
- Παραλείπει με ασφάλεια optional WooCommerce/HPOS πίνακες όταν δεν υπάρχουν.
- Αποθηκεύει την τελευταία ημερομηνία βελτιστοποίησης στο option `wcsi_last_optimization`.

## Diagnostics

Το dashboard περιλαμβάνει read-only diagnostics που βοηθούν να καταλάβεις αν το performance θέμα είναι πιθανό να σχετίζεται με τη βάση ή όχι.

Εμφανίζει:

- πόσοι managed πίνακες υπάρχουν,
- πόσα managed indexes λείπουν,
- μέγεθος και πλήθος autoloaded options,
- αν υπάρχει persistent object cache,
- τους μεγαλύτερους managed πίνακες με rows, data size και index size,
- απλά health signals για missing indexes ή πολύ μεγάλους πίνακες.

Τα diagnostics δεν αλλάζουν τη βάση και δεν αντικαθιστούν slow query log ή Query Monitor. Είναι γρήγορη ένδειξη για το πού αξίζει να κοιτάξεις πρώτα.

## Indexes που ελέγχονται

Το plugin εφαρμόζει τα indexes με ελεγχόμενο `ALTER TABLE ADD INDEX`, μόνο όταν:

- ο πίνακας υπάρχει,
- το index δεν υπάρχει ήδη,
- το table/index/column identifier περνάει από safety validation.

| Πίνακας | Index |
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

Το πραγματικό prefix του site λαμβάνεται από το `$wpdb`, άρα οι πίνακες δεν χρειάζεται να έχουν απαραίτητα prefix `wp_`.

## Εγκατάσταση

1. Ανέβασε τον φάκελο του plugin στο:

   ```text
   wp-content/plugins/wc-speed-indexer
   ```

2. Βεβαιώσου ότι το βασικό αρχείο είναι:

   ```text
   wc-speed-indexer.php
   ```

3. Ενεργοποίησε το plugin από το WordPress admin.
4. Πήγαινε στο **DB Indexer** από το admin menu.

## Χρήση

Με την ενεργοποίηση του plugin γίνεται αυτόματα προσπάθεια εφαρμογής των indexes.

Για χειροκίνητο έλεγχο:

1. Άνοιξε το WordPress admin.
2. Πήγαινε στο **DB Indexer**.
3. Πάτησε **Επαναφορά / Έλεγχος Indexes**.

## Σημαντικές σημειώσεις

- Πριν το χρησιμοποιήσεις σε production site, πάρε πλήρες backup της βάσης.
- Σε μεγάλα WooCommerce sites, η δημιουργία indexes μπορεί να χρειαστεί χρόνο και να επιβαρύνει προσωρινά τη βάση.
- Καλό είναι να δοκιμάζεται πρώτα σε staging περιβάλλον.
- Το plugin δεν αφαιρεί indexes κατά την απενεργοποίηση.
- Το plugin δεν κάνει query profiling. Ελέγχει μόνο την ύπαρξη συγκεκριμένων indexes.
- Τα optional WooCommerce/HPOS indexes εφαρμόζονται μόνο όταν οι αντίστοιχοι πίνακες υπάρχουν ήδη.

## Προτεινόμενες βελτιώσεις

- Πιθανή διάσπαση του plugin σε μικρότερες κλάσεις αν προστεθούν περισσότερες λειτουργίες.
- Προσθήκη query profiling με πραγματικά slow queries πριν προστεθούν πιο επιθετικά indexes.
- Προσθήκη WP-CLI command για ασφαλέστερο reindex σε μεγάλα production sites.
- Προσθήκη επιλογής dry-run για να φαίνεται τι θα προστεθεί πριν γίνει αλλαγή στη βάση.

## Απαιτήσεις

- WordPress
- WooCommerce
- MySQL ή MariaDB
- Διαχειριστικά δικαιώματα WordPress
- PHP 7.4 ή νεότερο

## Ανάπτυξη

Η τρέχουσα έκδοση είναι απλή και βρίσκεται σε ένα βασικό PHP αρχείο:

```text
wc-speed-indexer.php
```

Για έλεγχο σύνταξης:

```bash
php -l wc-speed-indexer.php
```

## Changelog

### 1.3

- Προσθήκη read-only diagnostics panel.
- Προσθήκη autoloaded options size check.
- Προσθήκη persistent object cache signal.
- Προσθήκη largest managed tables summary.
- Προσθήκη health signals για missing indexes και πολύ μεγάλους πίνακες.

### 1.2

- Προσθήκη capability checks στο manual reindex.
- Προσθήκη escaping στο admin output.
- Προσθήκη ασφαλούς SQL identifier handling.
- Μεταφορά reindex handler πριν από το dashboard render.
- Προσθήκη table existence checks.
- Προσθήκη admin notices.
- Προσθήκη WordPress/WooCommerce/PHP compatibility metadata.
- Προσθήκη επιπλέον συντηρητικών indexes για core meta, WooCommerce order item και HPOS order meta tables.

### 1.1

- Προσθήκη admin dashboard.
- Προσθήκη χειροκίνητου reindex button.
- Καταγραφή τελευταίας βελτιστοποίησης.

## License

Δεν έχει οριστεί ακόμα license. Αν το repository μπει δημόσια στο GitHub, προτείνεται να προστεθεί αρχείο `LICENSE`.
