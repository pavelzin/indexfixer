<?php
/**
 * Widget WordPress dla niezaindeksowanych URL-ów
 */

if (!defined('ABSPATH')) {
    exit;
}

class IndexFixer_Not_Indexed_Widget extends WP_Widget {
    
    public function __construct() {
        parent::__construct(
            'indexfixer_not_indexed',
            'IndexFixer - Niezaindeksowane posty',
            array(
                'description' => 'Wyświetla listę niezaindeksowanych postów do linkowania wewnętrznego',
            )
        );
        
        // Hook dla automatycznego sprawdzania co 24h
        add_action('wp', array($this, 'maybe_schedule_check'));
        add_action('indexfixer_widget_daily_check', array($this, 'daily_url_check'));
    }
    
    /**
     * Wyświetlanie widget na frontend
     */
    public function widget($args, $instance) {
        $title = !empty($instance['title']) ? $instance['title'] : 'Niezaindeksowane posty';
        $count = !empty($instance['count']) ? (int) $instance['count'] : 5;
        $auto_check = !empty($instance['auto_check']) ? 1 : 0;
        
        echo $args['before_widget'];
        if (!empty($title)) {
            echo $args['before_title'] . apply_filters('widget_title', $title) . $args['after_title'];
        }
        
        // Pobierz niezaindeksowane URL-e z bazy danych
        $not_indexed_urls = IndexFixer_Database::get_urls_by_status('not_indexed', $count);
        
        if (empty($not_indexed_urls)) {
            echo '<p>Brak niezaindeksowanych postów 🎉</p>';
        } else {
            echo '<div class="indexfixer-widget">';
            echo '<ul style="list-style: none; padding: 0; margin: 0;">';
            
            foreach ($not_indexed_urls as $url_data) {
                $post_title = $url_data->post_title ?: 'Bez tytułu';
                $post_url = $url_data->url;
                
                echo '<li style="margin-bottom: 12px; padding: 8px; background: #f9f9f9; border-left: 3px solid #ff6b6b;">';
                echo '<div style="font-weight: bold; margin-bottom: 4px;">';
                echo '<a href="' . esc_url($post_url) . '" title="' . esc_attr($post_title) . '">';
                echo esc_html(wp_trim_words($post_title, 6));
                echo '</a></div>';
                echo '</li>';
            }
            
            echo '</ul>';
            echo '</div>';
        }
        
        echo $args['after_widget'];
    }
    
