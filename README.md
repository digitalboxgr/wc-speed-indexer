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
  - ένδειξη αν υπάρχουν τα αναμενόμενα indexes.
- Παρέχει χειροκίνητο κουμπί για επανέλεγχο/εφαρμογή indexes.
- Αποθηκεύει την τελευταία ημερομηνία βελτιστοποίησης στο option `wcsi_last_optimization`.

## Indexes που ελέγχονται

Το plugin χρησιμοποιεί το WordPress `dbDelta()` για να εφαρμόσει schema definitions στους παρακάτω πίνακες:

| Πίνακας | Index |
| --- | --- |
| `wp_options` | `autoload` |
| `wp_postmeta` | `meta_key_value (meta_key, meta_value)` |
| `wp_termmeta` | `meta_key_value (meta_key, meta_value)` |
| `wp_term_relationships` | `term_taxonomy_id_object_id (term_taxonomy_id, object_id)` |

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

## Προτεινόμενες βελτιώσεις

- Προσθήκη capability check πριν από κάθε manual reindex action.
- Καλύτερο escaping στο admin output.
- Ασφαλέστερη διαχείριση SQL identifiers στα `SHOW TABLE STATUS` και `SHOW INDEX`.
- Μεταφορά του reindex handler πριν από την εμφάνιση του dashboard, ώστε τα αποτελέσματα να ανανεώνονται άμεσα.
- Έλεγχος ύπαρξης πινάκων πριν από κάθε dashboard query.
- Προσθήκη admin notices για επιτυχία/αποτυχία.
- Προσθήκη compatibility metadata για WordPress, WooCommerce και PHP.
- Πιθανή διάσπαση του plugin σε μικρότερες κλάσεις αν προστεθούν περισσότερες λειτουργίες.

## Απαιτήσεις

- WordPress
- WooCommerce
- MySQL ή MariaDB
- Διαχειριστικά δικαιώματα WordPress

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

### 1.1

- Προσθήκη admin dashboard.
- Προσθήκη χειροκίνητου reindex button.
- Καταγραφή τελευταίας βελτιστοποίησης.

## License

Δεν έχει οριστεί ακόμα license. Αν το repository μπει δημόσια στο GitHub, προτείνεται να προστεθεί αρχείο `LICENSE`.
