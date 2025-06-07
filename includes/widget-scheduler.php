<?php
/**
 * Ujednolicony mechanizm planowania sprawdzania URL-i dla widgetów
 */

if (!defined('ABSPATH')) {
    exit;
}

class IndexFixer_Widget_Scheduler {
    
    private static $instance = null;
    private static $hook_name = 'indexfixer_widget_check';
    private static $test_mode = false;
    private static $test_interval = 600; // 10 minut dla testów
    private static $production_interval = 86400; // 24 godziny dla produkcji
    
    /**
     * Singleton pattern
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Konstruktor
     */
    private function __construct() {
        // Hook dla wykonywania sprawdzania
        add_action(self::$hook_name, array($this, 'execute_widget_check'));
        
        // Hook dla sprawdzania harmonogramu (tylko raz dziennie)
        add_action('wp_loaded', array($this, 'maybe_update_schedule'), 10);
        
        // Dodaj własny interwał dla testów
        add_filter('cron_schedules', array($this, 'add_custom_intervals'));
    }
    
    /**
     * Dodaje własne interwały cron
     */
    public function add_custom_intervals($schedules) {
        $schedules['ten_minutes'] = array(
            'interval' => 600, // 10 minut
            'display' => 'Co 10 minut (test)'
        );
        return $schedules;
    }
    
    /**
     * Włącza tryb testowy (sprawdzanie co 10 minut)
     */
    public static function enable_test_mode() {
        self::$test_mode = true;
        update_option('indexfixer_widget_test_mode', true);
        
        // Usuń stary harmonogram
        wp_clear_scheduled_hook(self::$hook_name);
        
        // Zaplanuj nowy z krótkim interwałem
        wp_schedule_event(time(), 'ten_minutes', self::$hook_name);
        
        IndexFixer_Logger::log('🧪 TRYB TESTOWY WŁĄCZONY: Sprawdzanie widgetów co 10 minut', 'info');
    }
    
    /**
     * Wyłącza tryb testowy (powrót do 24h)
     */
    public static function disable_test_mode() {
        self::$test_mode = false;
        update_option('indexfixer_widget_test_mode', false);
        
        // Usuń stary harmonogram
        wp_clear_scheduled_hook(self::$hook_name);
        
        // Sprawdź czy widgety są aktywne i zaplanuj normalny harmonogram
        $instance = self::get_instance();
        $instance->maybe_update_schedule();
        
        IndexFixer_Logger::log('🏁 TRYB TESTOWY WYŁĄCZONY: Powrót do sprawdzania co 24h', 'info');
    }
    
    /**
     * Sprawdza czy tryb testowy jest włączony
     */
    public static function is_test_mode() {
        return get_option('indexfixer_widget_test_mode', false);
    }
    
    /**
     * Sprawdza czy trzeba zaktualizować harmonogram (wywoływane raz dziennie)
     */
    public function maybe_update_schedule() {
        // Sprawdź czy już sprawdzaliśmy dzisiaj
        $last_check = get_option('indexfixer_widget_schedule_check', 0);
        $today = date('Y-m-d');
        $last_check_date = date('Y-m-d', $last_check);
        
        if ($last_check_date === $today) {
            return; // Już sprawdzaliśmy dzisiaj
        }
        
        // Zapisz że sprawdziliśmy dzisiaj
        update_option('indexfixer_widget_schedule_check', time());
        
        // Sprawdź czy jakikolwiek widget ma włączone auto_check
        $widgets_active = $this->are_widgets_active();
        $scheduled = wp_next_scheduled(self::$hook_name);
        
        if ($widgets_active && !$scheduled) {
            // Zaplanuj sprawdzanie
            $interval = self::is_test_mode() ? 'ten_minutes' : 'daily';
            wp_schedule_event(time(), $interval, self::$hook_name);
            
            $mode = self::is_test_mode() ? 'TESTOWY (10 min)' : 'PRODUKCYJNY (24h)';
            IndexFixer_Logger::log("📅 Zaplanowano automatyczne sprawdzanie widgetów - tryb: $mode", 'info');
            
        } elseif (!$widgets_active && $scheduled) {
            // Usuń harmonogram
            wp_clear_scheduled_hook(self::$hook_name);
            IndexFixer_Logger::log('🗑️ Usunięto automatyczne sprawdzanie widgetów - brak aktywnych widgetów', 'info');
        }
    }
    
