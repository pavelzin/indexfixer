<?php
/**
 * Zakładka "Przegląd" w dashboardzie
 */

if (!defined('ABSPATH')) {
    exit;
}

$stats = IndexFixer_Database::get_statistics();
$historical_stats = IndexFixer_Database::get_historical_stats();
?>

<script>
// Lokalizacja zmiennych
var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
var indexfixer = {
    check_url_nonce: '<?php echo wp_create_nonce('indexfixer_check_url'); ?>'
};

var indexfixer_stats = <?php echo json_encode($historical_stats); ?>;

// Debugowanie
console.log('IndexFixer: Skrypt overview.php załadowany');
console.log('IndexFixer: ajaxurl =', ajaxurl);
console.log('IndexFixer: indexfixer =', indexfixer);

// Obsługa przycisku sprawdzania pojedynczego URL
jQuery(document).ready(function($) {
    console.log('IndexFixer: Document ready');
    
    $('.check-single-url').on('click', function() {
        console.log('IndexFixer: Przycisk kliknięty');
        
        var $button = $(this);
        var url = $button.data('url');
        var $row = $button.closest('tr');
        
        console.log('IndexFixer: URL do sprawdzenia =', url);
        
        $button.prop('disabled', true).text('⏳');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'check_single_url',
                url: url,
                nonce: indexfixer.check_url_nonce
            },
            success: function(response) {
                console.log('IndexFixer: Odpowiedź AJAX =', response);
                
                if (response.success) {
                    // Aktualizuj status w tabeli
                    var status = response.data.status;
                    var statusClass = '';
                    var statusText = 'Nieznany';
                    
                    if (status.coverageState) {
                        switch(status.coverageState) {
                            case 'Submitted and indexed':
                                statusClass = 'indexed';
                                statusText = '✅ Zaindeksowane';
                                break;
                            case 'Crawled - currently not indexed':
                                statusClass = 'not-indexed';
                                statusText = '❌ Nie zaindeksowane';
                                break;
                            case 'Discovered - currently not indexed':
                                statusClass = 'discovered';
                                statusText = '🔍 Odkryte';
                                break;
                            default:
                                statusClass = 'other';
                                statusText = '❓ ' + status.coverageState;
                        }
                    }
                    
                    $row.find('.widget-status')
                        .removeClass('indexed not-indexed discovered other')
                        .addClass(statusClass)
                        .text(statusText);
                    
                    $row.find('td:nth-child(4)').text(status.coverageState || '-');
                    $row.find('td:nth-child(6)').text(new Date().toLocaleString());
                    
                    // Odśwież statystyki na górze strony
                    location.reload();
                } else {
                    console.error('IndexFixer: Błąd AJAX =', response.data);
                    alert('Błąd: ' + (response.data || 'Nieznany błąd'));
                }
                $button.prop('disabled', false).text('🔄');
            },
            error: function(xhr, status, error) {
                console.error('IndexFixer: Błąd połączenia =', {xhr: xhr, status: status, error: error});
                alert('Błąd połączenia');
                $button.prop('disabled', false).text('🔄');
            }
        });
    });
});
</script>

