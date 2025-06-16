<?php
/**
 * Obsługa cache'owania wyników
 */

if (!defined('ABSPATH')) {
    exit;
}

class IndexFixer_Cache {
    /**
     * Pobiera status URL-a z cache'a
     */
    public static function get_url_status($url) {
        $cache_key = 'indexfixer_url_status_' . md5($url);
        return get_transient($cache_key);
    }
    
    /**
     * Zapisuje status URL-a w cache'u
     */
    public static function set_url_status($url, $status) {
        $cache_key = 'indexfixer_url_status_' . md5($url);
        set_transient($cache_key, $status, DAY_IN_SECONDS);
    }
    
    /**
     * Czyści cache dla URL-a
     */
    public static function clear_url_status($url) {
        $cache_key = 'indexfixer_url_status_' . md5($url);
        delete_transient($cache_key);
    }
    
    /**
     * Czyści cały cache
     */
    public static function clear_all_cache() {
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
    }
} 