<?php
/**
 * Dashboard Widget dla IndexFixer
 */

if (!defined('ABSPATH')) {
    exit;
}

class IndexFixer_Dashboard_Widget {
    
    public static function init() {
        add_action('wp_dashboard_setup', array(__CLASS__, 'add_dashboard_widgets'));
        add_action('wp_ajax_indexfixer_dashboard_refresh', array(__CLASS__, 'ajax_refresh_widget'));
    }
    
    /**
     * Dodaje widget do dashboardu
     */
    public static function add_dashboard_widgets() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        wp_add_dashboard_widget(
            'indexfixer_dashboard_widget',
            '📊 IndexFixer - Status Indeksowania',
            array(__CLASS__, 'render_widget'),
            array(__CLASS__, 'configure_widget')
        );
    }
    
    /**
     * Renderuje widget dashboardu
     */
    public static function render_widget() {
        // Pobierz statystyki tak samo jak główny dashboard
        $stats = self::calculate_statistics();
        
        // Sprawdź czy jest autoryzacja GSC
        $auth_handler = new IndexFixer_Auth_Handler();
        $is_authorized = $auth_handler->is_authorized();
        
        // Sprawdź ostatnią aktywność
        $last_check = get_option('indexfixer_last_check', 0);
        $last_check_formatted = $last_check ? human_time_diff($last_check, current_time('timestamp')) . ' temu' : 'Nigdy';
        
        // Sprawdź czy proces jest uruchomiony
        $process_running = get_transient('indexfixer_process_running');
        
        ?>
        <div class="indexfixer-dashboard-widget">
            <style>
                .indexfixer-dashboard-widget .stats-grid {
                    display: grid;
                    grid-template-columns: 1fr 1fr;
                    gap: 15px;
                    margin: 15px 0;
                }
                .indexfixer-dashboard-widget .stat-box {
                    background: #f8f9fa;
                    padding: 15px;
                    border-radius: 6px;
                    text-align: center;
                    border-left: 4px solid #0073aa;
                }
                .indexfixer-dashboard-widget .stat-box.indexed {
                    border-left-color: #46b450;
                }
                .indexfixer-dashboard-widget .stat-box.not-indexed {
                    border-left-color: #dc3232;
                }
                .indexfixer-dashboard-widget .stat-box.pending {
                    border-left-color: #ffb900;
                }
                .indexfixer-dashboard-widget .stat-number {
                    font-size: 24px;
                    font-weight: bold;
                    color: #23282d;
                    display: block;
                }
                .indexfixer-dashboard-widget .stat-label {
                    font-size: 12px;
                    color: #666;
                    text-transform: uppercase;
                    letter-spacing: 0.5px;
                }
                .indexfixer-dashboard-widget .quick-actions {
                    display: flex;
                    gap: 10px;
                    margin-top: 15px;
                    flex-wrap: wrap;
                }
                .indexfixer-dashboard-widget .quick-actions .button {
                    flex: 1;
                    text-align: center;
                    min-width: auto;
                }
                .indexfixer-dashboard-widget .status-info {
                    background: #fff;
                    border: 1px solid #e1e1e1;
                    padding: 10px;
                    border-radius: 4px;
                    margin: 10px 0;
                    font-size: 13px;
                }
                .indexfixer-dashboard-widget .status-info.warning {
                    border-left: 4px solid #ffb900;
                    background: #fff8e1;
                }
                .indexfixer-dashboard-widget .status-info.error {
                    border-left: 4px solid #dc3232;
                    background: #fef7f7;
                }
                .indexfixer-dashboard-widget .status-info.success {
                    border-left: 4px solid #46b450;
                    background: #f7fff7;
                }
            </style>
            
            <?php if (!$is_authorized): ?>
                <div class="status-info error">
                    <strong>⚠️ Brak autoryzacji Google Search Console</strong><br>
                    <a href="<?php echo admin_url('admin.php?page=indexfixer'); ?>">Skonfiguruj połączenie z GSC</a>
                </div>
            <?php else: ?>
                <?php 
                // NOWE: Pokaż informacje o czasie wygaśnięcia tokenu
                $auth_handler = new IndexFixer_Auth_Handler();
                $token_info = $auth_handler->get_token_expiry_info();
                ?>
                
                <?php if ($token_info['expires_soon']): ?>
                    <div class="status-info warning">
                        <strong>⏰ Token wygasa za <?php echo $token_info['expires_in_minutes']; ?> minut</strong><br>
                        Automatyczne odnawianie aktywne
                    </div>
                <?php elseif ($token_info['expires_at'] > 0): ?>
                    <div class="status-info success">
                        <strong>🔑 Token ważny do <?php echo wp_date('H:i', $token_info['expires_at']); ?> (<?php echo wp_date('T', $token_info['expires_at']); ?>)</strong><br>
                        Automatyczne odnawianie aktywne
                    </div>
                <?php endif; ?>
                
                <?php if ($process_running): ?>
                    <div class="status-info warning">
                        <strong>🔄 Sprawdzanie w toku...</strong><br>
                        Proces sprawdzania URL-ów jest aktywny
                    </div>
                <?php else: ?>
                    <div class="status-info success">
                        <strong>✅ Gotowy do sprawdzania</strong><br>
                        Ostatnie sprawdzenie: <?php echo esc_html($last_check_formatted); ?>
                    </div>
                <?php endif; ?>
                
                <div class="stats-grid">
                    <div class="stat-box indexed">
                        <span class="stat-number"><?php echo esc_html($stats['indexed']); ?></span>
                        <span class="stat-label">Zaindeksowane</span>
                    </div>
                    
                    <div class="stat-box not-indexed">
                        <span class="stat-number"><?php echo esc_html($stats['not_indexed']); ?></span>
                        <span class="stat-label">Niezaindeksowane</span>
                    </div>
                    
                    <div class="stat-box pending">
                        <span class="stat-number"><?php echo esc_html($stats['discovered']); ?></span>
                        <span class="stat-label">Odkryte</span>
                    </div>
                    
                    <div class="stat-box">
                        <span class="stat-number"><?php echo esc_html($stats['total']); ?></span>
                        <span class="stat-label">Łącznie URL-ów</span>
                    </div>
                </div>
                
                <?php if ($stats['not_indexed'] > 0): ?>
                    <div class="status-info warning">
                        <strong>📝 Sugestia:</strong> 
                        Masz <?php echo $stats['not_indexed']; ?> niezaindeksowanych postów. 
                        <a href="<?php echo admin_url('admin.php?page=indexfixer'); ?>">Sprawdź szczegóły</a>
                    </div>
                <?php endif; ?>
                
            <?php endif; ?>
            
            <div class="quick-actions">
                <?php if ($is_authorized): ?>
                    <button type="button" class="button button-primary" id="indexfixer-refresh-dashboard" 
                            <?php echo $process_running ? 'disabled' : ''; ?>>
                        <?php echo $process_running ? '⏳ Sprawdzanie...' : '🔄 Odśwież'; ?>
                    </button>
                <?php endif; ?>
                
                <a href="<?php echo admin_url('admin.php?page=indexfixer'); ?>" class="button button-secondary">
                    📊 Pełny Dashboard
                </a>
            </div>
            
            <script>
                jQuery(document).ready(function($) {
                    $('#indexfixer-refresh-dashboard').on('click', function() {
                        var $button = $(this);
                        var originalText = $button.text();
                        
                        $button.prop('disabled', true).text('⏳ Sprawdzanie...');
                        
                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            data: {
                                action: 'indexfixer_dashboard_refresh',
                                nonce: '<?php echo wp_create_nonce('indexfixer_dashboard_nonce'); ?>'
                            },
                            success: function(response) {
                                if (response.success) {
                                    // Odśwież widget
                                    location.reload();
                                } else {
                                    alert('Błąd: ' + (response.data || 'Nieznany błąd'));
                                    $button.prop('disabled', false).text(originalText);
                                }
                            },
                            error: function() {
                                alert('Błąd połączenia');
                                $button.prop('disabled', false).text(originalText);
                            }
                        });
                    });
                });
            </script>
        </div>
        <?php
    }
    
    /**
     * Konfiguracja widget (w ustawieniach dashboardu)
     */
    public static function configure_widget() {
        $show_tips = get_option('indexfixer_dashboard_show_tips', 1);
        $auto_refresh = get_option('indexfixer_dashboard_auto_refresh', 0);
        
        if (isset($_POST['indexfixer_dashboard_submit'])) {
            $show_tips = isset($_POST['indexfixer_dashboard_show_tips']) ? 1 : 0;
            $auto_refresh = isset($_POST['indexfixer_dashboard_auto_refresh']) ? 1 : 0;
            
            update_option('indexfixer_dashboard_show_tips', $show_tips);
            update_option('indexfixer_dashboard_auto_refresh', $auto_refresh);
        }
        
        ?>
        <p>
            <label>
                <input type="checkbox" name="indexfixer_dashboard_show_tips" 
                       <?php checked($show_tips); ?> />
                Pokazuj sugestie w widget
            </label>
        </p>
        
        <p>
            <label>
                <input type="checkbox" name="indexfixer_dashboard_auto_refresh" 
                       <?php checked($auto_refresh); ?> />
                Auto-odświeżanie co 5 min (tylko gdy dashboard otwarty)
            </label>
        </p>
        
        <input type="hidden" name="indexfixer_dashboard_submit" value="1" />
        <?php
    }
    
    /**
     * Liczy statystyki tak samo jak główny dashboard
     */
    private static function calculate_statistics() {
        $urls = IndexFixer_Fetch_URLs::get_all_urls();
        
        $stats = array(
            'total' => count($urls),
            'checked' => 0,
            'indexed' => 0,
            'not_indexed' => 0,
            'discovered' => 0,
            'excluded' => 0,
            'unknown' => 0
        );
        
        foreach ($urls as $url_data) {
            // Najpierw spróbuj z tabeli bazy danych
            $status_data = IndexFixer_Database::get_url_status($url_data['url']);
            
            // Fallback do starych transientów jeśli brak w tabeli
            if (!$status_data) {
                $status_data = IndexFixer_Cache::get_url_status($url_data['url']);
            }
            
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
                } else {
                    $stats['unknown']++;
                }
            } else {
                $stats['unknown']++;
            }
        }
        
        return $stats;
    }
    
    /**
     * AJAX odświeżanie widget
     */
    public static function ajax_refresh_widget() {
        if (!wp_verify_nonce($_POST['nonce'], 'indexfixer_dashboard_nonce')) {
            wp_send_json_error('Invalid nonce');
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Brak uprawnień');
        }
        
        // Sprawdź czy proces nie jest już uruchomiony
        $process_running = get_transient('indexfixer_process_running');
        if ($process_running) {
            wp_send_json_error('Proces sprawdzania jest już uruchomiony');
        }
        
        // Uruchom sprawdzanie w tle (asynchronicznie)
        wp_schedule_single_event(time(), 'indexfixer_check_urls_event');
        
        // Ustaw flagę procesu
        set_transient('indexfixer_process_running', true, 30 * MINUTE_IN_SECONDS);
        
        IndexFixer_Logger::log('Sprawdzanie uruchomione z dashboard widget', 'info');
        
        wp_send_json_success(array(
            'message' => 'Sprawdzanie zostało uruchomione w tle'
        ));
    }
}

// Inicjalizuj dashboard widget
IndexFixer_Dashboard_Widget::init(); 