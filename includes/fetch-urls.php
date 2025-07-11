<?php
/**
 * Pobieranie URL-i z WordPressa
 */

if (!defined('ABSPATH')) {
    exit;
}

class IndexFixer_Fetch_URLs {
    /**
     * Pobiera wszystkie URL-e z WordPressa
     */
    public static function get_all_urls() {
        $urls = array();
        $settings = get_option('indexfixer_settings', array());
        $post_types = isset($settings['post_types']) && is_array($settings['post_types']) && count($settings['post_types']) > 0 ? $settings['post_types'] : array('post');

        // Pobierz wszystkie opublikowane posty wybranych typów
        $posts = get_posts(array(
            'post_type' => $post_types,
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => array('ID', 'post_type', 'post_date')
        ));

        foreach ($posts as $post) {
            $urls[] = array(
                'url' => get_permalink($post->ID),
                'post_type' => $post->post_type,
                'post_date' => $post->post_date
            );
        }

        return $urls;
    }
} 