<div class="wrap">
    <h1>IndexFixer</h1>
    <div class="indexfixer-stats-boxes" style="background: #fff; padding: 24px; border-radius: 8px; border: 1px solid #e5e5e5; margin-bottom: 32px;">
        <h2 style="font-size: 20px; margin-bottom: 24px; display: flex; align-items: center; gap: 8px;">
            <span style="font-size: 22px;">📊</span> Statystyki URL-ów
        </h2>
        <div style="display: flex; gap: 16px; flex-wrap: wrap;">
            <div style="flex:1; min-width: 180px; background: #f8f9fa; border-radius: 8px; padding: 24px 12px; text-align: center; border: 1px solid #e5e5e5;">
                <div style="font-size: 2.5em; font-weight: bold; color: #222;"><?php echo number_format($stats['total']); ?></div>
                <div style="font-size: 1.1em; color: #666; margin-top: 8px;">Łącznie URL-ów</div>
            </div>
            <div style="flex:1; min-width: 180px; background: #f8f9fa; border-radius: 8px; padding: 24px 12px; text-align: center; border: 1px solid #e5e5e5;">
                <div style="font-size: 2.5em; font-weight: bold; color: #222;"><?php echo number_format($stats['checked']); ?></div>
                <div style="font-size: 1.1em; color: #666; margin-top: 8px;">Sprawdzonych</div>
                <div style="font-size: 0.95em; color: #888; margin-top: 4px;">
                    <?php echo $stats['total'] > 0 ? round(($stats['checked'] / $stats['total']) * 100, 1) : 0; ?>%
                </div>
            </div>
            <div style="flex:1; min-width: 180px; background: #f8fff8; border-radius: 8px; padding: 24px 12px; text-align: center; border: 1px solid #d4eed8;">
                <div style="font-size: 2.5em; font-weight: bold; color: #46b450;"><?php echo number_format($stats['indexed']); ?></div>
                <div style="font-size: 1.1em; color: #46b450; margin-top: 8px;">Zaindeksowanych</div>
                <div style="font-size: 0.95em; color: #46b450; margin-top: 4px;">
                    <?php echo $stats['total'] > 0 ? round(($stats['indexed'] / $stats['total']) * 100, 1) : 0; ?>%
                </div>
            </div>
            <div style="flex:1; min-width: 180px; background: #fff8f8; border-radius: 8px; padding: 24px 12px; text-align: center; border: 1px solid #f3d6d6;">
                <div style="font-size: 2.5em; font-weight: bold; color: #dc3232;"><?php echo number_format($stats['not_indexed']); ?></div>
                <div style="font-size: 1.1em; color: #dc3232; margin-top: 8px;">Nie zaindeksowanych</div>
                <div style="font-size: 0.95em; color: #dc3232; margin-top: 4px;">
                    <?php echo $stats['total'] > 0 ? round(($stats['not_indexed'] / $stats['total']) * 100, 1) : 0; ?>%
                </div>
            </div>
            <div style="flex:1; min-width: 180px; background: #fffbe8; border-radius: 8px; padding: 24px 12px; text-align: center; border: 1px solid #ffe6a1;">
                <div style="font-size: 2.5em; font-weight: bold; color: #ffb900;"><?php echo number_format($stats['discovered']); ?></div>
                <div style="font-size: 1.1em; color: #ffb900; margin-top: 8px;">Odkrytych</div>
                <div style="font-size: 0.95em; color: #ffb900; margin-top: 4px;">
                    <?php echo $stats['total'] > 0 ? round(($stats['discovered'] / $stats['total']) * 100, 1) : 0; ?>%
                </div>
            </div>
            <div style="flex:1; min-width: 180px; background: #f8f9fa; border-radius: 8px; padding: 24px 12px; text-align: center; border: 1px solid #e5e5e5;">
                <div style="font-size: 2.5em; font-weight: bold; color: #222;"><?php echo number_format($stats['excluded']); ?></div>
                <div style="font-size: 1.1em; color: #666; margin-top: 8px;">Wykluczonych</div>
                <div style="font-size: 0.95em; color: #888; margin-top: 4px;">
                    <?php echo $stats['total'] > 0 ? round(($stats['excluded'] / $stats['total']) * 100, 1) : 0; ?>%
                </div>
            </div>
        </div>
    </div>
</div>

<!-- URL-e z widgetów -->
<?php 
$widget_urls = IndexFixer_Widget_Scheduler::get_all_widget_urls();

