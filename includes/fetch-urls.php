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
        
        // Pobierz wszystkie opublikowane posty
        $posts = get_posts(array(
            'post_type' => 'post',
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
        
        // Pobierz wszystkie opublikowane strony
        $pages = get_pages(array(
            'post_status' => 'publish',
            'fields' => array('ID', 'post_type', 'post_date')
        ));
        
        foreach ($pages as $page) {
            $urls[] = array(
                'url' => get_permalink($page->ID),
                'post_type' => $page->post_type,
                'post_date' => $page->post_date
            );
        }
        
        return $urls;
    }
} 