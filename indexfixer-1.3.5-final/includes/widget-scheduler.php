<?php
/**
 * Ujednolicony mechanizm planowania sprawdzania URL-i dla widgetów
 */

if (!defined('ABSPATH')) {
    exit;
}

class IndexFixer_Widget_Scheduler {
    
    private static $instance = null;
    public static $hook_name = 'indexfixer_widget_check'; // Zmieniono na public żeby było dostępne z zewnątrz
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
        
        IndexFixer_Logger::log('🧪 TRYB TESTOWY WŁĄCZONY - usuwam stary harmonogram', 'info');
        
        // Usuń WSZYSTKIE crony z tym hookiem
        wp_clear_scheduled_hook(self::$hook_name);
        
        // Wyczyść cache sprawdzania harmonogramu
        delete_option('indexfixer_widget_schedule_check');
        
        // Zaplanuj nowy z krótkim interwałem (10 minut)
        $scheduled = wp_schedule_event(time(), 'ten_minutes', self::$hook_name);
        
        if ($scheduled !== false) {
            $next_run = wp_next_scheduled(self::$hook_name);
            $next_run_local = date('Y-m-d H:i:s', $next_run + (get_option('gmt_offset') * 3600));
            IndexFixer_Logger::log("✅ Zaplanowano nowy harmonogram TESTOWY (10 min) - następne uruchomienie: $next_run_local", 'success');
        } else {
            IndexFixer_Logger::log('❌ Nie udało się zaplanować nowego harmonogramu testowego', 'error');
        }
    }
    
    /**
     * Wyłącza tryb testowy (powrót do 24h)
     */
    public static function disable_test_mode() {
        self::$test_mode = false;
        update_option('indexfixer_widget_test_mode', false);
        
        IndexFixer_Logger::log('🏁 TRYB TESTOWY WYŁĄCZONY - usuwam stary harmonogram', 'info');
        
        // Usuń WSZYSTKIE crony z tym hookiem (może być kilka z różnymi interwałami)
        wp_clear_scheduled_hook(self::$hook_name);
        
        // Wyczyść cache sprawdzania harmonogramu żeby wymusić ponowne planowanie
        delete_option('indexfixer_widget_schedule_check');
        
        // Sprawdź czy widgety są aktywne
        $instance = self::get_instance();
        $widgets_active = $instance->are_widgets_active();
        
        if ($widgets_active) {
            // Zaplanuj nowy harmonogram w trybie produkcyjnym (24h)
            $scheduled = wp_schedule_event(time(), 'daily', self::$hook_name);
            
            if ($scheduled !== false) {
                $next_run = wp_next_scheduled(self::$hook_name);
                $next_run_local = date('Y-m-d H:i:s', $next_run + (get_option('gmt_offset') * 3600));
                IndexFixer_Logger::log("✅ Zaplanowano nowy harmonogram PRODUKCYJNY (24h) - następne uruchomienie: $next_run_local", 'success');
            } else {
                IndexFixer_Logger::log('❌ Nie udało się zaplanować nowego harmonogramu', 'error');
            }
        } else {
            IndexFixer_Logger::log('ℹ️ Brak aktywnych widgetów - nie planuje harmonogramu', 'info');
        }
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
     * @return bool True jeśli znaleziono aktywne widgety
     */
    public function are_widgets_active() {
        $force_active = apply_filters('indexfixer_force_widgets_active', false);
        if ($force_active === true) {
            IndexFixer_Logger::log("🔧 Aktywność widgetów wymuszona przez filtr", 'info');
            return true;
        }
        // Sprawdź klasyczne widgety
        $widget_instances = get_option('widget_indexfixer_not_indexed', array());
        $active_instance_found = false;
        foreach ($widget_instances as $key => $instance) {
            if (is_numeric($key) && is_array($instance) && !empty($instance)) {
                IndexFixer_Logger::log("📊 Znaleziono instancję widgetu #{$key}", 'debug');
                if (!empty($instance['auto_check'])) {
                    IndexFixer_Logger::log("✅ Znaleziono aktywny widget #{$key} z włączonym auto_check", 'info');
                    $active_instance_found = true;
                    break;
                }
            }
        }
        if ($active_instance_found) {
            return true;
        }
        // NOWOŚĆ: Wykrywanie bloków IndexFixer w sidebarach (Gutenberg)
        $sidebars_widgets = get_option('sidebars_widgets', array());
        foreach ($sidebars_widgets as $sidebar_id => $widgets) {
            if (!is_array($widgets)) continue;
            foreach ($widgets as $widget_id) {
                // Sprawdź czy to blok IndexFixer (np. block-2, block-3...)
                if (strpos($widget_id, 'block-') === 0) {
                    // Pobierz zawartość bloku z bazy danych
                    global $wpdb;
                    $post = $wpdb->get_row($wpdb->prepare(
                        "SELECT post_content FROM {$wpdb->posts} WHERE ID = %d AND post_status = 'publish'",
                        (int)str_replace('block-', '', $widget_id)
                    ));
                    if ($post && strpos($post->post_content, 'wp:indexfixer/not-indexed-posts') !== false) {
                        IndexFixer_Logger::log("🟩 Znaleziono blok IndexFixer w sidebarze '{$sidebar_id}' (ID: {$widget_id})", 'info');
                        return true;
                    }
                }
            }
        }
        // Sprawdź bloki IndexFixer w postach/stronach (jak dotychczas)
        global $wpdb;
        $block_usage = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} 
             WHERE post_content LIKE '%wp:indexfixer/not-indexed-posts%' 
             AND post_status = 'publish'"
        );
        if ($block_usage > 0) {
            IndexFixer_Logger::log("✅ Znaleziono {$block_usage} bloków Gutenberga", 'info');
            return true;
        }
        IndexFixer_Logger::log("❌ Nie znaleziono aktywnych widgetów ani bloków", 'warning');
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
                    // Dodajemy logikę widget_since
                    if (empty($url_data->widget_since)) {
                        // Ustaw widget_since na dziś jeśli nie było
                        IndexFixer_Database::save_url_status($url_data->post_id, $url_data->url, array_merge((array)$url_data, ['widget_since' => current_time('mysql')]));
                        $url_data->widget_since = current_time('mysql');
                    }
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
            preg_match('/wp:indexfixer\\/not-indexed-posts\\s*({[^}]*})?/', $post->post_content, $matches);
            $count = 5; // Domyślna wartość
            if (!empty($matches[1])) {
                $block_attrs = json_decode($matches[1], true);
                if (isset($block_attrs['count'])) {
                    $count = (int) $block_attrs['count'];
                }
            }
            $block_urls = IndexFixer_Database::get_urls_by_status('not_indexed', $count);
            foreach ($block_urls as $url_data) {
                if (!isset($all_widget_urls[$url_data->url])) { // Tylko jeśli jeszcze nie ma
                    // Dodajemy logikę widget_since
                    if (empty($url_data->widget_since)) {
                        IndexFixer_Database::save_url_status($url_data->post_id, $url_data->url, array_merge((array)$url_data, ['widget_since' => current_time('mysql')]));
                        $url_data->widget_since = current_time('mysql');
                    }
                    $url_data->widget_source = 'Blok w: ' . $post->post_title;
                    $url_data->widget_count = $count;
                    $all_widget_urls[$url_data->url] = $url_data;
                }
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
    
    /**
     * PUBLICZNA: Pobiera wszystkie URL-e wyświetlane w aktywnych widgetach (dla dashboardu)
     */
    public static function get_all_widget_urls() {
        $all_widget_urls = array();
        
        // Debugowanie
        error_log('IndexFixer: get_all_widget_urls() - START');
        
        // 1. Pobierz URL-e z widget WordPress
        $widget_instances = get_option('widget_indexfixer_not_indexed', array());
        error_log('IndexFixer: Znaleziono ' . count($widget_instances) . ' instancji WordPress widget');
        error_log('IndexFixer: Raw widget_instances: ' . print_r($widget_instances, true));
        
        foreach ($widget_instances as $key => $instance) {
            error_log("IndexFixer: Sprawdzam instancję #{$key}: " . print_r($instance, true));
            
            if (!empty($instance['auto_check'])) {
                $count = !empty($instance['count']) ? (int) $instance['count'] : 5;
                // Zwiększ limit jeśli widget ma więcej niż 10 URL-ów
                $actual_limit = max($count, 15); // Minimum 15 URL-ów
                $post_type = !empty($instance['post_type']) ? $instance['post_type'] : 'post';
                error_log("IndexFixer: WordPress widget #{$key} - count: $count, actual_limit: $actual_limit, post_type: $post_type, auto_check: włączone");
                
                $widget_urls = IndexFixer_Database::get_urls_by_status_and_type('not_indexed', $post_type, $actual_limit);
                error_log('IndexFixer: WordPress widget #{$key} zwrócił ' . count($widget_urls) . ' URL-ów (limit: ' . $actual_limit . ')');
                
                $added_count = 0;
                foreach ($widget_urls as $url_data) {
                    if (!isset($all_widget_urls[$url_data->url])) { // Tylko jeśli jeszcze nie ma
                        $url_data->widget_source = 'WordPress Widget #' . $key . ' (' . $post_type . ')';
                        $url_data->widget_count = $count;
                        $url_data->widget_post_type = $post_type;
                        $all_widget_urls[$url_data->url] = $url_data;
                        $added_count++;
                        error_log("IndexFixer: Dodano URL z widget #{$key}: {$url_data->url}");
                    } else {
                        error_log("IndexFixer: Pominięto duplikat URL z widget #{$key}: {$url_data->url}");
                    }
                }
                error_log("IndexFixer: Widget #{$key} dodał {$added_count} nowych URL-ów (z {$count} pobranych)");
            } else {
                error_log("IndexFixer: WordPress widget #{$key} - auto_check: WYŁĄCZONE");
            }
        }
        
        // 2. Pobierz URL-e z blok widget (sprawdź wszystkie posty/strony z blokiem)
        global $wpdb;
        $posts_with_blocks = $wpdb->get_results(
            "SELECT ID, post_title, post_content FROM {$wpdb->posts} 
             WHERE post_content LIKE '%wp:indexfixer/not-indexed-posts%' 
             AND post_status = 'publish'"
        );
        
        error_log('IndexFixer: Znaleziono ' . count($posts_with_blocks) . ' postów z blokami');
        
        foreach ($posts_with_blocks as $post) {
            // Parsuj blok żeby wyciągnąć parametr count i post_type
            preg_match('/wp:indexfixer\\/not-indexed-posts\\s*({[^}]*})?/', $post->post_content, $matches);
            $count = 5; // Domyślna wartość
            $post_type = 'post';
            if (!empty($matches[1])) {
                $block_attrs = json_decode($matches[1], true);
                if (isset($block_attrs['count'])) {
                    $count = (int) $block_attrs['count'];
                }
                if (isset($block_attrs['postType'])) {
                    $post_type = sanitize_text_field($block_attrs['postType']);
                }
            }
            
            error_log("IndexFixer: Blok w '{$post->post_title}' - count: $count, post_type: $post_type");
            
            $block_urls = IndexFixer_Database::get_urls_by_status_and_type('not_indexed', $post_type, $count);
            error_log('IndexFixer: Blok zwrócił ' . count($block_urls) . ' URL-ów');
            
            foreach ($block_urls as $url_data) {
                if (!isset($all_widget_urls[$url_data->url])) { // Tylko jeśli jeszcze nie ma
                    $url_data->widget_source = 'Blok w: ' . $post->post_title . ' (' . $post_type . ')';
                    $url_data->widget_count = $count;
                    $url_data->widget_post_type = $post_type;
                    $all_widget_urls[$url_data->url] = $url_data;
                }
            }
        }
        
        error_log('IndexFixer: get_all_widget_urls() - KONIEC, zwracam ' . count($all_widget_urls) . ' URL-ów');
        
        return array_values($all_widget_urls); // Zwróć jako zwykłą tablicę
    }
    
    /**
     * Zwraca raport diagnostyczny o widgetach
     * @return array Szczegółowe informacje o stanie widgetów
     */
    public static function get_widget_diagnostic_report() {
        global $wpdb;
        $instance = self::get_instance();
        $report = array();
        
        // 1. Sprawdź opcję widget_indexfixer_not_indexed
        $widget_instances = get_option('widget_indexfixer_not_indexed', array());
        $active_instances = array();
        
        foreach ($widget_instances as $key => $instance_data) {
            if (is_numeric($key) && !empty($instance_data)) {
                $active_instances[$key] = $instance_data;
            }
        }
        
        $report['widget_option'] = array(
            'raw_data' => $widget_instances,
            'active_instances' => $active_instances,
            'count' => count($active_instances)
        );
        
        // 2. Sprawdź sidebars_widgets
        $sidebars_widgets = get_option('sidebars_widgets', array());
        $found_in_sidebars = array();
        
        foreach ($sidebars_widgets as $sidebar_id => $widgets) {
            if (!is_array($widgets)) continue;
            
            foreach ($widgets as $widget_id) {
                if (strpos($widget_id, 'indexfixer_not_indexed-') === 0) {
                    $id_parts = explode('-', $widget_id);
                    $instance_id = isset($id_parts[1]) ? (int)$id_parts[1] : 0;
                    $found_in_sidebars[] = array(
                        'sidebar_id' => $sidebar_id,
                        'widget_id' => $widget_id,
                        'instance_id' => $instance_id
                    );
                }
            }
        }
        
        $report['sidebars'] = array(
            'found_instances' => $found_in_sidebars,
            'count' => count($found_in_sidebars)
        );
        
        // 3. Sprawdź bloki Gutenberga
        $block_usage = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} 
             WHERE post_content LIKE '%wp:indexfixer/not-indexed-posts%' 
             AND post_status = 'publish'"
        );
        
        $report['gutenberg_blocks'] = array(
            'count' => (int)$block_usage,
            'query_result' => $block_usage
        );
        
        // 4. Sprawdź wynik funkcji are_widgets_active
        $is_active = $instance->are_widgets_active();
        $force_active = apply_filters('indexfixer_force_widgets_active', false);
        
        $report['status'] = array(
            'is_active' => $is_active,
            'force_active' => $force_active,
            'hook_name' => self::$hook_name,
            'next_scheduled' => wp_next_scheduled(self::$hook_name)
        );
        
        return $report;
    }
}

// Inicjalizuj scheduler
IndexFixer_Widget_Scheduler::get_instance(); 