    /**
     * Formularz ustawień widget w admin
     */
    public function form($instance) {
        $title = !empty($instance['title']) ? $instance['title'] : 'Niezaindeksowane posty';
        $count = !empty($instance['count']) ? $instance['count'] : 5;
        $auto_check = !empty($instance['auto_check']) ? 1 : 0;
        ?>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('title')); ?>">Tytuł:</label>
            <input class="widefat" id="<?php echo esc_attr($this->get_field_id('title')); ?>" 
                   name="<?php echo esc_attr($this->get_field_name('title')); ?>" 
                   type="text" value="<?php echo esc_attr($title); ?>">
        </p>
        
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('count')); ?>">Liczba postów do wyświetlenia:</label>
            <input class="tiny-text" id="<?php echo esc_attr($this->get_field_id('count')); ?>" 
                   name="<?php echo esc_attr($this->get_field_name('count')); ?>" 
                   type="number" min="1" max="20" value="<?php echo esc_attr($count); ?>">
        </p>
        
        <p>
            <input class="checkbox" type="checkbox" <?php checked($auto_check); ?> 
                   id="<?php echo esc_attr($this->get_field_id('auto_check')); ?>" 
                   name="<?php echo esc_attr($this->get_field_name('auto_check')); ?>" />
            <label for="<?php echo esc_attr($this->get_field_id('auto_check')); ?>">
                Automatycznie sprawdzaj URL-e co 24h
            </label>
        </p>
        
        <p style="background: #fff3cd; padding: 8px; border-left: 3px solid #ffc107; margin-top: 15px;">
            <strong>💡 Tip:</strong> Ten widget pomaga w linkowaniu wewnętrznym. 
            Gdy Google zaindeksuje post, automatycznie zniknie z listy.
        </p>
        <?php
    }
    
    /**
     * Zapisanie ustawień widget
     */
    public function update($new_instance, $old_instance) {
        $instance = array();
        $instance['title'] = (!empty($new_instance['title'])) ? sanitize_text_field($new_instance['title']) : '';
        $instance['count'] = (!empty($new_instance['count'])) ? (int) $new_instance['count'] : 5;
        $instance['auto_check'] = (!empty($new_instance['auto_check'])) ? 1 : 0;
        
        // Sprawdź harmonogram automatycznego sprawdzania po zapisaniu ustawień
        add_action('shutdown', array($this, 'maybe_schedule_check'));
        
        return $instance;
    }
    
    /**
     * Sprawdza czy trzeba zaplanować automatyczne sprawdzanie
     */
    public function maybe_schedule_check() {
        // Sprawdź czy jakikolwiek widget ma włączone auto_check
        $widget_instances = get_option('widget_indexfixer_not_indexed', array());
        
        $auto_check_enabled = false;
        foreach ($widget_instances as $instance) {
            if (!empty($instance['auto_check'])) {
                $auto_check_enabled = true;
                break;
            }
        }
        
        $scheduled = wp_next_scheduled('indexfixer_widget_daily_check');
        
        if ($auto_check_enabled && !$scheduled) {
            // Zaplanuj automatyczne sprawdzanie jeśli jest włączone
            wp_schedule_event(time(), 'daily', 'indexfixer_widget_daily_check');
            IndexFixer_Logger::log('Zaplanowano automatyczne sprawdzanie URL-ów przez widget co 24h', 'info');
        } elseif (!$auto_check_enabled && $scheduled) {
            // Usuń harmonogram jeśli auto_check jest wyłączone wszędzie
            wp_clear_scheduled_hook('indexfixer_widget_daily_check');
            IndexFixer_Logger::log('Usunięto automatyczne sprawdzanie URL-ów przez widget', 'info');
        }
    }
    
    /**
     * Codzienne automatyczne sprawdzanie URL-ów
     */
    public function daily_url_check() {
        // Sprawdź czy jakakolwiek instancja widget ma włączone auto_check
        $widget_instances = get_option('widget_indexfixer_not_indexed', array());
        
        $auto_check_enabled = false;
        foreach ($widget_instances as $instance) {
            if (!empty($instance['auto_check'])) {
                $auto_check_enabled = true;
                break;
            }
        }
        
        if (!$auto_check_enabled) {
            return;
        }
        
        IndexFixer_Logger::log('Rozpoczęcie automatycznego sprawdzania URL-ów przez widget', 'info');
        
        // Pobierz URL-e do sprawdzenia (max 10 na raz)
        $urls_to_check = IndexFixer_Database::get_urls_for_checking(10);
        
        if (empty($urls_to_check)) {
            IndexFixer_Logger::log('Brak URL-ów do sprawdzenia przez widget', 'info');
            return;
        }
        
        $gsc_api = new IndexFixer_GSC_API();
        $checked = 0;
        
        foreach ($urls_to_check as $url_data) {
            $status = $gsc_api->check_url_status($url_data->url);
            
            if ($status && !isset($status['error'])) {
                IndexFixer_Database::save_url_status($url_data->post_id, $url_data->url, $status);
                $checked++;
                
                // Rate limiting
                sleep(2);
            }
        }
        
        IndexFixer_Logger::log("Widget automatycznie sprawdził $checked URL-ów", 'success');
    }
}

/**
 * Klasa helper dla zarządzania widgetami
 */
class IndexFixer_Widget_Manager {
    
    public static function init() {
        add_action('widgets_init', array(__CLASS__, 'register_widgets'));
        
        // Hook dla automatycznego dodawania postów do sprawdzania
        add_action('publish_post', array(__CLASS__, 'add_new_post_to_check'), 10, 2);
        add_action('publish_page', array(__CLASS__, 'add_new_post_to_check'), 10, 2);
    }
    
    /**
     * Rejestruje widgety
     */
    public static function register_widgets() {
        register_widget('IndexFixer_Not_Indexed_Widget');
    }
    
    /**
     * Automatycznie dodaje nowe posty do sprawdzania
     */
    public static function add_new_post_to_check($post_id, $post) {
        // Sprawdź czy to pierwszy raz publikowany post
        if ($post->post_status !== 'publish') {
            return;
        }
        
        $url = get_permalink($post_id);
        if (!$url) {
            return;
        }
        
        // Sprawdź czy URL już jest w bazie
        $existing = IndexFixer_Database::get_url_status($url);
        if ($existing) {
            return;
        }
        
        // Dodaj jako 'unknown' - będzie sprawdzony przy następnym cyklu
        IndexFixer_Database::save_url_status($post_id, $url, array(
            'simple_status' => 'unknown',
            'verdict' => null,
            'coverageState' => null,
        ));
        
        IndexFixer_Logger::log("Dodano nowy post do sprawdzania: $url", 'info');
    }
    
    /**
     * Cleanup - usuwa zaplanowane zadania przy deaktywacji
     */
    public static function cleanup() {
        wp_clear_scheduled_hook('indexfixer_widget_daily_check');
    }
}

// Inicjalizuj widget manager
IndexFixer_Widget_Manager::init(); 