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
        add_action('wp_ajax_indexfixer_save_daily_stats', array($this, 'ajax_save_daily_stats'));
        add_action('wp_ajax_indexfixer_clear_logs', array($this, 'ajax_clear_logs'));
        
        // NOWE: AJAX dla zarządzania schedulerem widgetów
        add_action('wp_ajax_indexfixer_enable_test_mode', array($this, 'ajax_enable_test_mode'));
        add_action('wp_ajax_indexfixer_disable_test_mode', array($this, 'ajax_disable_test_mode'));
        add_action('wp_ajax_indexfixer_run_manual_check', array($this, 'ajax_run_manual_check'));
        add_action('wp_ajax_indexfixer_get_schedule_status', array($this, 'ajax_get_schedule_status'));
        add_action('wp_ajax_indexfixer_save_today_stats', array($this, 'ajax_save_today_stats'));
        add_action('wp_ajax_indexfixer_force_full_refresh', array($this, 'ajax_force_full_refresh'));
        add_action('wp_ajax_indexfixer_test_refresh_token', array($this, 'ajax_test_refresh_token'));
        add_action('wp_ajax_indexfixer_test_updater', array($this, 'ajax_test_updater'));
        add_action('wp_ajax_indexfixer_schedule_token_cron', array($this, 'ajax_schedule_token_cron'));
        add_action('wp_ajax_indexfixer_force_rebuild_widget_schedule', array($this, 'ajax_force_rebuild_widget_schedule'));
        add_action('wp_ajax_indexfixer_test_stats_cron', array($this, 'ajax_test_stats_cron'));
        add_action('wp_ajax_indexfixer_manual_cleanup', array($this, 'ajax_manual_cleanup'));
        add_action('wp_ajax_indexfixer_recreate_tables', array($this, 'ajax_recreate_tables'));
        add_action('wp_ajax_indexfixer_remove_trailing_slash_urls', array($this, 'ajax_remove_trailing_slash_urls'));
        add_action('wp_ajax_indexfixer_rebuild_urls', array($this, 'ajax_rebuild_urls'));
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
        // ZAWSZE ładuj skrypt i nonce na wszystkich podstronach IndexFixer
        if (strpos($hook, 'indexfixer') === false) {
            return;
        }
        
        wp_enqueue_style(
            'indexfixer-admin',
            plugins_url('assets/css/admin.css', dirname(__FILE__)),
            array(),
            INDEXFIXER_VERSION
        );
        
        wp_enqueue_script(
            'chart-js',
            'https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js',
            array(),
            '3.9.1',
            true
        );
        
        wp_enqueue_script(
            'indexfixer-admin',
            plugins_url('assets/js/admin.js', dirname(__FILE__)),
            array('jquery', 'chart-js'),
            INDEXFIXER_VERSION,
            true
        );
        
        // ZAWSZE przekazuj aktualny nonce i ajaxurl
        wp_localize_script('indexfixer-admin', 'indexfixer', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('indexfixer_nonce'),
            'check_url_nonce' => wp_create_nonce('indexfixer_check_url')
        ));
        
        // Dodaj zmienną ajaxurl do globalnego scope
        wp_add_inline_script('indexfixer-admin', 'var ajaxurl = "' . admin_url('admin-ajax.php') . '";');
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
            
            // POPRAWKA: URL jest sprawdzony tylko jeśli ma wypełnione last_checked (faktycznie sprawdzony przez API)
            if ($status_data !== false && !empty($status_data['lastChecked'])) {
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
        
        // Pobierz historyczne statystyki dla template
        $historical_stats = IndexFixer_Database::get_historical_stats(30);
        $trend_stats = IndexFixer_Database::get_trend_stats();
        
        // Wyświetl dashboard
        include INDEXFIXER_PLUGIN_DIR . 'templates/dashboard.php';
    }
    
    /**
     * Renderuje sekcję monitorowania limitów API
     */
    private function render_quota_monitor() {
        $quota_monitor = IndexFixer_Quota_Monitor::get_instance();
        $stats = $quota_monitor->get_usage_stats();
        $history = $quota_monitor->get_usage_history(7);
        $estimation = $quota_monitor->estimate_quota_exhaustion();
        
        $status_class = '';
        $status_text = '';
        $status_icon = '';
        
        switch ($stats['status']) {
            case 'exceeded':
                $status_class = 'notice-error';
                $status_text = 'LIMIT PRZEKROCZONY';
                $status_icon = '🚫';
                break;
            case 'critical':
                $status_class = 'notice-error';
                $status_text = 'KRYTYCZNY';
                $status_icon = '🔴';
                break;
            case 'warning':
                $status_class = 'notice-warning';
                $status_text = 'OSTRZEŻENIE';
                $status_icon = '🟡';
                break;
            default:
                $status_class = 'notice-success';
                $status_text = 'OK';
                $status_icon = '✅';
                break;
        }
        ?>
        
        <div class="notice <?php echo $status_class; ?>" style="margin-bottom: 20px;">
            <h3><?php echo $status_icon; ?> Monitor Limitów API Google Search Console</h3>
            
            <div style="display: flex; gap: 30px; align-items: center; margin: 15px 0;">
                <div>
                    <strong>Status:</strong> <?php echo $status_icon . ' ' . $status_text; ?>
                </div>
                <div>
                    <strong>Dzisiaj:</strong> 
                    <?php echo $stats['daily']['used']; ?>/<?php echo $stats['daily']['limit']; ?> 
                    (<?php echo $stats['daily']['percentage']; ?>%)
                </div>
                <div>
                    <strong>Pozostało:</strong> <?php echo $stats['daily']['remaining']; ?> requestów
                </div>
                <div>
                    <strong>Ta minuta:</strong> 
                    <?php echo $stats['minute']['used']; ?>/<?php echo $stats['minute']['limit']; ?>
                </div>
            </div>
            
            <?php if ($stats['status'] !== 'ok'): ?>
                <div style="margin: 10px 0; padding: 10px; background: rgba(255,255,255,0.8); border-radius: 4px;">
                    <strong>⚠️ Uwaga:</strong> 
                    <?php if ($stats['status'] === 'exceeded'): ?>
                        Dzienny limit API został przekroczony. Sprawdzanie URL-ów zostało wstrzymane do jutra.
                    <?php elseif ($stats['status'] === 'critical'): ?>
                        Wykorzystano ponad 95% dziennego limitu. System może wkrótce wstrzymać sprawdzanie.
                    <?php else: ?>
                        Wykorzystano ponad 80% dziennego limitu. Monitoruj wykorzystanie.
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <div style="margin-top: 15px;">
                <strong>Prognoza:</strong> <?php echo esc_html($estimation); ?>
            </div>
            
            <?php if (!empty($history)): ?>
                <details style="margin-top: 15px;">
                    <summary style="cursor: pointer; font-weight: bold;">📊 Historia ostatnich 7 dni</summary>
                    <div style="margin-top: 10px;">
                        <table style="border-collapse: collapse; width: 100%; max-width: 600px;">
                            <thead>
                                <tr style="background: #f0f0f1;">
                                    <th style="padding: 8px; border: 1px solid #ddd; text-align: left;">Data</th>
                                    <th style="padding: 8px; border: 1px solid #ddd; text-align: right;">Requestów</th>
                                    <th style="padding: 8px; border: 1px solid #ddd; text-align: right;">% Limitu</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($history as $date => $count): ?>
                                    <?php 
                                    $percentage = round(($count / 2000) * 100, 1);
                                    $row_class = '';
                                    if ($percentage >= 95) $row_class = 'style="background: #ffebee;"';
                                    elseif ($percentage >= 80) $row_class = 'style="background: #fff3e0;"';
                                    ?>
                                    <tr <?php echo $row_class; ?>>
                                        <td style="padding: 8px; border: 1px solid #ddd;">
                                            <?php echo date('d.m.Y', strtotime($date)); ?>
                                            <?php if ($date === date('Y-m-d')): ?>
                                                <em>(dzisiaj)</em>
                                            <?php endif; ?>
                                        </td>
                                        <td style="padding: 8px; border: 1px solid #ddd; text-align: right;">
                                            <?php echo number_format($count); ?>
                                        </td>
                                        <td style="padding: 8px; border: 1px solid #ddd; text-align: right;">
                                            <?php echo $percentage; ?>%
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </details>
            <?php endif; ?>
            
            <div style="margin-top: 15px; font-size: 12px; color: #666;">
                <strong>Limity Google Search Console API:</strong> 
                2000 requestów/dzień • 600 requestów/minutę • 
                <a href="https://developers.google.com/webmaster-tools/limits" target="_blank">Dokumentacja</a>
            </div>
        </div>
        
        <?php
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
        // Debugowanie
        error_log('IndexFixer: Wywołano ajax_check_single_url');
        error_log('IndexFixer: POST data: ' . print_r($_POST, true));
        
        check_ajax_referer('indexfixer_check_url', 'nonce');
        
        if (!current_user_can('manage_options')) {
            error_log('IndexFixer: Brak uprawnień');
            wp_send_json_error('Brak uprawnień');
        }
        
        $url = sanitize_url($_POST['url']);
        
        if (empty($url)) {
            error_log('IndexFixer: Nieprawidłowy URL');
            wp_send_json_error('Nieprawidłowy URL');
        }
        
        error_log("IndexFixer: Sprawdzanie URL: $url");
        
        $gsc_api = new IndexFixer_GSC_API();
        $status = $gsc_api->check_url_status($url);
        
        if (isset($status['error'])) {
            error_log("IndexFixer: Błąd sprawdzania URL: {$status['error']}");
            wp_send_json_error($status['error']);
        } else {
            error_log('IndexFixer: Status URL: ' . print_r($status, true));
            
            // UJEDNOLICENIE: Przygotuj szczegółowe dane tak samo jak w głównej funkcji
            $detailed_status = array(
                'verdict' => isset($status['indexStatusResult']['verdict']) ? $status['indexStatusResult']['verdict'] : 'unknown',
                'coverageState' => isset($status['indexStatusResult']['coverageState']) ? $status['indexStatusResult']['coverageState'] : 'unknown',
                'robotsTxtState' => isset($status['indexStatusResult']['robotsTxtState']) ? $status['indexStatusResult']['robotsTxtState'] : 'unknown',
                'indexingState' => isset($status['indexStatusResult']['indexingState']) ? $status['indexStatusResult']['indexingState'] : 'unknown',
                'pageFetchState' => isset($status['indexStatusResult']['pageFetchState']) ? $status['indexStatusResult']['pageFetchState'] : 'unknown',
                'lastCrawlTime' => isset($status['indexStatusResult']['lastCrawlTime']) ? $status['indexStatusResult']['lastCrawlTime'] : 'unknown',
                'crawledAs' => isset($status['indexStatusResult']['crawledAs']) ? $status['indexStatusResult']['crawledAs'] : 'unknown',
                'referringUrls' => isset($status['indexStatusResult']['referringUrls']) ? $status['indexStatusResult']['referringUrls'] : array(),
                'lastChecked' => current_time('mysql')
            );
            
            // Znajdź post_id dla URL
            $post_id = url_to_postid($url);
            
            if (!$post_id) {
                // Spróbuj znaleźć post_id na podstawie permalink (tak samo jak główna funkcja)
                $path = parse_url($url, PHP_URL_PATH);
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
            
            error_log("IndexFixer: Post ID: $post_id");
            
            // Zapisz status w bazie danych
            IndexFixer_Database::save_url_status($post_id ?: 0, $url, $detailed_status);
            
            if ($post_id) {
                error_log("IndexFixer: Zaktualizowano status URL (post_id: $post_id): $url");
            } else {
                error_log("IndexFixer: Zaktualizowano status URL (bez post_id): $url");
            }
            
            // Zwróć ujednolicone dane
            wp_send_json_success(array(
                'url' => $url,
                'status' => $detailed_status,
                'raw_status' => $status // Dla kompatybilności z istniejącym JS
            ));
        }
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
        // Sprawdź uprawnienia i nonce
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Brak uprawnień');
        }
        
        check_ajax_referer('indexfixer_nonce', 'nonce');
        
        IndexFixer_Logger::log('🔄 Rozpoczynam migrację danych z wp_options do tabeli...', 'info');
        
        // Pobierz wszystkie URL-e ze strony
        $all_urls = IndexFixer_Fetch_URLs::get_all_urls();
        $migrated = 0;
        $skipped = 0;
        $errors = 0;
        
        foreach ($all_urls as $url_data) {
            try {
                // Sprawdź czy URL już jest w tabeli
                $existing = IndexFixer_Database::get_url_status($url_data['url']);
                if ($existing) {
                    $skipped++;
                    continue;
                }
                
                // Sprawdź czy URL ma dane w cache (stary system)
                $cached_status = IndexFixer_Cache::get_url_status($url_data['url']);
                if ($cached_status !== false) {
                    // Konwertuj stare dane na nowy format
                    $status_data = array(
                        'simple_status' => is_array($cached_status) && isset($cached_status['simple_status']) ? $cached_status['simple_status'] : $cached_status,
                        'verdict' => is_array($cached_status) && isset($cached_status['verdict']) ? $cached_status['verdict'] : null,
                        'coverageState' => is_array($cached_status) && isset($cached_status['coverageState']) ? $cached_status['coverageState'] : null,
                        'robotsTxtState' => is_array($cached_status) && isset($cached_status['robotsTxtState']) ? $cached_status['robotsTxtState'] : null,
                        'indexingState' => is_array($cached_status) && isset($cached_status['indexingState']) ? $cached_status['indexingState'] : null,
                        'pageFetchState' => is_array($cached_status) && isset($cached_status['pageFetchState']) ? $cached_status['pageFetchState'] : null,
                        'lastCrawlTime' => is_array($cached_status) && isset($cached_status['lastCrawlTime']) ? $cached_status['lastCrawlTime'] : null,
                        'crawledAs' => is_array($cached_status) && isset($cached_status['crawledAs']) ? $cached_status['crawledAs'] : null,
                    );
                    
                    // Znajdź post_id
                    $post_id = url_to_postid($url_data['url']);
                    
                    // Zapisz w nowej tabeli
                    if (IndexFixer_Database::save_url_status($post_id ?: 0, $url_data['url'], $status_data)) {
                        $migrated++;
                    } else {
                        $errors++;
                    }
                } else {
                    // Dodaj jako unknown dla przyszłego sprawdzenia
                    $post_id = url_to_postid($url_data['url']);
                    if (IndexFixer_Database::save_url_status($post_id ?: 0, $url_data['url'], array('simple_status' => 'unknown'))) {
                        $migrated++;
                    } else {
                        $errors++;
                    }
                }
                
            } catch (Exception $e) {
                IndexFixer_Logger::log("❌ Błąd migracji URL {$url_data['url']}: " . $e->getMessage(), 'error');
                $errors++;
            }
        }
        
        IndexFixer_Logger::log("✅ Migracja zakończona: migrated=$migrated, skipped=$skipped, errors=$errors", 'success');
        
        wp_send_json_success(array(
            'message' => "Migracja zakończona: $migrated zmigrowanych, $skipped pominiętych, $errors błędów",
            'migrated' => $migrated,
            'skipped' => $skipped,
            'errors' => $errors
        ));
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
            
            // POPRAWKA: URL jest niesprawdzony jeśli nie ma danych w tabeli LUB nie ma wypełnionego last_checked
            if (!$db_status || 
                empty($db_status['lastChecked']) ||
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
            
            // Inteligentne opóźnienie bazujące na limitach API
            if ($current_position < $total_urls) {
                $quota_monitor = IndexFixer_Quota_Monitor::get_instance();
                $stats = $quota_monitor->get_usage_stats();
                
                // Jeśli wykorzystaliśmy > 90% limitu minutowego (540/600), poczekaj do nowej minuty
                if ($stats['minute']['percentage'] > 90) {
                    $wait_time = 60 - (int)date('s'); // Czekaj do nowej minuty
                    IndexFixer_Logger::log(sprintf('⏳ Limit minutowy prawie wyczerpany (%d/600) - czekam %ds do nowej minuty', 
                        $stats['minute']['used'], $wait_time), 'info');
                    sleep($wait_time);
                } else {
                    // Krótkie opóźnienie 0.1s między requestami (bezpieczne dla API)
                    usleep(100000); // 0.1 sekundy = 100,000 mikrosekund
                }
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
    
    /**
     * AJAX ręczne zapisanie dzisiejszych statystyk
     */
    public function ajax_save_daily_stats() {
        check_ajax_referer('indexfixer_save_stats', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Brak uprawnień');
        }
        
        // Zapisz dzienne statystyki
        $result = IndexFixer_Database::save_daily_stats();
        
        if ($result) {
            $today = current_time('Y-m-d');
            IndexFixer_Logger::log("📊 Ręcznie zapisano statystyki dzienne dla $today", 'success');
            wp_send_json_success(array(
                'message' => "Statystyki dzienne zostały zapisane dla $today",
                'date' => $today
            ));
        } else {
            wp_send_json_error('Błąd podczas zapisywania statystyk dziennych');
        }
    }
    
    /**
     * AJAX czyszczenie logów
     */
    public function ajax_clear_logs() {
        check_ajax_referer('indexfixer_clear_logs', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Brak uprawnień');
        }
        
        // Wyczyść logi
        $result = IndexFixer_Logger::clear_logs();
        
        if ($result) {
            IndexFixer_Logger::log('🗑️ Logi zostały wyczyszczone przez administratora', 'info');
            wp_send_json_success(array(
                'message' => 'Logi zostały wyczyszczone'
            ));
        } else {
            wp_send_json_error('Błąd podczas czyszczenia logów');
        }
    }
    
    // NOWE: AJAX dla zarządzania schedulerem widgetów
    public function ajax_enable_test_mode() {
        check_ajax_referer('indexfixer_test_mode', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Brak uprawnień');
        }
        
        IndexFixer_Widget_Scheduler::enable_test_mode();
        
        wp_send_json_success(array(
            'message' => 'Tryb testowy włączony - sprawdzanie co 10 minut',
            'status' => IndexFixer_Widget_Scheduler::get_schedule_status()
        ));
    }
    
    public function ajax_disable_test_mode() {
        check_ajax_referer('indexfixer_test_mode', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Brak uprawnień');
        }
        
        IndexFixer_Widget_Scheduler::disable_test_mode();
        
        wp_send_json_success(array(
            'message' => 'Tryb testowy wyłączony - powrót do sprawdzania co 24h',
            'status' => IndexFixer_Widget_Scheduler::get_schedule_status()
        ));
    }
    
    public function ajax_run_manual_check() {
        check_ajax_referer('indexfixer_manual_check', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Brak uprawnień');
        }
        
        // Uruchom sprawdzanie w tle
        IndexFixer_Widget_Scheduler::run_manual_check();
        
        wp_send_json_success(array(
            'message' => 'Ręczne sprawdzanie zostało uruchomione - sprawdź logi',
            'logs' => IndexFixer_Logger::format_logs()
        ));
    }
    
    public function ajax_get_schedule_status() {
        check_ajax_referer('indexfixer_schedule_status', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Brak uprawnień');
        }
        
        $status = IndexFixer_Widget_Scheduler::get_schedule_status();
        
        wp_send_json_success(array(
            'status' => $status,
            'message' => $status['scheduled'] ? 
                "Następne sprawdzanie: {$status['next_run']} (interwał: {$status['interval']})" : 
                'Brak zaplanowanego sprawdzania'
        ));
    }
    
    /**
     * AJAX ręczne zapisanie dzisiejszych statystyk
     */
    public function ajax_save_today_stats() {
        check_ajax_referer('indexfixer_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Brak uprawnień');
        }
        
        $database = new IndexFixer_Database();
        $result = $database->save_today_stats();
        
        if ($result) {
            wp_send_json_success('Statystyki zostały zapisane');
        } else {
            wp_send_json_error('Błąd podczas zapisywania statystyk');
        }
    }
    
    /**
     * AJAX testowe odświeżanie tokenu Google
     */
    public function ajax_test_refresh_token() {
        check_ajax_referer('indexfixer_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Brak uprawnień');
        }
        
        IndexFixer_Logger::log('🧪 TESTOWE ODŚWIEŻANIE TOKENU - wywołane ręcznie', 'info');
        
        $auth_handler = new IndexFixer_Auth_Handler();
        
        // Sprawdź aktualny stan tokenu
        $token_info = $auth_handler->get_token_expiry_info();
        IndexFixer_Logger::log('📊 Stan tokenu przed odświeżaniem:', 'info');
        IndexFixer_Logger::log('   • Wygasa: ' . ($token_info['expires_at_formatted'] ?? 'brak danych'), 'info');
        IndexFixer_Logger::log('   • Za minut: ' . ($token_info['expires_in_minutes'] ?? 'brak danych'), 'info');
        IndexFixer_Logger::log('   • Wygasł: ' . ($token_info['is_expired'] ? 'TAK' : 'NIE'), 'info');
        
        // Spróbuj odświeżyć token
        $refresh_result = $auth_handler->refresh_access_token();
        
        if ($refresh_result) {
            // Sprawdź stan po odświeżeniu
            $new_token_info = $auth_handler->get_token_expiry_info();
            IndexFixer_Logger::log('✅ Token został odświeżony pomyślnie', 'success');
            IndexFixer_Logger::log('📊 Stan tokenu po odświeżaniu:', 'info');
            IndexFixer_Logger::log('   • Wygasa: ' . ($new_token_info['expires_at_formatted'] ?? 'brak danych'), 'info');
            IndexFixer_Logger::log('   • Za minut: ' . ($new_token_info['expires_in_minutes'] ?? 'brak danych'), 'info');
            
            wp_send_json_success(array(
                'message' => 'Token został pomyślnie odświeżony',
                'old_expiry' => $token_info['expires_at_formatted'] ?? 'brak danych',
                'new_expiry' => $new_token_info['expires_at_formatted'] ?? 'brak danych',
                'expires_in_minutes' => $new_token_info['expires_in_minutes'] ?? 'brak danych'
            ));
        } else {
            IndexFixer_Logger::log('❌ Nie udało się odświeżyć tokenu', 'error');
            wp_send_json_error('Nie udało się odświeżyć tokenu - sprawdź logi');
        }
    }
    
    /**
     * AJAX wymuś pełne odświeżenie (ignoruje cache)
     */
    public function ajax_force_full_refresh() {
        check_ajax_referer('indexfixer_force_refresh', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Brak uprawnień');
        }
        
        // Sprawdź czy proces nie jest już uruchomiony
        $process_running = get_transient('indexfixer_process_running');
        if ($process_running) {
            wp_send_json_error('Proces sprawdzania jest już uruchomiony');
        }
        
        IndexFixer_Logger::log('🔥 WYMUSZENIE PEŁNEGO ODŚWIEŻENIA - czyszczenie cache i uruchomienie sprawdzania', 'info');
        
        // KROK 1: Wyczyść cały cache
        IndexFixer_Cache::clear_all_cache();
        IndexFixer_Logger::log('🧹 Cache został wyczyszczony - wszystkie URL-e będą sprawdzone ponownie', 'success');
        
        // KROK 2: Uruchom sprawdzanie asynchronicznie
        wp_schedule_single_event(time(), 'indexfixer_check_urls_event');
        
        // KROK 3: Ustaw flagę procesu
        set_transient('indexfixer_process_running', true, 30 * MINUTE_IN_SECONDS);
        
        IndexFixer_Logger::log('🚀 Wymuszenie pełnego sprawdzania uruchomione w tle', 'success');
        
        wp_send_json_success(array(
            'message' => 'Pełne sprawdzanie zostało uruchomione (cache wyczyszczony)'
        ));
    }
    
    /**
     * AJAX test systemu aktualizacji
     */
    public function ajax_test_updater() {
        // Sprawdź uprawnienia i nonce
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Brak uprawnień');
        }
        
        check_ajax_referer('indexfixer_nonce', 'nonce');
        
        IndexFixer_Logger::log('🔄 Testowanie systemu aktualizacji...', 'info');
        
        try {
            // Testuj GitHub API bezpośrednio
            $github_url = 'https://api.github.com/repos/pavelzin/indexfixer/releases/latest';
            $request = wp_remote_get($github_url, array('timeout' => 30));
            
            if (is_wp_error($request)) {
                throw new Exception('Błąd połączenia z GitHub API: ' . $request->get_error_message());
            }
            
            $response_code = wp_remote_retrieve_response_code($request);
            $body = wp_remote_retrieve_body($request);
            
            IndexFixer_Logger::log("GitHub API Response Code: $response_code", 'info');
            
            if ($response_code !== 200) {
                throw new Exception("GitHub API zwróciło kod: $response_code");
            }
            
            $data = json_decode($body, true);
            if (!$data) {
                throw new Exception('Nie można parsować odpowiedzi GitHub API');
            }
            
            $latest_version = isset($data['tag_name']) ? ltrim($data['tag_name'], 'v') : 'unknown';
            $current_version = INDEXFIXER_VERSION;
            $download_url = isset($data['assets'][0]['browser_download_url']) ? $data['assets'][0]['browser_download_url'] : null;
            
            IndexFixer_Logger::log("Aktualna wersja: $current_version", 'info');
            IndexFixer_Logger::log("Najnowsza wersja: $latest_version", 'info');
            IndexFixer_Logger::log("Download URL: " . ($download_url ?: 'Brak'), 'info');
            
            // Test czy WordPress wykrywa aktualizację
            delete_site_transient('update_plugins');
            wp_update_plugins();
            
            $updates = get_site_transient('update_plugins');
            $plugin_slug = plugin_basename(INDEXFIXER_PLUGIN_DIR . 'indexfixer.php');
            $update_available = isset($updates->response[$plugin_slug]);
            
            IndexFixer_Logger::log("WordPress wykrywa aktualizację: " . ($update_available ? 'TAK' : 'NIE'), $update_available ? 'success' : 'warning');
            
            wp_send_json_success(array(
                'current_version' => $current_version,
                'latest_version' => $latest_version,
                'update_available' => $update_available,
                'download_url' => $download_url,
                'github_response' => $data
            ));
            
        } catch (Exception $e) {
            IndexFixer_Logger::log('❌ Błąd testowania aktualizacji: ' . $e->getMessage(), 'error');
            wp_send_json_error('Błąd: ' . $e->getMessage());
        }
    }
    
    /**
     * AJAX planowanie crona odnawiania tokenów
     */
    public function ajax_schedule_token_cron() {
        check_ajax_referer('indexfixer_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Brak uprawnień');
        }
        
        $already_scheduled = wp_next_scheduled('indexfixer_auto_refresh_tokens');
        
        if ($already_scheduled) {
            $next_run = date('Y-m-d H:i:s', $already_scheduled + (get_option('gmt_offset') * 3600));
            wp_send_json_success(array(
                'message' => "Cron odnawiania tokenów już istnieje. Następne uruchomienie: $next_run",
                'already_exists' => true
            ));
        } else {
            // Zaplanuj cron odnawiania tokenów co 30 minut
            $scheduled = wp_schedule_event(time(), 'thirty_minutes', 'indexfixer_auto_refresh_tokens');
            
            if ($scheduled !== false) {
                IndexFixer_Logger::log('⏰ Ręcznie zaplanowano cron odnawiania tokenów (co 30 min)', 'success');
                wp_send_json_success(array(
                    'message' => 'Cron odnawiania tokenów został zaplanowany (co 30 minut)',
                    'scheduled' => true
                ));
            } else {
                wp_send_json_error('Nie udało się zaplanować crona odnawiania tokenów');
            }
        }
    }

    /**
     * AJAX wymuś przebudowę harmonogramu widgetów
     */
    public function ajax_force_rebuild_widget_schedule() {
        check_ajax_referer('indexfixer_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Brak uprawnień');
        }
        
        // Usuń WSZYSTKIE crony widgetów (także te z nieprawidłowym interwałem)
        wp_clear_scheduled_hook('indexfixer_widget_check');
        
        // Wymuś przebudowę harmonogramu
        delete_option('indexfixer_widget_schedule_check'); // Usuń info o ostatnim sprawdzeniu
        
        // Zaplanuj nowy harmonogram w trybie produkcyjnym
        $test_mode = get_option('indexfixer_widget_test_mode', false);
        if ($test_mode) {
            IndexFixer_Logger::log('⚠️ UWAGA: Tryb testowy jest włączony w bazie danych!', 'warning');
        }
        
        $interval = $test_mode ? 'ten_minutes' : 'daily';
        $scheduled = wp_schedule_event(time(), $interval, 'indexfixer_widget_check');
        
        if ($scheduled !== false) {
            $mode = $test_mode ? 'TESTOWY (10 min)' : 'PRODUKCYJNY (24h)';
            IndexFixer_Logger::log("🔧 Wymuszona przebudowa harmonogramu widgetów - tryb: $mode", 'success');
            
            wp_send_json_success(array(
                'message' => "Harmonogram przebudowany - tryb: $mode",
                'test_mode' => $test_mode,
                'interval' => $interval
            ));
        } else {
            wp_send_json_error('Nie udało się przebudować harmonogramu');
        }
    }
    
    /**
     * Testuje cron zapisywania statystyk dziennych
     */
    public function ajax_test_stats_cron() {
        check_ajax_referer('indexfixer_test_stats_cron', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Brak uprawnień');
        }
        
        IndexFixer_Logger::log('🧪 TEST: Uruchomienie testowego zapisu statystyk przez AJAX', 'info');
        
        $result = IndexFixer_Database::save_daily_stats();
        
        if ($result) {
            IndexFixer_Logger::log('✅ TEST: Statystyki zostały zapisane pomyślnie', 'success');
            wp_send_json_success(array(
                'message' => 'Statystyki zostały zapisane pomyślnie',
                'result' => $result
            ));
        } else {
            IndexFixer_Logger::log('❌ TEST: Błąd podczas zapisywania statystyk', 'error');
            wp_send_json_error('Błąd podczas zapisywania statystyk');
        }
    }
    
    /**
     * AJAX handler dla manualnego czyszczenia URL-ów z usuniętych postów
     */
    public function ajax_manual_cleanup() {
        check_ajax_referer('indexfixer_cleanup', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Brak uprawnień');
        }
        
        $cleanup_type = sanitize_text_field($_POST['cleanup_type']);
        
        IndexFixer_Logger::log("🧹 Uruchomiono manualne czyszczenie: $cleanup_type", 'info');
        
        if ($cleanup_type === 'orphaned_urls') {
            $deleted_count = IndexFixer_Database::cleanup_orphaned_urls();
            wp_send_json_success(array(
                'deleted_count' => $deleted_count,
                'message' => "Usunięto $deleted_count URL-ów z usuniętych postów"
            ));
        } else {
            wp_send_json_error('Nieznany typ czyszczenia');
        }
    }

    /**
     * AJAX handler dla odtwarzania tabel bazy danych
     */
    public function ajax_recreate_tables() {
        check_ajax_referer('indexfixer_recreate_tables', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Brak uprawnień');
        }
        
        IndexFixer_Logger::log('🔧 Uruchomiono odtwarzanie tabel bazy danych', 'info');
        
        try {
            // Uruchom tworzenie tabel
            IndexFixer_Database::create_tables();
            
            // Sprawdź czy tabele zostały utworzone
            $database_tables = IndexFixer_Database::check_database_tables();
            $missing_tables = array();
            
            foreach ($database_tables as $table) {
                if (!$table['exists']) {
                    $missing_tables[] = $table['name'];
                }
            }
            
            if (empty($missing_tables)) {
                IndexFixer_Logger::log('✅ Wszystkie tabele zostały pomyślnie utworzone', 'success');
                wp_send_json_success(array(
                    'message' => 'Wszystkie tabele zostały pomyślnie utworzone',
                    'tables_created' => count($database_tables)
                ));
            } else {
                IndexFixer_Logger::log('⚠️ Niektóre tabele nadal nie istnieją: ' . implode(', ', $missing_tables), 'warning');
                wp_send_json_error('Niektóre tabele nadal nie zostały utworzone: ' . implode(', ', $missing_tables));
            }
            
        } catch (Exception $e) {
            IndexFixer_Logger::log('❌ Błąd podczas tworzenia tabel: ' . $e->getMessage(), 'error');
            wp_send_json_error('Błąd podczas tworzenia tabel: ' . $e->getMessage());
        }
    }

    /**
     * AJAX handler dla usuwania URL-ów ze slashem na końcu
     */
    public function ajax_remove_trailing_slash_urls() {
        check_ajax_referer('indexfixer_remove_slash_urls', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Brak uprawnień');
        }
        
        IndexFixer_Logger::log('🧹 Uruchomiono usuwanie URL-ów ze slashem na końcu', 'info');
        
        try {
            global $wpdb;
            $table_name = IndexFixer_Database::get_table_name();
            
            // Znajdź URL-e ze slashem na końcu (ale nie główną stronę /)
            $urls_with_slash = $wpdb->get_results(
                "SELECT id, url FROM $table_name 
                 WHERE url LIKE '%/' 
                 AND url != '/' 
                 AND LENGTH(url) > 1"
            );
            
            $deleted_count = 0;
            
            if (!empty($urls_with_slash)) {
                // Usuń URL-e ze slashem
                $result = $wpdb->query(
                    "DELETE FROM $table_name 
                     WHERE url LIKE '%/' 
                     AND url != '/' 
                     AND LENGTH(url) > 1"
                );
                
                $deleted_count = $wpdb->rows_affected;
                
                IndexFixer_Logger::log("✅ Usunięto $deleted_count URL-ów ze slashem na końcu", 'success');
                
                // Loguj kilka przykładów usuniętych URL-ów
                $examples = array_slice($urls_with_slash, 0, 5);
                foreach ($examples as $example) {
                    IndexFixer_Logger::log("🗑️ Usunięto: {$example->url}", 'info');
                }
                
                if (count($urls_with_slash) > 5) {
                    IndexFixer_Logger::log("... i " . (count($urls_with_slash) - 5) . " więcej", 'info');
                }
            }
            
            wp_send_json_success(array(
                'deleted_count' => $deleted_count,
                'message' => "Usunięto $deleted_count URL-ów ze slashem na końcu"
            ));
            
        } catch (Exception $e) {
            IndexFixer_Logger::log('❌ Błąd podczas usuwania URL-ów: ' . $e->getMessage(), 'error');
            wp_send_json_error('Błąd podczas usuwania URL-ów: ' . $e->getMessage());
        }
    }

    /**
     * AJAX handler dla przebudowy URL-ów po zmianie struktury
     */
    public function ajax_rebuild_urls() {
        check_ajax_referer('indexfixer_rebuild_urls', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Brak uprawnień');
        }
        
        IndexFixer_Logger::log('🔄 Uruchomiono przebudowę URL-ów po zmianie struktury', 'info');
        
        try {
            global $wpdb;
            $table_name = IndexFixer_Database::get_table_name();
            $history_table = IndexFixer_Database::get_history_table_name();
            
            // Pobierz statystyki przed usunięciem
            $urls_before = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
            $history_before = $wpdb->get_var("SELECT COUNT(*) FROM $history_table");
            
            // Usuń wszystkie URL-e z głównej tabeli
            $wpdb->query("TRUNCATE TABLE $table_name");
            
            // Usuń wszystkie wpisy z historii
            $wpdb->query("TRUNCATE TABLE $history_table");
            
            IndexFixer_Logger::log("🗑️ Usunięto $urls_before URL-ów z głównej tabeli", 'info');
            IndexFixer_Logger::log("🗑️ Usunięto $history_before wpisów z historii", 'info');
            
            // Pobierz świeże URL-e z WordPressa
            require_once INDEXFIXER_PLUGIN_DIR . 'includes/fetch-urls.php';
            $fresh_urls = get_all_urls();
            
            if (empty($fresh_urls)) {
                IndexFixer_Logger::log('⚠️ Nie znaleziono URL-ów w WordPressie', 'warning');
                wp_send_json_error('Nie znaleziono URL-ów w WordPressie');
                return;
            }
            
            // Dodaj świeże URL-e do bazy
            $added_count = 0;
            foreach ($fresh_urls as $url) {
                $result = IndexFixer_Database::add_or_update_url($url, 'unknown', array(
                    'verdict' => 'URL_UNKNOWN',
                    'coverage_state' => null,
                    'robots_txt_state' => null,
                    'indexing_state' => null,
                    'page_fetch_state' => null,
                    'crawled_as' => null,
                    'last_crawl_time' => null
                ));
                
                if ($result) {
                    $added_count++;
                }
            }
            
            IndexFixer_Logger::log("✅ Dodano $added_count nowych URL-ów z aktualnej struktury WordPress", 'success');
            
            wp_send_json_success(array(
                'urls_removed' => $urls_before,
                'history_removed' => $history_before,
                'urls_added' => $added_count,
                'message' => "Przebudowa zakończona! Usunięto $urls_before starych URL-ów, dodano $added_count nowych URL-ów z aktualnej struktury WordPress."
            ));
            
        } catch (Exception $e) {
            IndexFixer_Logger::log('❌ Błąd podczas przebudowy URL-ów: ' . $e->getMessage(), 'error');
            wp_send_json_error('Błąd podczas przebudowy URL-ów: ' . $e->getMessage());
        }
    }
} 