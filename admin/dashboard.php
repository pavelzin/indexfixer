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
    }
    
    /**
     * Dodaje skrypty i style
     */
    public function enqueue_scripts($hook) {
        if (!in_array($hook, array('toplevel_page_indexfixer', 'indexfixer_page_indexfixer-settings'))) {
            return;
        }
        
        wp_enqueue_style(
            'indexfixer-admin',
            plugins_url('assets/css/admin.css', dirname(__FILE__)),
            array(),
            INDEXFIXER_VERSION
        );
        
        wp_enqueue_script(
            'indexfixer-admin',
            plugins_url('assets/js/admin.js', dirname(__FILE__)),
            array('jquery'),
            INDEXFIXER_VERSION,
            true
        );
        
        // Dodaj dane dla AJAX
        wp_localize_script('indexfixer-admin', 'indexfixer', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('indexfixer_nonce')
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
        
        foreach ($urls as $url_data) {
            $url_statuses[$url_data['url']] = IndexFixer_Cache::get_url_status($url_data['url']);
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
} 