if (!empty($widget_urls)): 
?>
<div class="indexfixer-widget-urls">
    <h2>🎯 URL-e wyświetlane w widgetach</h2>
    
    <div class="info-box">
        <p><strong>ℹ️ Info:</strong> Te URL-e są aktualnie wyświetlane w Twoich widgetach IndexFixer i są automatycznie sprawdzane co 24h.</p>
    </div>
    
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th>URL</th>
                <th>Tytuł</th>
                <th>Status</th>
                <th>Coverage State</th>
                <th>Źródło widgetu</th>
                <th>Ostatnie sprawdzenie</th>
                <th>Dni w widgetcie</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($widget_urls as $url_data): ?>
                <?php
                $status_class = '';
                $status_text = 'Nieznany';
                
                if (isset($url_data->coverage_state)) {
                    switch($url_data->coverage_state) {
                        case 'Submitted and indexed':
                            $status_class = 'indexed';
                            $status_text = '✅ Zaindeksowane';
                            break;
                        case 'Crawled - currently not indexed':
                            $status_class = 'not-indexed';
                            $status_text = '❌ Nie zaindeksowane';
                            break;
                        case 'Discovered - currently not indexed':
                            $status_class = 'discovered';
                            $status_text = '🔍 Odkryte';
                            break;
                        default:
                            $status_class = 'other';
                            $status_text = '❓ ' . $url_data->coverage_state;
                    }
                }
                
                $last_checked = $url_data->last_checked ? 
                    date('Y-m-d H:i', strtotime($url_data->last_checked)) : 
                    'Nigdy';
                ?>
                <tr>
                    <td>
                        <div class="url-cell">
                            <a href="<?php echo esc_url($url_data->url); ?>" target="_blank" title="Otwórz URL">
                                <?php echo esc_html($url_data->url); ?>
                            </a>
                            <button type="button" 
                                    class="button button-small check-single-url" 
                                    data-url="<?php echo esc_attr($url_data->url); ?>"
                                    title="Sprawdź status tego URL">
                                🔄
                            </button>
                        </div>
                    </td>
                    <td>
                        <strong><?php echo esc_html($url_data->post_title ?: 'Bez tytułu'); ?></strong>
                    </td>
                    <td>
                        <span class="widget-status <?php echo esc_attr($status_class); ?>">
                            <?php echo esc_html($status_text); ?>
                        </span>
                    </td>
                    <td>
                        <?php echo esc_html($url_data->coverage_state ?: '-'); ?>
                    </td>
                    <td>
                        <strong><?php echo esc_html($url_data->widget_source); ?></strong><br>
                        <small>Pokazuje <?php echo $url_data->widget_count; ?> URL-ów</small>
                    </td>
                    <td>
                        <?php echo esc_html($last_checked); ?>
                    </td>
                    <td>
                        <?php
                        if (!empty($url_data->widget_since)) {
                            $since = strtotime($url_data->widget_since);
                            $now = time();
                            $days = floor(($now - $since) / 86400);
                            echo $days >= 0 ? $days : '-';
                        } else {
                            echo '-';
                        }
                        ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <div class="tip-box">
        <p><strong>💡 Tip:</strong> 
        Gdy Google zaindeksuje któryś z tych URL-ów, automatycznie zniknie z widgetów i zostanie zastąpiony następnym niezaindeksowanym postem.
        </p>
    </div>
</div>
<?php endif; ?>

<style>
/* Statystyki */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-bottom: 30px;
}

.stat-box {
    padding: 20px;
    background: #f8f9fa;
    border: 1px solid #e1e5e9;
    border-radius: 4px;
    text-align: center;
}

.stat-box.total { border-left: 4px solid #0073aa; }
.stat-box.checked { border-left: 4px solid #0085ba; }
.stat-box.indexed { border-left: 4px solid #46b450; }
.stat-box.not-indexed { border-left: 4px solid #dc3232; }
.stat-box.discovered { border-left: 4px solid #ffb900; }
.stat-box.excluded { border-left: 4px solid #666; }

.stat-value {
    font-size: 36px;
    font-weight: bold;
    margin-bottom: 8px;
    color: #23282d;
}

.stat-label {
    font-size: 14px;
    color: #666;
    margin-bottom: 5px;
}

.stat-percent {
    font-size: 12px;
    color: #999;
    font-weight: 500;
}

/* URL-e z widgetów */
.url-cell {
    display: flex;
    align-items: center;
    gap: 8px;
}

.url-cell a {
    flex: 1;
    word-break: break-all;
    font-size: 12px;
}

.info-box {
    background: #e7f3ff;
    padding: 15px;
    border-left: 3px solid #0073aa;
    margin-bottom: 20px;
}

.tip-box {
    margin-top: 15px;
    padding: 10px;
    background: #fff3cd;
    border-left: 3px solid #ffc107;
}

.tip-box p {
    margin: 0;
    font-size: 13px;
}

.widget-status {
    padding: 4px 8px;
    border-radius: 3px;
    font-size: 11px;
    font-weight: bold;
}

.widget-status.indexed { background: #dff0d8; color: #3c763d; }
.widget-status.not-indexed { background: #f2dede; color: #a94442; }
.widget-status.discovered { background: #fcf8e3; color: #8a6d3b; }
.widget-status.other { background: #f5f5f5; color: #666; }
</style> 