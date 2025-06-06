<?php
/**
 * Dashboard administracyjny
 */

if (!defined('ABSPATH')) {
    exit;
}

class IndexFixer_Dashboard {
    private $auth_handler;
    
    /**
     * Inicjalizacja dashboardu
     */
    public function __construct() {
        $this->auth_handler = new IndexFixer_Auth_Handler();
        add_action('admin_menu', array($this, 'add_menu_pages'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_init', array($this, 'handle_auth_callback'));
        
        // AJAX endpoints
        add_action('wp_ajax_check_single_url', array($this, 'ajax_check_single_url'));
        add_action('wp_ajax_indexfixer_migrate_data', array($this, 'ajax_migrate_data'));
        add_action('wp_ajax_indexfixer_clear_cache', array($this, 'ajax_clear_cache'));
        add_action('wp_ajax_indexfixer_debug_cache', array($this, 'ajax_debug_cache'));
        add_action('wp_ajax_indexfixer_debug_database', array($this, 'ajax_debug_database'));
        add_action('wp_ajax_indexfixer_unlock_process', array($this, 'ajax_unlock_process'));
        add_action('wp_ajax_indexfixer_resume_checking', array($this, 'ajax_resume_checking'));
    }
    
    /**
     * Dodaje strony do menu WordPressa
     */
    public function add_menu_pages() {
        // Główna strona
        add_menu_page(
            'IndexFixer',
            'IndexFixer',
            'manage_options',
            'indexfixer',
            array($this, 'render_page'),
            'dashicons-search',
            30
        );
        
        // Podstrona konfiguracji
        add_submenu_page(
            'indexfixer',
            'Konfiguracja',
            'Konfiguracja',
            'manage_options',
            'indexfixer-settings',
            array($this, 'render_settings')
        );
        
        // Submenu dla zarządzania
        add_submenu_page(
            'indexfixer',
            'Zarządzanie Bazy Danych',
            '📊 Zarządzanie',
            'manage_options',
            'indexfixer-management',
            array($this, 'render_management_page')
        );
    }
    
    /**
     * Dodaje skrypty i style
     */
    public function enqueue_scripts($hook) {
        if (!in_array($hook, array('toplevel_page_indexfixer', 'indexfixer_page_indexfixer-settings', 'indexfixer_page_indexfixer-management'))) {
            return;
        }
        
        wp_enqueue_style(
            'indexfixer-admin',
            plugins_url('assets/css/admin.css', dirname(__FILE__)),
            array(),
            INDEXFIXER_VERSION
        );
        
        // Próbuj załadować Chart.js z CDN, jeśli nie uda się - pomiń
        wp_enqueue_script(
            'chart-js',
            'https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js',
            array(),
            '3.9.1',
            true
        );
        
        // Dodaj fallback script inline
        wp_add_inline_script('indexfixer-admin', '
            // Sprawdź czy Chart.js się załadował
            document.addEventListener("DOMContentLoaded", function() {
                if (typeof Chart === "undefined") {
                    console.warn("Chart.js nie załadował się z CDN");
                }
            });
        ');
        
        wp_enqueue_script(
            'indexfixer-admin',
            plugins_url('assets/js/admin.js', dirname(__FILE__)),
            array('jquery', 'chart-js'),
            INDEXFIXER_VERSION,
            true
        );
        
        // Dodaj dane dla AJAX
        wp_localize_script('indexfixer-admin', 'indexfixer', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('indexfixer_nonce'),
            'stats' => isset($stats) ? $stats : array()
        ));
    }
    
    /**
     * Obsługuje callback z autoryzacji
     */
    public function handle_auth_callback() {
        if (!isset($_GET['page']) || $_GET['page'] !== 'indexfixer') {
            return;
        }
        
        if (isset($_GET['code']) && isset($_GET['state'])) {
            if (wp_verify_nonce($_GET['state'], 'indexfixer_auth')) {
                if ($this->auth_handler->handle_auth_callback($_GET['code'])) {
                    wp_redirect(admin_url('admin.php?page=indexfixer&auth=success'));
                    exit;
                } else {
                    wp_redirect(admin_url('admin.php?page=indexfixer&auth=error'));
                    exit;
                }
            }
        }
        
        // Obsługa zapisywania ustawień
        if (isset($_POST['indexfixer_save_settings'])) {
            check_admin_referer('indexfixer_settings');
            
            $client_id = sanitize_text_field($_POST['indexfixer_client_id']);
            $client_secret = sanitize_text_field($_POST['indexfixer_client_secret']);
            
            $this->auth_handler->set_client_credentials($client_id, $client_secret);
            
            wp_redirect(admin_url('admin.php?page=indexfixer&settings=updated'));
            exit;
        }
    }
    
    /**
     * Renderuje stronę
     */
    public function render_page() {
        if (!IndexFixer_Helpers::can_manage_plugin()) {
            wp_die(__('Nie masz uprawnień do zarządzania tą wtyczką.', 'indexfixer'));
        }
        
        // Pobierz dane
        $urls = IndexFixer_Fetch_URLs::get_all_urls();
        $url_statuses = array();
        
        // Statystyki
        $stats = array(
            'total' => count($urls),
            'checked' => 0,
            'indexed' => 0,
            'not_indexed' => 0,
            'discovered' => 0,
            'excluded' => 0,
            'unknown' => 0,
            'pass' => 0,
            'neutral' => 0,
            'fail' => 0,
            'robots_allowed' => 0,
            'robots_disallowed' => 0
        );
        
        foreach ($urls as $url_data) {
            // NOWE: Najpierw spróbuj z tabeli bazy danych
            $status_data = IndexFixer_Database::get_url_status($url_data['url']);
            
            // Fallback do starych transientów jeśli brak w tabeli
            if (!$status_data) {
                $status_data = IndexFixer_Cache::get_url_status($url_data['url']);
            }
            
            $url_statuses[$url_data['url']] = $status_data;
            
            if ($status_data !== false) {
                $stats['checked']++;
                
                // Jeśli to stary format (string), przekonwertuj na nowy
                if (!is_array($status_data)) {
                    $status_data = array('simple_status' => $status_data);
                }
                
                // Coverage State
                if (isset($status_data['coverageState'])) {
                    switch($status_data['coverageState']) {
                        case 'Submitted and indexed':
                            $stats['indexed']++;
                            break;
                        case 'Crawled - currently not indexed':
                            $stats['not_indexed']++;
                            break;
                        case 'Discovered - currently not indexed':
                            $stats['discovered']++;
                            break;
                        case 'Page with redirect':
                        case 'Excluded by robots.txt':
                        case 'Blocked due to unauthorized request (401)':
                        case 'Not found (404)':
                            $stats['excluded']++;
                            break;
                        default:
                            $stats['unknown']++;
                    }
                }
                
                // Verdict
                if (isset($status_data['verdict'])) {
                    switch(strtolower($status_data['verdict'])) {
                        case 'pass':
                            $stats['pass']++;
                            break;
                        case 'neutral':
                            $stats['neutral']++;
                            break;
                        case 'fail':
                            $stats['fail']++;
                            break;
                    }
                }
                
                // Robots.txt
                if (isset($status_data['robotsTxtState'])) {
                    if ($status_data['robotsTxtState'] === 'ALLOWED') {
                        $stats['robots_allowed']++;
                    } else {
                        $stats['robots_disallowed']++;
                    }
                }
            } else {
                $stats['unknown']++;
            }
        }
        
        // Wyświetl komunikaty
        if (isset($_GET['auth']) && $_GET['auth'] === 'success') {
            echo '<div class="notice notice-success"><p>Autoryzacja zakończona sukcesem!</p></div>';
        } elseif (isset($_GET['auth']) && $_GET['auth'] === 'error') {
            echo '<div class="notice notice-error"><p>Wystąpił błąd podczas autoryzacji.</p></div>';
        }
        
        if (isset($_GET['settings']) && $_GET['settings'] === 'updated') {
            echo '<div class="notice notice-success"><p>Ustawienia zostały zaktualizowane.</p></div>';
        }
        
        // Wyświetl formularz ustawień jeśli nie ma autoryzacji
        if (!$this->auth_handler->is_authorized()) {
            ?>
            <div class="wrap">
                <h1>IndexFixer - Konfiguracja</h1>
                
                <div class="indexfixer-settings">
                    <p>
                        <strong>URI przekierowania do skonfigurowania w Google Cloud Console:</strong><br>
                        <code><?php echo esc_url(admin_url('admin.php?page=indexfixer&action=auth_callback')); ?></code>
                    </p>
                    <hr>
                    <form method="post" action="">
                        <?php wp_nonce_field('indexfixer_settings'); ?>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="indexfixer_client_id">Client ID</label>
                                </th>
                                <td>
                                    <input type="text" 
                                           id="indexfixer_client_id" 
                                           name="indexfixer_client_id" 
                                           value="<?php echo esc_attr($this->auth_handler->get_client_id()); ?>" 
                                           class="regular-text">
                                    <p class="description">
                                        Client ID z Google Cloud Console. 
                                        <a href="https://console.cloud.google.com/apis/credentials" target="_blank">
                                            Przejdź do Google Cloud Console
                                        </a>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="indexfixer_client_secret">Client Secret</label>
                                </th>
                                <td>
                                    <input type="text" 
                                           id="indexfixer_client_secret" 
                                           name="indexfixer_client_secret" 
                                           value="<?php echo esc_attr($this->auth_handler->get_client_secret()); ?>" 
                                           class="regular-text">
                                    <p class="description">
                                        Client Secret z Google Cloud Console
                                    </p>
                                </td>
                            </tr>
                        </table>
                        
                        <p class="submit">
                            <input type="submit" 
                                   name="indexfixer_save_settings" 
                                   class="button button-primary" 
                                   value="Zapisz ustawienia">
                        </p>
                    </form>
                    
                    <?php if ($this->auth_handler->get_client_id() && $this->auth_handler->get_client_secret()): ?>
                        <hr>
                        <p>
                            <strong>URI przekierowania do skonfigurowania w Google Cloud Console:</strong><br>
                            <code><?php echo esc_url(admin_url('admin.php?page=indexfixer&action=auth_callback')); ?></code>
                        </p>
                        <p>
                            <a href="<?php echo esc_url($this->auth_handler->get_auth_url()); ?>" 
                               class="button button-primary">
                                Zaloguj się przez Google
                            </a>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
            <?php
            return;
        }
        
        // Dodaj style inline jako backup jeśli CSS się nie załadował
        echo '<style>
        .verdict-pass { color: #46b450; font-weight: bold; }
        .verdict-neutral { color: #0073aa; font-weight: bold; }
        .verdict-fail { color: #dc3232; font-weight: bold; }
        .good { color: #46b450; }
        .bad { color: #dc3232; }
        .status-unknown { color: #999; font-style: italic; }
        .wp-list-table th { cursor: pointer; }
        .wp-list-table th:hover { background-color: #f0f0f1; }
        .wp-list-table th.sorted-asc::after { content: " ↑"; color: #0073aa; }
        .wp-list-table th.sorted-desc::after { content: " ↓"; color: #0073aa; }
        .indexfixer-filters select { margin-right: 10px; min-width: 150px; }
        </style>';
        
        // Upewnij się że skrypty są załadowane
        wp_enqueue_script('indexfixer-admin');
        
        // Przekaż statystyki do JS
        wp_localize_script('indexfixer-admin', 'indexfixer_stats', $stats);
        
        // Wyświetl dashboard
        include INDEXFIXER_PLUGIN_DIR . 'templates/dashboard.php';
    }
    
    /**
     * Renderuje stronę konfiguracji
     */
    public function render_settings() {
        if (!IndexFixer_Helpers::can_manage_plugin()) {
            wp_die(__('Nie masz uprawnień do zarządzania tą wtyczką.', 'indexfixer'));
        }
        
        $auth_handler = new IndexFixer_Auth_Handler();
        
        // Obsługa formularza
        if (isset($_POST['indexfixer_settings_nonce']) && 
            wp_verify_nonce($_POST['indexfixer_settings_nonce'], 'indexfixer_settings')) {
            
            $client_id = sanitize_text_field($_POST['client_id']);
            $client_secret = sanitize_text_field($_POST['client_secret']);
            
            $auth_handler->set_client_credentials($client_id, $client_secret);
            
            echo IndexFixer_Helpers::success_message('Ustawienia zostały zapisane.');
        }
        
        // Pobierz aktualne ustawienia
        $client_id = $auth_handler->get_client_id();
        $client_secret = $auth_handler->get_client_secret();
        
        // Wyświetl formularz
        ?>
        <div class="wrap">
            <h1>Konfiguracja IndexFixer</h1>
            
            <form method="post" action="">
                <?php wp_nonce_field('indexfixer_settings', 'indexfixer_settings_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="client_id">Client ID</label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="client_id" 
                                   name="client_id" 
                                   value="<?php echo esc_attr($client_id); ?>" 
                                   class="regular-text">
                            <p class="description">
                                Client ID z Google Cloud Console
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="client_secret">Client Secret</label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="client_secret" 
                                   name="client_secret" 
                                   value="<?php echo esc_attr($client_secret); ?>" 
                                   class="regular-text">
                            <p class="description">
                                Client Secret z Google Cloud Console
                            </p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button('Zapisz ustawienia'); ?>
            </form>
            
            <?php if (!empty($client_id) && !empty($client_secret)): ?>
                <hr>
                <h2>Autoryzacja</h2>
                <p>
                    Kliknij poniższy przycisk, aby autoryzować dostęp do Google Search Console:
                </p>
                <a href="<?php echo esc_url($auth_handler->get_auth_url()); ?>" class="button button-primary">
                    Autoryzuj dostęp
                </a>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Pobiera buforowane statusy URL-ów z wp_options lub z bazy danych
     */
    private function get_cached_urls() {
        $cached_statuses = array();
        
        // NAJPIERW spróbuj załadować z tabeli bazy danych (priorytet)
        global $wpdb;
        $table_name = IndexFixer_Database::get_table_name();
        
        $sql = "SELECT url, status, verdict, coverage_state, robots_txt_state, indexing_state, 
                page_fetch_state, crawled_as, last_crawl_time, last_checked, 
                last_status_change, check_count 
         FROM $table_name 
         ORDER BY last_checked DESC 
         LIMIT " . INDEXFIXER_URL_LIMIT;
        
        IndexFixer_Logger::log("SQL zapytanie: $sql", 'debug');
        IndexFixer_Logger::log("Nazwa tabeli: $table_name", 'debug');
        
        $results = $wpdb->get_results($sql);
        
        IndexFixer_Logger::log("Wyników z bazy: " . ($results ? count($results) : '0'), 'debug');
        
        if ($wpdb->last_error) {
            IndexFixer_Logger::log("Błąd SQL: " . $wpdb->last_error, 'error');
        }
        
        if ($results) {
            foreach ($results as $row) {
                $cached_statuses[$row->url] = array(
                    'simple_status' => $row->status,
                    'verdict' => $row->verdict,
                    'coverageState' => $row->coverage_state,
                    'robotsTxtState' => $row->robots_txt_state,
                    'indexingState' => $row->indexing_state,
                    'pageFetchState' => $row->page_fetch_state,
                    'crawledAs' => $row->crawled_as,
                    'lastCrawlTime' => $row->last_crawl_time,
                    'lastChecked' => $row->last_checked,
                    'lastStatusChange' => $row->last_status_change,
                    'checkCount' => $row->check_count
                );
            }
            IndexFixer_Logger::log('Załadowano ' . count($cached_statuses) . ' URL-ów z tabeli bazy danych', 'info');
        } else {
            // Fallback do wp_options jeśli tabela jest pusta
            IndexFixer_Logger::log('Tabela pusta, sprawdzam wp_options jako fallback...', 'info');
            $cached_statuses = get_option('indexfixer_url_statuses', array());
            
            if (!empty($cached_statuses)) {
                IndexFixer_Logger::log('Załadowano ' . count($cached_statuses) . ' URL-ów z wp_options (fallback)', 'info');
            } else {
                IndexFixer_Logger::log('Brak danych w tabeli i wp_options', 'warning');
            }
        }
        
        return $cached_statuses;
    }
    
    /**
     * AJAX sprawdzanie pojedynczego URL-a
     */
    public function ajax_check_single_url() {
        check_ajax_referer('indexfixer_check_url', 'nonce');
        
        $url = sanitize_url($_POST['url']);
        
        if (empty($url)) {
            wp_die('Invalid URL');
        }
        
        $gsc_api = new IndexFixer_GSC_API();
        $status = $gsc_api->check_url_status($url);
        
        if (isset($status['error'])) {
            wp_send_json_error($status['error']);
        } else {
            // Zapisz w wp_options (kompatybilność wsteczna)
            $cached_statuses = get_option('indexfixer_url_statuses', array());
            $cached_statuses[$url] = $status;
            update_option('indexfixer_url_statuses', $cached_statuses);
            
            // NOWE: Zapisz również w tabeli bazy danych
            $post_id = url_to_postid($url);
            // Zapisz nawet bez post_id (użyj 0)
            IndexFixer_Database::save_url_status($post_id ?: 0, $url, $status);
            
            if ($post_id) {
                IndexFixer_Logger::log("Zaktualizowano status URL w tabeli (post_id: $post_id): $url", 'info');
            } else {
                IndexFixer_Logger::log("Zaktualizowano status URL w tabeli (bez post_id): $url", 'info');
            }
            
            wp_send_json_success($status);
        }
        
        wp_die();
    }
    
    /**
     * Renderuje stronę zarządzania
     */
    public function render_management_page() {
        include INDEXFIXER_PLUGIN_DIR . 'templates/widget-settings.php';
    }
    
    /**
     * AJAX migracja danych z wp_options do tabeli
     */
    public function ajax_migrate_data() {
        check_ajax_referer('indexfixer_migrate', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Brak uprawnień');
        }
        
        try {
            IndexFixer_Database::migrate_from_cache();
            wp_send_json_success(array('message' => 'Migracja danych zakończona pomyślnie'));
        } catch (Exception $e) {
            wp_send_json_error('Błąd migracji: ' . $e->getMessage());
        }
    }
    
    /**
     * AJAX czyszczenie cache wp_options
     */
    public function ajax_clear_cache() {
        check_ajax_referer('indexfixer_clear', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Brak uprawnień');
        }
        
        try {
            delete_option('indexfixer_url_statuses');
            wp_send_json_success(array('message' => 'Cache wp_options został wyczyszczony'));
        } catch (Exception $e) {
            wp_send_json_error('Błąd czyszczenia: ' . $e->getMessage());
        }
    }
    
    /**
     * AJAX debug cache wp_options
     */
    public function ajax_debug_cache() {
        check_ajax_referer('indexfixer_debug', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Brak uprawnień');
        }
        
        // Sprawdź wszystkie opcje IndexFixer
        global $wpdb;
        $options = $wpdb->get_results(
            "SELECT option_name, CHAR_LENGTH(option_value) as value_length 
             FROM {$wpdb->options} 
             WHERE option_name LIKE '%indexfixer%' 
             ORDER BY option_name"
        );
        
        $debug_info = array();
        foreach ($options as $option) {
            $value = get_option($option->option_name);
            $debug_info[$option->option_name] = array(
                'length' => $option->value_length,
                'type' => gettype($value),
                'count' => is_array($value) ? count($value) : 'N/A',
                'sample' => is_array($value) && !empty($value) ? array_keys(array_slice($value, 0, 3, true)) : 'N/A'
            );
        }
        
        wp_send_json_success($debug_info);
    }
    
    /**
     * AJAX debug tabeli bazy danych
     */
    public function ajax_debug_database() {
        check_ajax_referer('indexfixer_debug_db', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Brak uprawnień');
        }
        
        global $wpdb;
        $table_name = IndexFixer_Database::get_table_name();
        
        // Sprawdź czy tabela istnieje
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
        
        if (!$table_exists) {
            wp_send_json_error('Tabela nie istnieje');
        }
        
        // Policz rekordy
        $total_records = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        
        // Pobierz przykładowe rekordy
        $sample_records = $wpdb->get_results(
            "SELECT url, status, verdict, coverage_state, last_checked 
             FROM $table_name 
             ORDER BY last_checked DESC 
             LIMIT 5", 
            ARRAY_A
        );
        
        $debug_info = array(
            'total_records' => $total_records,
            'sample_records' => $sample_records
        );
        
        wp_send_json_success($debug_info);
    }
    
    /**
     * AJAX odblokowanie procesu sprawdzania
     */
    public function ajax_unlock_process() {
        check_ajax_referer('indexfixer_unlock', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Brak uprawnień');
        }
        
        // Sprawdź czy proces jest zablokowany
        $process_running = get_transient('indexfixer_process_running');
        
        if (!$process_running) {
            wp_send_json_success(array('message' => 'Proces już był odblokowany'));
        }
        
        // Usuń flagę blokady
        delete_transient('indexfixer_process_running');
        
        // Sprawdź czy się udało
        $still_blocked = get_transient('indexfixer_process_running');
        
        if ($still_blocked) {
            wp_send_json_error('Nie udało się odblokować procesu');
        } else {
            IndexFixer_Logger::log('Proces sprawdzania został ręcznie odblokowany przez administratora', 'info');
            wp_send_json_success(array('message' => 'Proces został pomyślnie odblokowany. Możesz teraz uruchomić sprawdzanie.'));
        }
    }
    
    /**
     * AJAX wznowienie sprawdzania URL-ów bez danych
     */
    public function ajax_resume_checking() {
        check_ajax_referer('indexfixer_resume', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Brak uprawnień');
        }
        
        // Sprawdź czy proces już jest uruchomiony
        $process_running = get_transient('indexfixer_process_running');
        if ($process_running) {
            wp_send_json_error('Proces sprawdzania jest już uruchomiony. Użyj "Odblokuj Proces" jeśli się zawiesił.');
        }
        
        // Znajdź URL-e bez danych API
        $urls_to_check = $this->get_unchecked_urls();
        
        if (empty($urls_to_check)) {
            wp_send_json_success(array(
                'message' => 'Wszystkie URL-e mają już dane z API - brak do sprawdzenia',
                'details' => 'Znalezione: 0 URL-ów do sprawdzenia'
            ));
        }
        
        // Ustaw flagę procesu
        set_transient('indexfixer_process_running', true, 30 * MINUTE_IN_SECONDS);
        
        // Uruchom sprawdzanie w tle
        $this->start_resume_checking($urls_to_check);
        
        $count = count($urls_to_check);
        IndexFixer_Logger::log("Wznowienie sprawdzania: znaleziono $count URL-ów bez danych API", 'info');
        
        wp_send_json_success(array(
            'message' => "Wznowiono sprawdzanie - znaleziono $count URL-ów bez danych API",
            'details' => "Sprawdzanie zostało uruchomione w tle. Sprawdź logi po chwili."
        ));
    }
    
    /**
     * Znajduje URL-e które nie mają jeszcze danych z API
     */
    private function get_unchecked_urls() {
        $all_urls = IndexFixer_Fetch_URLs::get_all_urls();
        $unchecked = array();
        
        foreach ($all_urls as $url_data) {
            // Sprawdź czy URL ma dane w tabeli
            $db_status = IndexFixer_Database::get_url_status($url_data['url']);
            
            // Jeśli nie ma danych w tabeli lub ma status "unknown", dodaj do sprawdzenia
            if (!$db_status || 
                (is_array($db_status) && isset($db_status['status']) && $db_status['status'] === 'unknown') ||
                (is_array($db_status) && isset($db_status['verdict']) && $db_status['verdict'] === 'unknown')) {
                $unchecked[] = $url_data;
            }
        }
        
        return $unchecked;
    }
    
    /**
     * Uruchamia sprawdzanie wybranych URL-ów w tle
     */
    private function start_resume_checking($urls_to_check) {
        if (empty($urls_to_check)) {
            return;
        }
        
        IndexFixer_Logger::log('=== WZNOWIENIE SPRAWDZANIA URL-ÓW ===', 'info');
        $start_time = time();
        
        $gsc_api = new IndexFixer_GSC_API();
        $checked = 0;
        $errors = 0;
        $total_urls = count($urls_to_check);
        
        foreach ($urls_to_check as $index => $url_data) {
            $current_position = $index + 1;
            $progress_percent = round(($current_position / $total_urls) * 100, 1);
            
            IndexFixer_Logger::log(sprintf('📊 WZNOWIENIE [%d/%d] (%s%%): %s', 
                $current_position, 
                $total_urls, 
                $progress_percent,
                $url_data['url']
            ), 'info');
            
            try {
                $status = $gsc_api->check_url_status($url_data['url']);
                if ($status !== false) {
                    // Przygotuj szczegółowe dane do zapisu w formacie kompatybilnym z bazą
                    $detailed_status = array(
                        'verdict' => isset($status['indexStatusResult']['verdict']) ? $status['indexStatusResult']['verdict'] : 'unknown',
                        'coverageState' => isset($status['indexStatusResult']['coverageState']) ? $status['indexStatusResult']['coverageState'] : 'unknown',
                        'robotsTxtState' => isset($status['indexStatusResult']['robotsTxtState']) ? $status['indexStatusResult']['robotsTxtState'] : 'unknown',
                        'indexingState' => isset($status['indexStatusResult']['indexingState']) ? $status['indexStatusResult']['indexingState'] : 'unknown',
                        'pageFetchState' => isset($status['indexStatusResult']['pageFetchState']) ? $status['indexStatusResult']['pageFetchState'] : 'unknown',
                        'lastCrawlTime' => isset($status['indexStatusResult']['lastCrawlTime']) ? $status['indexStatusResult']['lastCrawlTime'] : 'unknown',
                        'crawledAs' => isset($status['indexStatusResult']['crawledAs']) ? $status['indexStatusResult']['crawledAs'] : 'unknown'
                    );
                    
                    // Dodaj prosty status na podstawie coverage state
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
                    
                    // Zapisz w wp_options (kompatybilność wsteczna) - surowe dane
                    $cached_statuses = get_option('indexfixer_url_statuses', array());
                    $cached_statuses[$url_data['url']] = $status;
                    update_option('indexfixer_url_statuses', $cached_statuses);
                    
                    // Zapisz w tabeli bazy danych - przetworzone dane
                    $post_id = url_to_postid($url_data['url']) ?: 0;
                    IndexFixer_Database::save_url_status($post_id, $url_data['url'], $detailed_status);
                    
                    IndexFixer_Logger::log("✅ SPRAWDZONO: {$url_data['url']} - Status: {$detailed_status['simple_status']} (Verdict: {$detailed_status['verdict']}, Coverage: {$detailed_status['coverageState']})", 'success');
                    $checked++;
                } else {
                    IndexFixer_Logger::log("❌ BŁĄD: Nie udało się sprawdzić {$url_data['url']}", 'error');
                    $errors++;
                }
            } catch (Exception $e) {
                IndexFixer_Logger::log("💥 WYJĄTEK: {$url_data['url']} - {$e->getMessage()}", 'error');
                $errors++;
            }
            
            // Rate limiting
            if ($current_position < $total_urls) {
                sleep(3);
            }
        }
        
        $end_time = time();
        $duration_minutes = round(($end_time - $start_time) / 60, 1);
        
        IndexFixer_Logger::log('=== ZAKOŃCZENIE WZNOWIENIA ===', 'info');
        IndexFixer_Logger::log("🎯 PODSUMOWANIE WZNOWIENIA:", 'info');
        IndexFixer_Logger::log("   • Sprawdzono: $checked", 'info');
        IndexFixer_Logger::log("   • Błędy: $errors", 'info');
        IndexFixer_Logger::log("   • Czas: $duration_minutes minut", 'info');
        
        // Zapisz czas ostatniego sprawdzenia
        update_option('indexfixer_last_check', time());
        
        // Usuń flagę procesu
        delete_transient('indexfixer_process_running');
        IndexFixer_Logger::log('🏁 WZNOWIENIE ZAKOŃCZONE', 'success');
    }
} 