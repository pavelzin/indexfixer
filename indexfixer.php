<?php
/**
 * Plugin Name: IndexFixer
 * Plugin URI: https://github.com/pavelzin/indexfixer.git
 * Description: Wtyczka do sprawdzania statusu indeksowania URL-i w Google Search Console
 * Version: 1.0.22
 * Author: Pawel Zinkiewicz
 * Author URI: https://bynajmniej.pl
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: indexfixer
 * Domain Path: /languages
 */

// Zabezpieczenie przed bezpośrednim dostępem
if (!defined('ABSPATH')) {
    exit;
}

// Sprawdzenie czy WordPress jest zainstalowany
if (!function_exists('add_action')) {
    die('WordPress nie jest zainstalowany.');
}

// Definicje stałych
define('INDEXFIXER_VERSION', '1.0.22');
define('INDEXFIXER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('INDEXFIXER_PLUGIN_URL', plugin_dir_url(__FILE__));

// Konfiguracja wtyczki
define('INDEXFIXER_URL_LIMIT', 500); // Maksymalna liczba URL-ów do sprawdzania

// Dołączanie plików
require_once INDEXFIXER_PLUGIN_DIR . 'includes/logger.php';
require_once INDEXFIXER_PLUGIN_DIR . 'includes/fetch-urls.php';
require_once INDEXFIXER_PLUGIN_DIR . 'includes/cache.php';
require_once INDEXFIXER_PLUGIN_DIR . 'includes/helpers.php';
require_once INDEXFIXER_PLUGIN_DIR . 'includes/auth-handler.php';
require_once INDEXFIXER_PLUGIN_DIR . 'includes/gsc-api.php';
require_once INDEXFIXER_PLUGIN_DIR . 'includes/database.php';
require_once INDEXFIXER_PLUGIN_DIR . 'includes/widget.php';
require_once INDEXFIXER_PLUGIN_DIR . 'includes/block-widget.php';
require_once INDEXFIXER_PLUGIN_DIR . 'includes/dashboard-widget.php';
require_once INDEXFIXER_PLUGIN_DIR . 'admin/dashboard.php';

// Inicjalizacja wtyczki
function indexfixer_init() {
    // Inicjalizacja dashboardu
    new IndexFixer_Dashboard();
    
    // Rejestracja skryptów i stylów - obsługiwane przez IndexFixer_Dashboard
    
    // Rejestracja endpointów AJAX
    add_action('wp_ajax_indexfixer_refresh_data', 'indexfixer_ajax_refresh_data');
    add_action('wp_ajax_indexfixer_export_csv', 'indexfixer_ajax_export_csv');
    add_action('wp_ajax_indexfixer_check_single_url', 'indexfixer_ajax_check_single_url');
    
    // Rejestracja harmonogramu
    add_action('indexfixer_check_urls_event', 'indexfixer_check_urls');
    
    // Rejestracja harmonogramu przy aktywacji wtyczki
    register_activation_hook(__FILE__, 'indexfixer_activate');
    
    // Usunięcie harmonogramu przy deaktywacji wtyczki
    register_deactivation_hook(__FILE__, 'indexfixer_deactivate');
}
add_action('plugins_loaded', 'indexfixer_init');

// Rejestracja skryptów i stylów
function indexfixer_register_scripts() {
    wp_register_style(
        'indexfixer-admin',
        INDEXFIXER_PLUGIN_URL . 'assets/css/admin.css',
        array(),
        INDEXFIXER_VERSION
    );
    
    wp_register_script(
        'indexfixer-admin',
        INDEXFIXER_PLUGIN_URL . 'assets/js/admin.js',
        array('jquery'),
        INDEXFIXER_VERSION,
        true
    );
    
    wp_localize_script('indexfixer-admin', 'indexfixer', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('indexfixer_nonce')
    ));
}

