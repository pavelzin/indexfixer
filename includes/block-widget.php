<?php
/**
 * Widget blokowy dla IndexFixer
 */

if (!defined('ABSPATH')) {
    exit;
}

class IndexFixer_Block_Widget {
    
    public static function init() {
        add_action('init', array(__CLASS__, 'register_block'));
        add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue_block_assets'));
        add_action('enqueue_block_editor_assets', array(__CLASS__, 'enqueue_editor_assets'));
        add_action('wp_ajax_indexfixer_block_preview', array(__CLASS__, 'ajax_block_preview'));
        add_action('wp_ajax_nopriv_indexfixer_block_preview', array(__CLASS__, 'ajax_block_preview'));
    }
    
    /**
     * Rejestruje blok
     */
    public static function register_block() {
        if (!function_exists('register_block_type')) {
            return;
        }
        
        register_block_type('indexfixer/not-indexed-posts', array(
            'attributes' => array(
                'title' => array(
                    'type' => 'string',
                    'default' => 'Niezaindeksowane posty'
                ),
                'count' => array(
                    'type' => 'number',
                    'default' => 5
                ),
                'autoCheck' => array(
                    'type' => 'boolean',
                    'default' => false
                )
            ),
            'render_callback' => array(__CLASS__, 'render_block'),
            'editor_script' => 'indexfixer-block',
            'editor_style' => 'indexfixer-block-editor',
            'style' => 'indexfixer-block-style'
        ));
    }
    
    /**
     * Renderuje blok na froncie
     */
    public static function render_block($attributes) {
        $title = isset($attributes['title']) ? $attributes['title'] : 'Niezaindeksowane posty';
        $count = isset($attributes['count']) ? (int) $attributes['count'] : 5;
        $auto_check = isset($attributes['autoCheck']) ? $attributes['autoCheck'] : false;
        
        // Jeśli auto_check jest włączone, zaplanuj sprawdzanie
        if ($auto_check) {
            self::maybe_schedule_check();
        }
        
        // Pobierz niezaindeksowane URL-e z bazy danych
        $not_indexed_urls = IndexFixer_Database::get_urls_by_status('not_indexed', $count);
        
        ob_start();
        ?>
        <div class="wp-block-indexfixer-not-indexed-posts indexfixer-widget">
            <?php if (!empty($title)): ?>
                <h3 style="margin-top: 0; margin-bottom: 15px;"><?php echo esc_html($title); ?></h3>
            <?php endif; ?>
            
            <?php if (empty($not_indexed_urls)): ?>
                <p>Brak niezaindeksowanych postów 🎉</p>
            <?php else: ?>
                <ul style="list-style: none; padding: 0; margin: 0;">
                    <?php foreach ($not_indexed_urls as $url_data): ?>
                        <?php
                        $post_title = $url_data->post_title ?: 'Bez tytułu';
                        $post_url = $url_data->url;
                        ?>
                        <li style="margin-bottom: 12px; padding: 8px; background: #f9f9f9; border-left: 3px solid #ff6b6b;">
                            <div style="font-weight: bold; margin-bottom: 4px;">
                                <a href="<?php echo esc_url($post_url); ?>" title="<?php echo esc_attr($post_title); ?>">
                                    <?php echo esc_html(wp_trim_words($post_title, 6)); ?>
                                </a>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Ładuje assets bloku na froncie
     */
    public static function enqueue_block_assets() {
        if (has_block('indexfixer/not-indexed-posts')) {
            wp_enqueue_style(
                'indexfixer-block-style',
                INDEXFIXER_PLUGIN_URL . 'assets/css/block.css',
                array(),
                INDEXFIXER_VERSION
            );
        }
    }
    
    /**
     * Ładuje assets bloku w edytorze
     */
    public static function enqueue_editor_assets() {
        wp_enqueue_script(
            'indexfixer-block',
            INDEXFIXER_PLUGIN_URL . 'assets/js/indexfixer-block.js',
            array('wp-blocks', 'wp-element', 'wp-editor', 'wp-components', 'wp-i18n'),
            INDEXFIXER_VERSION,
            true
        );
        
        wp_localize_script('indexfixer-block', 'indexfixer_block', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('indexfixer_block_nonce')
        ));
        
        wp_enqueue_style(
            'indexfixer-block-editor',
            INDEXFIXER_PLUGIN_URL . 'assets/css/block-editor.css',
            array('wp-edit-blocks'),
            INDEXFIXER_VERSION
        );
    }
    
    /**
     * AJAX podgląd bloku w edytorze
     */
    public static function ajax_block_preview() {
        if (!wp_verify_nonce($_POST['nonce'], 'indexfixer_block_nonce')) {
            wp_send_json_error('Invalid nonce');
        }
        
        $count = isset($_POST['count']) ? (int) $_POST['count'] : 5;
        $not_indexed_urls = IndexFixer_Database::get_urls_by_status('not_indexed', $count);
        
        $posts = array();
        foreach ($not_indexed_urls as $url_data) {
            $posts[] = array(
                'title' => $url_data->post_title ?: 'Bez tytułu',
                'url' => $url_data->url
            );
        }
        
        wp_send_json_success($posts);
    }
    
    /**
     * Sprawdza czy trzeba zaplanować automatyczne sprawdzanie
     */
    private static function maybe_schedule_check() {
        $scheduled = wp_next_scheduled('indexfixer_widget_daily_check');
        
        if (!$scheduled) {
            wp_schedule_event(time(), 'daily', 'indexfixer_widget_daily_check');
            IndexFixer_Logger::log('Zaplanowano automatyczne sprawdzanie URL-ów przez blok widget co 24h', 'info');
        }
    }
}

// Inicjalizuj blok widget
IndexFixer_Block_Widget::init(); 