<?php
/**
 * Zakładka "Diagnostyka" w dashboardzie
 */

if (!defined('ABSPATH')) {
    exit;
}

// Pobierz aktualne ustawienia
$settings = get_option('indexfixer_settings', array());
$debug_mode = isset($settings['debug_mode']) ? $settings['debug_mode'] : false;

// Pobierz logi diagnostyczne
$logs = get_option('indexfixer_debug_logs', array());
$logs = array_reverse($logs); // Najnowsze logi na górze

// Obsługa czyszczenia logów
if (isset($_POST['clear_logs'])) {
    check_admin_referer('indexfixer_clear_logs');
    update_option('indexfixer_debug_logs', array());
    $logs = array();
    echo '<div class="notice notice-success"><p>Logi zostały wyczyszczone.</p></div>';
}

// Pobierz status systemu
$system_status = array(
    'wordpress_version' => get_bloginfo('version'),
    'php_version' => PHP_VERSION,
    'memory_limit' => ini_get('memory_limit'),
    'max_execution_time' => ini_get('max_execution_time'),
    'curl_enabled' => function_exists('curl_version'),
    'ssl_enabled' => extension_loaded('openssl'),
    'debug_mode' => WP_DEBUG,
    'plugin_debug_mode' => $debug_mode
);

// Sprawdź status połączenia z Google Search Console
$gsc_connection = get_option('indexfixer_gsc_connection', array());
$gsc_status = !empty($gsc_connection['access_token']) ? 'connected' : 'disconnected';

// Sprawdź status tabel bazy danych
$database_tables = IndexFixer_Database::check_database_tables();
?>

