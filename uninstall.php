<?php
/**
 * Dezinstalacja wtyczki
 */

// Jeśli nie jest wywołany przez WordPress, zakończ
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Usuń opcje
delete_option('indexfixer_gsc_client_id');
delete_option('indexfixer_gsc_client_secret');
delete_option('indexfixer_gsc_access_token');

// Usuń cache
global $wpdb;
$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM $wpdb->options WHERE option_name LIKE %s",
        $wpdb->esc_like('_transient_indexfixer_url_status_') . '%'
    )
);
$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM $wpdb->options WHERE option_name LIKE %s",
        $wpdb->esc_like('_transient_timeout_indexfixer_url_status_') . '%'
    )
); 