// Obsługa AJAX - odświeżanie danych
function indexfixer_ajax_refresh_data() {
    check_ajax_referer('indexfixer_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Brak uprawnień');
    }
    
    IndexFixer_Logger::log('Ręczne odświeżanie danych', 'info');
    indexfixer_check_urls();
    
    wp_send_json_success(array(
        'message' => 'Dane zostały odświeżone',
        'logs' => IndexFixer_Logger::format_logs()
    ));
}

// Obsługa AJAX - eksport do CSV
function indexfixer_ajax_export_csv() {
    check_ajax_referer('indexfixer_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Brak uprawnień');
    }
    
    // Użyj tego samego limitu co w głównej funkcji
    $url_limit = apply_filters('indexfixer_url_limit', INDEXFIXER_URL_LIMIT);
    $all_urls = IndexFixer_Fetch_URLs::get_all_urls();
    $urls = array_slice($all_urls, 0, $url_limit);
    $gsc_api = new IndexFixer_GSC_API();
    $results = array();
    
    foreach ($urls as $url_data) {
        $cached_status = IndexFixer_Cache::get_url_status($url_data['url']);
        if ($cached_status === false) {
            $status = $gsc_api->check_url_status($url_data['url']);
            if ($status !== false) {
                // Wyciągnij czytelny status z tablicy API (jak w głównej funkcji)
                $index_status = 'unknown';
                if (isset($status['indexStatusResult']['coverageState'])) {
                    $coverage_state = $status['indexStatusResult']['coverageState'];
                    switch($coverage_state) {
                        case 'Submitted and indexed':
                            $index_status = 'INDEXED';
                            break;
                        case 'Crawled - currently not indexed':
                            $index_status = 'NOT_INDEXED';
                            break;
                        case 'Discovered - currently not indexed':
                            $index_status = 'PENDING';
                            break;
                        case 'Page with redirect':
                        case 'Excluded by robots.txt':
                        case 'Blocked due to unauthorized request (401)':
                        case 'Not found (404)':
                            $index_status = 'EXCLUDED';
                            break;
                        default:
                            $index_status = $coverage_state;
                    }
                }
                
                IndexFixer_Cache::set_url_status($url_data['url'], $index_status);
                $results[] = array(
                    'url' => $url_data['url'],
                    'status' => $index_status
                );
            }
        } else {
            $results[] = array(
                'url' => $url_data['url'],
                'status' => $cached_status
            );
        }
    }
    
    $filename = 'indexfixer-export-' . date('Y-m-d') . '.csv';
    $csv = array();
    
    // Nagłówki
    $csv[] = array('URL', 'Status');
    
    // Dane
    foreach ($results as $result) {
        $csv[] = array($result['url'], $result['status']);
    }
    
    // Generowanie CSV
    $output = fopen('php://temp', 'r+');
    foreach ($csv as $row) {
        fputcsv($output, $row);
    }
    rewind($output);
    $csv_content = stream_get_contents($output);
    fclose($output);
    
    wp_send_json_success(array(
        'filename' => $filename,
        'content' => $csv_content
    ));
}