    /**
     * Sprawdza czy jakikolwiek widget ma włączone auto_check
     */
    private function are_widgets_active() {
        // Sprawdź widget WordPress
        $widget_instances = get_option('widget_indexfixer_not_indexed', array());
        foreach ($widget_instances as $instance) {
            if (!empty($instance['auto_check'])) {
                return true;
            }
        }
        
        // Sprawdź blok widget (sprawdź czy jest używany w postach/stronach)
        global $wpdb;
        $block_usage = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} 
             WHERE post_content LIKE '%wp:indexfixer/not-indexed-posts%' 
             AND post_status = 'publish'"
        );
        
        if ($block_usage > 0) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Wykonuje sprawdzanie URL-i dla widgetów
     */
    public function execute_widget_check() {
        $mode = self::is_test_mode() ? 'TESTOWY' : 'PRODUKCYJNY';
        IndexFixer_Logger::log("🤖 ROZPOCZĘCIE AUTOMATYCZNEGO SPRAWDZANIA WIDGETÓW - tryb: $mode", 'info');
        
        // Sprawdź czy widgety są nadal aktywne
        if (!$this->are_widgets_active()) {
            IndexFixer_Logger::log('⚠️ Brak aktywnych widgetów - kończę sprawdzanie', 'warning');
            wp_clear_scheduled_hook(self::$hook_name);
            return;
        }
        
        // Pobierz URL-e do sprawdzenia
        $limit = self::is_test_mode() ? 5 : 10; // Mniej URL-i w trybie testowym
        $urls_to_check = $this->get_actual_widget_urls($limit);
        
        if (empty($urls_to_check)) {
            IndexFixer_Logger::log('ℹ️ Brak URL-ów do sprawdzenia przez widgety', 'info');
            return;
        }
        
        IndexFixer_Logger::log("🎯 Znaleziono " . count($urls_to_check) . " URL-ów do sprawdzenia", 'info');
        
        $gsc_api = new IndexFixer_GSC_API();
        $checked = 0;
        $indexed_found = 0;
        
        foreach ($urls_to_check as $url_data) {
            IndexFixer_Logger::log("🔍 Sprawdzam: {$url_data->url}", 'info');
            
            try {
                $status = $gsc_api->check_url_status($url_data->url);
                
                if ($status && !isset($status['error'])) {
                    // Przygotuj dane w ujednoliconym formacie
                    $detailed_status = array(
                        'verdict' => isset($status['indexStatusResult']['verdict']) ? $status['indexStatusResult']['verdict'] : 'unknown',
                        'coverageState' => isset($status['indexStatusResult']['coverageState']) ? $status['indexStatusResult']['coverageState'] : 'unknown',
                        'robotsTxtState' => isset($status['indexStatusResult']['robotsTxtState']) ? $status['indexStatusResult']['robotsTxtState'] : 'unknown',
                        'indexingState' => isset($status['indexStatusResult']['indexingState']) ? $status['indexStatusResult']['indexingState'] : 'unknown',
                        'pageFetchState' => isset($status['indexStatusResult']['pageFetchState']) ? $status['indexStatusResult']['pageFetchState'] : 'unknown',
                        'lastCrawlTime' => isset($status['indexStatusResult']['lastCrawlTime']) ? $status['indexStatusResult']['lastCrawlTime'] : 'unknown',
                        'crawledAs' => isset($status['indexStatusResult']['crawledAs']) ? $status['indexStatusResult']['crawledAs'] : 'unknown'
                    );
                    
                    // Dodaj prosty status
                    if (isset($status['indexStatusResult']['coverageState'])) {
                        $coverage_state = $status['indexStatusResult']['coverageState'];
                        switch($coverage_state) {
                            case 'Submitted and indexed':
                                $detailed_status['simple_status'] = 'INDEXED';
                                $indexed_found++;
                                IndexFixer_Logger::log("🎉 ZAINDEKSOWANO: {$url_data->url}", 'success');
                                break;
                            case 'Crawled - currently not indexed':
                                $detailed_status['simple_status'] = 'NOT_INDEXED';
                                IndexFixer_Logger::log("⏳ Nadal nie zaindeksowane: {$url_data->url}", 'info');
                                break;
                            case 'Discovered - currently not indexed':
                                $detailed_status['simple_status'] = 'PENDING';
                                IndexFixer_Logger::log("🔍 Odkryte ale nie zaindeksowane: {$url_data->url}", 'info');
                                break;
                            default:
                                $detailed_status['simple_status'] = 'OTHER';
                                IndexFixer_Logger::log("❓ Inny status: {$url_data->url} - {$coverage_state}", 'info');
                        }
                    } else {
                        $detailed_status['simple_status'] = 'unknown';
                    }
                    
                    // Zapisz w bazie danych
                    IndexFixer_Database::save_url_status($url_data->post_id, $url_data->url, $detailed_status);
                    $checked++;
                    
                    // Rate limiting
                    sleep(2);
                    
                } else {
                    IndexFixer_Logger::log("❌ Błąd sprawdzania: {$url_data->url}", 'error');
                }
                
            } catch (Exception $e) {
                IndexFixer_Logger::log("💥 Wyjątek przy sprawdzaniu {$url_data->url}: {$e->getMessage()}", 'error');
            }
        }
        
        IndexFixer_Logger::log("🏁 ZAKOŃCZENIE SPRAWDZANIA WIDGETÓW:", 'info');
        IndexFixer_Logger::log("   • Sprawdzono: $checked URL-ów", 'info');
        IndexFixer_Logger::log("   • Nowo zaindeksowane: $indexed_found URL-ów", 'success');
        
        if ($indexed_found > 0) {
            IndexFixer_Logger::log("🎊 Gratulacje! $indexed_found URL-ów zostało zaindeksowanych!", 'success');
        }
    }
    
    /**
     * Ręczne uruchomienie sprawdzania (dla testów)
     */
    public static function run_manual_check() {
        $instance = self::get_instance();
        $instance->execute_widget_check();
    }
    
    /**
     * Pobiera status harmonogramu
     */
    public static function get_schedule_status() {
        $scheduled = wp_next_scheduled(self::$hook_name);
        $test_mode = self::is_test_mode();
        
        return array(
            'scheduled' => $scheduled,
            'next_run' => $scheduled ? date('Y-m-d H:i:s', $scheduled) : null,
            'test_mode' => $test_mode,
            'interval' => $test_mode ? '10 minut' : '24 godziny'
        );
    }
    
    /**
     * Cleanup przy deaktywacji wtyczki
     */
    public static function cleanup() {
        wp_clear_scheduled_hook(self::$hook_name);
        delete_option('indexfixer_widget_test_mode');
        delete_option('indexfixer_widget_schedule_check');
    }
    
    /**
     * NOWA: Pobiera dokładnie te URL-e które są wyświetlane w aktywnych widgetach
     */
    private function get_actual_widget_urls($max_limit = 10) {
        $all_widget_urls = array();
        
        // 1. Pobierz URL-e z widget WordPress
        $widget_instances = get_option('widget_indexfixer_not_indexed', array());
        foreach ($widget_instances as $instance) {
            if (!empty($instance['auto_check'])) {
                $count = !empty($instance['count']) ? (int) $instance['count'] : 5;
                $widget_urls = IndexFixer_Database::get_urls_by_status('not_indexed', $count);
                
                foreach ($widget_urls as $url_data) {
                    $all_widget_urls[$url_data->url] = $url_data; // Użyj URL jako klucz żeby uniknąć duplikatów
                }
            }
        }
        
        // 2. Pobierz URL-e z blok widget (sprawdź wszystkie posty/strony z blokiem)
        global $wpdb;
        $posts_with_blocks = $wpdb->get_results(
            "SELECT post_content FROM {$wpdb->posts} 
             WHERE post_content LIKE '%wp:indexfixer/not-indexed-posts%' 
             AND post_status = 'publish'"
        );
        
        foreach ($posts_with_blocks as $post) {
            // Parsuj blok żeby wyciągnąć parametr count
            preg_match('/wp:indexfixer\/not-indexed-posts\s*({[^}]*})?/', $post->post_content, $matches);
            
            $count = 5; // Domyślna wartość
            if (!empty($matches[1])) {
                $block_attrs = json_decode($matches[1], true);
                if (isset($block_attrs['count'])) {
                    $count = (int) $block_attrs['count'];
                }
            }
            
            $block_urls = IndexFixer_Database::get_urls_by_status('not_indexed', $count);
            foreach ($block_urls as $url_data) {
                $all_widget_urls[$url_data->url] = $url_data; // Użyj URL jako klucz żeby uniknąć duplikatów
            }
        }
        
        // 3. Filtruj tylko te które wymagają sprawdzenia (nie były sprawdzane przez 24h)
        $urls_to_check = array();
        foreach ($all_widget_urls as $url_data) {
            // Sprawdź czy URL wymaga sprawdzenia
            if (empty($url_data->last_checked) || 
                strtotime($url_data->last_checked) < (time() - 24 * 3600)) {
                $urls_to_check[] = $url_data;
                
                if (count($urls_to_check) >= $max_limit) {
                    break; // Ogranicz do max_limit
                }
            }
        }
        
        IndexFixer_Logger::log("🎯 Znaleziono " . count($all_widget_urls) . " unikalnych URL-ów w widgetach, " . count($urls_to_check) . " wymaga sprawdzenia", 'info');
        
        return $urls_to_check;
    }
}

// Inicjalizuj scheduler
IndexFixer_Widget_Scheduler::get_instance(); 