<div class="indexfixer-diagnostics">
    <h2>🔍 Diagnostyka</h2>
    
    <!-- Status systemu -->
    <div class="card">
        <h3>Status systemu</h3>
        <table class="widefat">
            <tbody>
                <tr>
                    <th>WordPress</th>
                    <td><?php echo esc_html($system_status['wordpress_version']); ?></td>
                </tr>
                <tr>
                    <th>PHP</th>
                    <td><?php echo esc_html($system_status['php_version']); ?></td>
                </tr>
                <tr>
                    <th>Limit pamięci</th>
                    <td><?php echo esc_html($system_status['memory_limit']); ?></td>
                </tr>
                <tr>
                    <th>Maksymalny czas wykonania</th>
                    <td><?php echo esc_html($system_status['max_execution_time']); ?> sekund</td>
                </tr>
                <tr>
                    <th>cURL</th>
                    <td>
                        <?php if ($system_status['curl_enabled']): ?>
                            <span class="status-ok">✓ Włączone</span>
                        <?php else: ?>
                            <span class="status-error">✗ Wyłączone</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th>SSL</th>
                    <td>
                        <?php if ($system_status['ssl_enabled']): ?>
                            <span class="status-ok">✓ Włączone</span>
                        <?php else: ?>
                            <span class="status-error">✗ Wyłączone</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th>Tryb debugowania WordPress</th>
                    <td>
                        <?php if ($system_status['debug_mode']): ?>
                            <span class="status-warning">⚠ Włączony</span>
                        <?php else: ?>
                            <span class="status-ok">✓ Wyłączony</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th>Tryb debugowania wtyczki</th>
                    <td>
                        <?php if ($system_status['plugin_debug_mode']): ?>
                            <span class="status-warning">⚠ Włączony</span>
                        <?php else: ?>
                            <span class="status-ok">✓ Wyłączony</span>
                        <?php endif; ?>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
    
    <!-- Status połączenia z Google Search Console -->
    <div class="card">
        <h3>Status połączenia z Google Search Console</h3>
        <table class="widefat">
            <tbody>
                <tr>
                    <th>Status połączenia</th>
                    <td>
                        <?php if ($gsc_status === 'connected'): ?>
                            <span class="status-ok">✓ Połączono</span>
                        <?php else: ?>
                            <span class="status-error">✗ Brak połączenia</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php if ($gsc_status === 'connected'): ?>
                    <tr>
                        <th>Ostatnie odświeżenie tokenu</th>
                        <td>
                            <?php 
                            if (!empty($gsc_connection['token_refresh_time'])) {
                                echo esc_html(
                                    date_i18n(
                                        'Y-m-d H:i:s', 
                                        $gsc_connection['token_refresh_time']
                                    )
                                );
                            } else {
                                echo 'Nieznane';
                            }
                            ?>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Status bazy danych -->
    <div class="card">
        <h3>🗄️ Status bazy danych</h3>
        
        <?php if (!empty($database_tables)): ?>
            <table class="widefat">
                <thead>
                    <tr>
                        <th>Tabela</th>
                        <th>Opis</th>
                        <th>Status</th>
                        <th>Rekordów</th>
                        <th>Rozmiar (MB)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $all_tables_exist = true;
                    foreach ($database_tables as $table): 
                        if (!$table['exists']) {
                            $all_tables_exist = false;
                        }
                    ?>
                        <tr>
                            <td><code><?php echo esc_html($table['name']); ?></code></td>
                            <td><?php echo esc_html($table['description']); ?></td>
                            <td>
                                <?php if ($table['exists']): ?>
                                    <span class="status-ok">✓ Istnieje</span>
                                <?php else: ?>
                                    <span class="status-error">✗ Brak tabeli</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($table['exists']): ?>
                                    <?php echo number_format($table['row_count']); ?>
                                <?php else: ?>
                                    <span style="color: #999;">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($table['exists']): ?>
                                    <?php echo $table['size_mb']; ?>
                                <?php else: ?>
                                    <span style="color: #999;">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <?php if (!$all_tables_exist): ?>
                <div style="margin-top: 16px; padding: 12px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px; color: #721c24;">
                    <strong>⚠️ Wykryto brakujące tabele!</strong><br>
                    Niektóre tabele bazy danych nie istnieją. Możesz spróbować je odtworzyć klikając przycisk poniżej.
                    <br><br>
                    <button type="button" class="button button-primary" id="recreate-tables">
                        🔧 Odtwórz brakujące tabele
                    </button>
                </div>
            <?php else: ?>
                <div style="margin-top: 16px; padding: 12px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 4px; color: #155724;">
                    <strong>✅ Wszystkie tabele istnieją</strong><br>
                    Baza danych wtyczki jest kompletna i gotowa do pracy.
                </div>
            <?php endif; ?>
        <?php else: ?>
            <p style="color: #dc3232;">❌ Nie można sprawdzić statusu tabel bazy danych.</p>
        <?php endif; ?>
    </div>
    
    <!-- NOWA SEKCJA: Czyszczenie bazy danych -->
    <div class="card">
        <h3>🧹 Czyszczenie bazy danych</h3>
        
        <p>Usuń URL-e które nie mają odpowiadającego postu w WordPressie (sprawdza tylko czy post o danym ID istnieje w bazie).</p>
        
        <div style="background: #f8f9fa; padding: 16px; border-radius: 6px; border-left: 4px solid #0073aa; margin-bottom: 20px;">
            <h4 style="margin: 0 0 8px 0;">Czyszczenie URL-ów z usuniętych postów</h4>
            <p style="margin: 0 0 12px 0; font-size: 14px; color: #666;">
                Usuwa URL-e których odpowiadające posty zostały usunięte z WordPressa. Sprawdza tylko czy post o danym ID istnieje w bazie WordPress - nie wykonuje żadnych zapytań HTTP.
            </p>
            <button type="button" class="button button-primary" id="cleanup-orphaned-urls">
                🗑️ Wyczyść URL-e z usuniętych postów
            </button>
        </div>
        
        <div id="cleanup-results" style="margin-top: 16px; padding: 12px; background: #fff; border: 1px solid #ddd; border-radius: 4px; display: none;">
            <h4 style="margin: 0 0 8px 0;">Wyniki czyszczenia:</h4>
            <div id="cleanup-output"></div>
        </div>
    </div>

    <!-- NOWA SEKCJA: Przebudowa URL-ów po zmianie struktury -->
    <div class="card">
        <h3>🔄 Przebudowa URL-ów po zmianie struktury</h3>
        
        <p>Użyj tej opcji gdy zmieniłeś strukturę URL-ów w WordPressie (np. z <code>/artykul/</code> na <code>/artykul</code>) i masz duplikaty w bazie danych.</p>
        
        <div style="background: #fff3cd; padding: 16px; border-radius: 6px; border-left: 4px solid #ffc107; margin-bottom: 20px;">
            <h4 style="margin: 0 0 8px 0;">⚠️ UWAGA: Drastyczna operacja!</h4>
            <p style="margin: 0 0 12px 0; font-size: 14px; color: #856404;">
                Ta operacja usunie <strong>WSZYSTKIE</strong> URL-e z bazy danych (włącznie z historią) i pobierze świeże URL-e z aktualnej struktury WordPress. 
                Wszystkie statusy indeksacji zostaną zresetowane do "unknown" i będą musiały być sprawdzone ponownie.
            </p>
            <p style="margin: 0; font-size: 14px; color: #856404;">
                <strong>Kiedy używać:</strong> Gdy zmieniłeś ustawienia permalinków w WordPress i masz duplikaty URL-ów (np. ze slashem i bez slasha).
            </p>
        </div>
        
        <button type="button" class="button button-secondary" id="rebuild-urls" 
                style="background: #dc3545; border-color: #dc3545; color: white;">
            🔄 Przebuduj wszystkie URL-e
        </button>
        
        <div id="rebuild-results" style="margin-top: 16px; padding: 12px; background: #fff; border: 1px solid #ddd; border-radius: 4px; display: none;">
            <h4 style="margin: 0 0 8px 0;">Wyniki przebudowy:</h4>
            <div id="rebuild-output"></div>
        </div>
    </div>

    <!-- Logi diagnostyczne -->
    <div class="card">
        <h3>Logi diagnostyczne</h3>
        
        <?php if (empty($logs)): ?>
            <p>Brak logów diagnostycznych.</p>
        <?php else: ?>
            <form method="post" action="">
                <?php wp_nonce_field('indexfixer_clear_logs'); ?>
                <p class="submit">
                    <input type="submit" 
                           name="clear_logs" 
                           class="button" 
                           value="Wyczyść logi"
                           onclick="return confirm('Czy na pewno chcesz wyczyścić wszystkie logi?');">
                </p>
            </form>
            
            <table class="widefat">
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Typ</th>
                        <th>Wiadomość</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><?php echo esc_html(date_i18n('Y-m-d H:i:s', $log['time'])); ?></td>
                            <td>
                                <span class="log-type log-type-<?php echo esc_attr($log['type']); ?>">
                                    <?php echo esc_html($log['type']); ?>
                                </span>
                            </td>
                            <td><?php echo esc_html($log['message']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<style>
.indexfixer-diagnostics .card {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    margin-bottom: 20px;
    padding: 20px;
}

.indexfixer-diagnostics .card h3 {
    margin-top: 0;
    padding-bottom: 10px;
    border-bottom: 1px solid #eee;
}

.indexfixer-diagnostics .widefat {
    border: none;
}

.indexfixer-diagnostics .widefat th {
    width: 200px;
    padding: 12px;
}

.indexfixer-diagnostics .widefat td {
    padding: 12px;
}

.status-ok {
    color: #46b450;
}

.status-error {
    color: #dc3232;
}

.status-warning {
    color: #ffb900;
}

.log-type {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 3px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
}

.log-type-error {
    background: #dc3232;
    color: #fff;
}

.log-type-warning {
    background: #ffb900;
    color: #fff;
}

.log-type-info {
    background: #00a0d2;
    color: #fff;
}

.log-type-debug {
    background: #7c7c7c;
    color: #fff;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Obsługa przycisku odtwarzania tabel
    $('#recreate-tables').on('click', function() {
        if (!confirm('Czy na pewno chcesz odtworzyć brakujące tabele bazy danych?\n\nTa operacja jest bezpieczna i nie usunie istniejących danych.')) {
            return;
        }
        
        var $button = $(this);
        var originalText = $button.text();
        
        $button.prop('disabled', true).text('⏳ Tworzenie tabel...');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'indexfixer_recreate_tables',
                nonce: '<?php echo wp_create_nonce('indexfixer_recreate_tables'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    alert('✅ Tabele zostały pomyślnie odtworzone!\n\nStrona zostanie odświeżona.');
                    location.reload();
                } else {
                    alert('❌ Błąd podczas tworzenia tabel: ' + (response.data || 'Nieznany błąd'));
                }
                
                $button.prop('disabled', false).text(originalText);
            },
            error: function() {
                alert('❌ Błąd połączenia podczas tworzenia tabel');
                $button.prop('disabled', false).text(originalText);
            }
        });
    });

    // Obsługa przycisku czyszczenia URL-ów z usuniętych postów
    $('#cleanup-orphaned-urls').on('click', function() {
        if (!confirm('Czy na pewno chcesz usunąć URL-e z usuniętych postów? Ta operacja jest nieodwracalna.\n\nSprawdza tylko czy post o danym ID istnieje w bazie WordPress.')) {
            return;
        }
        
        runCleanup('orphaned_urls', $(this));
    });
    
    function runCleanup(type, $button) {
        var originalText = $button.text();
        
        $button.prop('disabled', true).text('⏳ Czyszczenie...');
        $('#cleanup-results').show();
        $('#cleanup-output').html('<p>🔄 Sprawdzanie bazy danych...</p>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'indexfixer_manual_cleanup',
                cleanup_type: type,
                nonce: '<?php echo wp_create_nonce('indexfixer_cleanup'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    var html = '<div style="color: #46b450; font-weight: bold;">✅ Czyszczenie zakończone!</div>';
                    
                    if (response.data.deleted_count !== undefined) {
                        if (response.data.deleted_count > 0) {
                            html += '<p><strong>Usunięto ' + response.data.deleted_count + ' URL-ów</strong> z usuniętych postów.</p>';
                        } else {
                            html = '<div style="color: #0073aa;">ℹ️ Brak URL-ów do usunięcia - wszystkie URL-e mają odpowiadające posty.</div>';
                        }
                    }
                    
                    $('#cleanup-output').html(html);
                } else {
                    $('#cleanup-output').html(
                        '<div style="color: #dc3232; font-weight: bold;">❌ Błąd: ' + 
                        (response.data || 'Nieznany błąd') + '</div>'
                    );
                }
                
                $button.prop('disabled', false).text(originalText);
            },
            error: function() {
                $('#cleanup-output').html(
                    '<div style="color: #dc3232; font-weight: bold;">❌ Błąd połączenia</div>'
                );
                $button.prop('disabled', false).text(originalText);
            }
        });
    }

    // Obsługa przycisku przebudowy URL-ów
    $('#rebuild-urls').on('click', function() {
        if (!confirm('⚠️ UWAGA: Ta operacja usunie WSZYSTKIE URL-e z bazy danych!\n\n' +
                     'Wszystkie statusy indeksacji zostaną utracone i będą musiały być sprawdzone ponownie.\n\n' +
                     'Ta operacja jest nieodwracalna. Czy na pewno chcesz kontynuować?')) {
            return;
        }
        
        if (!confirm('🔄 Ostatnie potwierdzenie!\n\n' +
                     'Czy naprawdę chcesz usunąć wszystkie URL-e i rozpocząć od nowa?\n\n' +
                     'Kliknij OK żeby kontynuować lub Anuluj żeby przerwać.')) {
            return;
        }
        
        var $button = $(this);
        var originalText = $button.text();
        
        $button.prop('disabled', true).text('⏳ Przebudowywanie URL-ów...');
        $('#rebuild-results').show();
        $('#rebuild-output').html('<p>🔄 Usuwanie starych URL-ów z bazy danych...</p>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'indexfixer_rebuild_urls',
                nonce: '<?php echo wp_create_nonce('indexfixer_rebuild_urls'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    var html = '<div style="color: #46b450; font-weight: bold;">✅ Przebudowa zakończona pomyślnie!</div>';
                    html += '<p><strong>Statystyki operacji:</strong></p>';
                    html += '<ul>';
                    html += '<li>Usunięto starych URL-ów: <strong>' + response.data.urls_removed + '</strong></li>';
                    html += '<li>Usunięto wpisów z historii: <strong>' + response.data.history_removed + '</strong></li>';
                    html += '<li>Dodano nowych URL-ów: <strong>' + response.data.urls_added + '</strong></li>';
                    html += '</ul>';
                    html += '<p style="color: #856404;"><strong>ℹ️ Wszystkie nowe URL-e mają status "unknown" i będą musiały być sprawdzone ponownie.</strong></p>';
                    html += '<br><button type="button" class="button button-primary" onclick="location.reload()">🔄 Odśwież stronę</button>';
                    
                    $('#rebuild-output').html(html);
                } else {
                    $('#rebuild-output').html(
                        '<div style="color: #dc3232; font-weight: bold;">❌ Błąd podczas przebudowy: ' + 
                        (response.data || 'Nieznany błąd') + '</div>'
                    );
                }
                
                $button.prop('disabled', false).text(originalText);
            },
            error: function() {
                $('#rebuild-output').html(
                    '<div style="color: #dc3232; font-weight: bold;">❌ Błąd połączenia podczas przebudowy URL-ów</div>'
                );
                $button.prop('disabled', false).text(originalText);
            }
        });
    });
});
</script> 