// Obsługa AJAX - sprawdzanie pojedynczego URL-a
function indexfixer_ajax_check_single_url() {
    check_ajax_referer('indexfixer_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Brak uprawnień');
    }
    
    $url = sanitize_url($_POST['url']);
    if (empty($url)) {
        wp_send_json_error('Podaj prawidłowy URL');
    }
    
    IndexFixer_Logger::log("🔍 Sprawdzanie pojedynczego URL: $url", 'info');
    
    $gsc_api = new IndexFixer_GSC_API();
    $status = $gsc_api->check_url_status($url);
    
    if ($status === false) {
        IndexFixer_Logger::log("❌ Błąd sprawdzania URL: $url", 'error');
        wp_send_json_error('Nie udało się sprawdzić URL. Sprawdź logi.');
    }
    
    // Przygotuj szczegółowe dane
    $detailed_status = array(
        'verdict' => isset($status['indexStatusResult']['verdict']) ? $status['indexStatusResult']['verdict'] : 'unknown',
        'coverageState' => isset($status['indexStatusResult']['coverageState']) ? $status['indexStatusResult']['coverageState'] : 'unknown',
        'robotsTxtState' => isset($status['indexStatusResult']['robotsTxtState']) ? $status['indexStatusResult']['robotsTxtState'] : 'unknown',
        'indexingState' => isset($status['indexStatusResult']['indexingState']) ? $status['indexStatusResult']['indexingState'] : 'unknown',
        'pageFetchState' => isset($status['indexStatusResult']['pageFetchState']) ? $status['indexStatusResult']['pageFetchState'] : 'unknown',
        'lastCrawlTime' => isset($status['indexStatusResult']['lastCrawlTime']) ? $status['indexStatusResult']['lastCrawlTime'] : 'unknown',
        'crawledAs' => isset($status['indexStatusResult']['crawledAs']) ? $status['indexStatusResult']['crawledAs'] : 'unknown',
        'referringUrls' => isset($status['indexStatusResult']['referringUrls']) ? $status['indexStatusResult']['referringUrls'] : array(),
        'sitemap' => isset($status['indexStatusResult']['sitemap']) ? $status['indexStatusResult']['sitemap'] : array()
    );
    
    // Zapisz w cache
    IndexFixer_Cache::set_url_status($url, $detailed_status);
    
    IndexFixer_Logger::log("✅ Sprawdzono URL: $url - Verdict: {$detailed_status['verdict']}, Coverage: {$detailed_status['coverageState']}", 'success');
    
    wp_send_json_success(array(
        'url' => $url,
        'status' => $detailed_status,
        'logs' => IndexFixer_Logger::format_logs()
    ));
}

// Aktywacja wtyczki
function indexfixer_activate() {
    if (!wp_next_scheduled('indexfixer_check_urls_event')) {
        wp_schedule_event(time(), 'hourly', 'indexfixer_check_urls_event');
    }
}

// Deaktywacja wtyczki
function indexfixer_deactivate() {
    wp_clear_scheduled_hook('indexfixer_check_urls_event');
}

// Dodanie własnego interwału
function indexfixer_add_cron_interval($schedules) {
    $schedules['six_hours'] = array(
        'interval' => 21600, // 6 godzin w sekundach
        'display' => 'Co 6 godzin'
    );
    return $schedules;
}
add_filter('cron_schedules', 'indexfixer_add_cron_interval');

