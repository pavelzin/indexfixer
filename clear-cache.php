<?php
// TYMCZASOWY PLIK DO CZYSZCZENIA CACHE - USUŃ PO UŻYCIU!

// Zabezpieczenie
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__FILE__) . '/');
}

// Załaduj WordPress
require_once(ABSPATH . 'wp-config.php');

// Wyczyść cache IndexFixer
global $wpdb;

$deleted_transients = $wpdb->query(
    $wpdb->prepare(
        "DELETE FROM $wpdb->options WHERE option_name LIKE %s",
        $wpdb->esc_like('_transient_indexfixer_') . '%'
    )
);

$deleted_timeouts = $wpdb->query(
    $wpdb->prepare(
        "DELETE FROM $wpdb->options WHERE option_name LIKE %s", 
        $wpdb->esc_like('_transient_timeout_indexfixer_') . '%'
    )
);

echo "<h1>IndexFixer Cache Cleared!</h1>";
echo "<p>Usunięto transients: $deleted_transients</p>";
echo "<p>Usunięto timeouts: $deleted_timeouts</p>";
echo "<p><strong>USUŃ TEN PLIK TERAZ!</strong></p>";
echo "<p>Cache został wyczyszczony. Możesz teraz sprawdzić IndexFixer.</p>";
?> 