// Funkcja sprawdzająca URL-e
function indexfixer_check_urls() {
    // Sprawdź czy proces już się wykonuje
    if (get_transient('indexfixer_process_running')) {
        IndexFixer_Logger::log('PROCES JEST JUŻ URUCHOMIONY - pomijam', 'warning');
        return;
    }
    
    // Ustaw flagę że proces się wykonuje
    set_transient('indexfixer_process_running', true, 3600); // 1 godzina
    
    IndexFixer_Logger::log('=== ROZPOCZĘCIE PEŁNEGO PROCESU SPRAWDZANIA ===', 'info');
    $start_time = time();
    
    // Wyczyść cache ze starymi wpisami (tablicami) - wykonaj tylko raz
    if (!get_transient('indexfixer_cache_cleaned')) {
        IndexFixer_Logger::log('🧹 Czyszczę stary cache z tablicami...', 'info');
        IndexFixer_Cache::clear_all_cache();
        set_transient('indexfixer_cache_cleaned', true, WEEK_IN_SECONDS);
        IndexFixer_Logger::log('✅ Cache wyczyszczony - nowe statusy będą stringami', 'success');
    }
    
    // Konfigurowalny limit URL-i do sprawdzenia
    $url_limit = apply_filters('indexfixer_url_limit', INDEXFIXER_URL_LIMIT); // Domyślnie 500, można zmienić przez filter
    
    $all_urls = IndexFixer_Fetch_URLs::get_all_urls();
    $urls = array_slice($all_urls, 0, $url_limit);
    $total_urls = count($urls);
    $total_all_urls = count($all_urls);
    IndexFixer_Logger::log(sprintf('🎯 ZNALEZIONO ŁĄCZNIE: %d URL-i (ograniczono do %d z %d)', $total_all_urls, $total_urls, $total_all_urls), 'info');
    
    if ($total_urls === 0) {
        IndexFixer_Logger::log('❌ Brak URL-i do sprawdzenia - kończę proces', 'warning');
        delete_transient('indexfixer_process_running');
        return;
    }
    
    $gsc_api = new IndexFixer_GSC_API();
    $checked = 0;
    $errors = 0;
    $skipped = 0;
    
    foreach ($urls as $index => $url_data) {
        $current_position = $index + 1;
        $progress_percent = round(($current_position / $total_urls) * 100, 1);
        
        IndexFixer_Logger::log(sprintf('📊 POSTĘP: %d/%d (%s%%) - sprawdzam: %s', 
            $current_position, 
            $total_urls, 
            $progress_percent,
            $url_data['url']
        ), 'info');
        
        // Sprawdź czy URL już ma cache
        $cached_status = IndexFixer_Cache::get_url_status($url_data['url']);
        if ($cached_status !== false) {
            IndexFixer_Logger::log(sprintf('💾 URL już w cache - pomijam: %s', $url_data['url']), 'info');
            $skipped++;
            continue;
        }
        
        try {
            $status = $gsc_api->check_url_status($url_data['url']);
            if ($status !== false) {
                // Przygotuj szczegółowe dane do zapisu w cache
                $detailed_status = array(
                    'verdict' => isset($status['indexStatusResult']['verdict']) ? $status['indexStatusResult']['verdict'] : 'unknown',
                    'coverageState' => isset($status['indexStatusResult']['coverageState']) ? $status['indexStatusResult']['coverageState'] : 'unknown',
                    'robotsTxtState' => isset($status['indexStatusResult']['robotsTxtState']) ? $status['indexStatusResult']['robotsTxtState'] : 'unknown',
                    'indexingState' => isset($status['indexStatusResult']['indexingState']) ? $status['indexStatusResult']['indexingState'] : 'unknown',
                    'pageFetchState' => isset($status['indexStatusResult']['pageFetchState']) ? $status['indexStatusResult']['pageFetchState'] : 'unknown',
                    'lastCrawlTime' => isset($status['indexStatusResult']['lastCrawlTime']) ? $status['indexStatusResult']['lastCrawlTime'] : 'unknown',
                    'crawledAs' => isset($status['indexStatusResult']['crawledAs']) ? $status['indexStatusResult']['crawledAs'] : 'unknown',
                    'referringUrls' => isset($status['indexStatusResult']['referringUrls']) ? $status['indexStatusResult']['referringUrls'] : array(),
                    'sitemap' => isset($status['indexStatusResult']['sitemap']) ? $status['indexStatusResult']['sitemap'] : array()
                );
                
                // Dodaj prosty status dla backward compatibility
                if (isset($status['indexStatusResult']['coverageState'])) {
                    $coverage_state = $status['indexStatusResult']['coverageState'];
                    switch($coverage_state) {
                        case 'Submitted and indexed':
                            $detailed_status['simple_status'] = 'INDEXED';
                            break;
                        case 'Crawled - currently not indexed':
                            $detailed_status['simple_status'] = 'NOT_INDEXED';
                            break;
                        case 'Discovered - currently not indexed':
                            $detailed_status['simple_status'] = 'PENDING';
                            break;
                        default:
                            $detailed_status['simple_status'] = 'OTHER';
                    }
                } else {
                    $detailed_status['simple_status'] = 'unknown';
                }
                
                IndexFixer_Cache::set_url_status($url_data['url'], $detailed_status);
                
                // NOWE: Zapisz również w tabeli bazy danych
                $post_id = url_to_postid($url_data['url']);
                if (!$post_id) {
                    // Spróbuj znaleźć post_id na podstawie permalink
                    $path = parse_url($url_data['url'], PHP_URL_PATH);
                    if ($path) {
                        global $wpdb;
                        $post_id = $wpdb->get_var($wpdb->prepare(
                            "SELECT ID FROM {$wpdb->posts} 
                             WHERE post_name = %s 
                             AND post_status = 'publish'
                             LIMIT 1",
                            basename(rtrim($path, '/'))
                        ));
                    }
                }
                // Zapisz zawsze - nawet bez post_id (użyj 0)
                IndexFixer_Database::save_url_status($post_id ?: 0, $url_data['url'], $detailed_status);
                
                $index_status = $detailed_status['simple_status'];
                IndexFixer_Logger::log(
                    sprintf('✅ SUKCES [%d/%d]: %s - Status: %s', $current_position, $total_urls, $url_data['url'], $index_status),
                    'success'
                );
                $checked++;
            } else {
                IndexFixer_Logger::log(
                    sprintf('❌ BŁĄD [%d/%d]: Nie udało się sprawdzić %s', $current_position, $total_urls, $url_data['url']),
                    'error'
                );
                $errors++;
            }
        } catch (Exception $e) {
            IndexFixer_Logger::log(
                sprintf('💥 WYJĄTEK [%d/%d]: %s - %s', $current_position, $total_urls, $url_data['url'], $e->getMessage()),
                'error'
            );
            $errors++;
        }
        
        // Dodaj opóźnienie między URL-ami żeby nie przeciążyć API
        if ($current_position < $total_urls) {
            IndexFixer_Logger::log('⏳ Czekam 3 sekundy przed następnym URL...', 'info');
            sleep(3);
        }
    }
    
    $end_time = time();
    $duration_minutes = round(($end_time - $start_time) / 60, 1);
    
    IndexFixer_Logger::log('=== ZAKOŃCZENIE PROCESU SPRAWDZANIA ===', 'info');
    IndexFixer_Logger::log(sprintf('🎯 PODSUMOWANIE:'), 'info');
    IndexFixer_Logger::log(sprintf('   • Łącznie URL-i: %d', $total_urls), 'info');
    IndexFixer_Logger::log(sprintf('   • Sprawdzono nowych: %d', $checked), 'info');
    IndexFixer_Logger::log(sprintf('   • Pominięto (cache): %d', $skipped), 'info');
    IndexFixer_Logger::log(sprintf('   • Błędy: %d', $errors), 'info');
    IndexFixer_Logger::log(sprintf('   • Czas trwania: %s minut', $duration_minutes), 'info');
    
    if ($errors > 0) {
        IndexFixer_Logger::log(sprintf('⚠️  UWAGA: Wystąpiły błędy przy %d URL-ach', $errors), 'warning');
    }
    
    if ($checked > 0) {
        IndexFixer_Logger::log(sprintf('🎉 SUKCES: Pomyślnie sprawdzono %d nowych URL-i', $checked), 'success');
    }
    
    if ($checked === 0 && $skipped > 0) {
        IndexFixer_Logger::log('ℹ️  INFORMACJA: Wszystkie URL-e były już w cache - brak nowych do sprawdzenia', 'info');
    }
    
    // Zapisz czas ostatniego sprawdzenia dla dashboard widget
    update_option('indexfixer_last_check', time());
    
    // Usuń flagę że proces się wykonuje
    delete_transient('indexfixer_process_running');
    IndexFixer_Logger::log('🏁 PROCES ZAKOŃCZONY - można uruchomić ponownie', 'success');
}

// Główna klasa wtyczki
class IndexFixer {

    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_ajax_check_single_url', array($this, 'ajax_check_single_url'));
        
        // Hook dla migracji danych przy aktywacji
        add_action('admin_init', array($this, 'maybe_migrate_data'));
    }

    /**
     * Migruje dane z wp_options do nowej tabeli (uruchamiane raz)
     */
    public function maybe_migrate_data() {
        $migrated = get_option('indexfixer_data_migrated', false);
        
        if (!$migrated) {
            IndexFixer_Database::migrate_from_cache();
            update_option('indexfixer_data_migrated', true);
        }
    }
} 