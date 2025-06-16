<?php
/**
 * Główny szablon dashboardu IndexFixer
 */

if (!defined('ABSPATH')) {
    exit;
}

// Pobierz aktualną zakładkę
$current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'overview';

// Lista dostępnych zakładek
$tabs = array(
    'overview' => array(
        'label' => 'Przegląd',
        'icon' => '📊'
    ),
    'urls' => array(
        'label' => 'Lista URL-i',
        'icon' => '🔗'
    ),
    'history' => array(
        'label' => 'Historia',
        'icon' => '📈'
    ),
    'settings' => array(
        'label' => 'Ustawienia',
        'icon' => '⚙️'
    ),
    'diagnostics' => array(
        'label' => 'Diagnostyka',
        'icon' => '🔍'
    )
);

$historical_stats = IndexFixer_Database::get_historical_stats(30);
$trend_stats = IndexFixer_Database::get_trend_stats();
?>

<div class="wrap indexfixer-dashboard">
    <h1>IndexFixer</h1>
    
    <?php if (isset($_GET['auth']) && $_GET['auth'] === 'success'): ?>
        <div class="notice notice-success">
            <p>Autoryzacja zakończona sukcesem!</p>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_GET['auth']) && $_GET['auth'] === 'error'): ?>
        <div class="notice notice-error">
            <p>Wystąpił błąd podczas autoryzacji.</p>
        </div>
    <?php endif; ?>
    
    <!-- Nawigacja zakładek -->
    <nav class="nav-tab-wrapper">
        <?php foreach ($tabs as $tab_id => $tab): ?>
            <a href="?page=indexfixer&tab=<?php echo esc_attr($tab_id); ?>" 
               class="nav-tab <?php echo $current_tab === $tab_id ? 'nav-tab-active' : ''; ?>">
                <?php echo esc_html($tab['icon'] . ' ' . $tab['label']); ?>
            </a>
        <?php endforeach; ?>
    </nav>
    
    <!-- Zawartość zakładki -->
    <div class="tab-content">
        <?php
        $tab_file = INDEXFIXER_PLUGIN_DIR . 'templates/tabs/' . $current_tab . '.php';
        
        if (file_exists($tab_file)) {
            include $tab_file;
        } else {
            echo '<div class="notice notice-error"><p>Nie znaleziono zawartości dla wybranej zakładki.</p></div>';
        }
        ?>
    </div>
</div>

<style>
.indexfixer-dashboard {
    margin: 20px;
}

.indexfixer-dashboard .nav-tab-wrapper {
    margin-bottom: 20px;
}

.indexfixer-dashboard .nav-tab {
    font-size: 14px;
    padding: 8px 15px;
}

.indexfixer-dashboard .tab-content {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-top: none;
    padding: 20px;
}

/* Responsywność */
@media screen and (max-width: 782px) {
    .indexfixer-dashboard .nav-tab {
        padding: 8px 10px;
        font-size: 13px;
    }
}
</style>

<!-- Debug script -->
<script>
    // Debug informacje
    document.addEventListener('DOMContentLoaded', function() {
        const chartStatus = document.getElementById('chart-status');
        const statsStatus = document.getElementById('stats-status');
        
        if (typeof Chart !== 'undefined') {
            chartStatus.textContent = 'OK';
            chartStatus.style.color = '#46b450';
        } else {
            chartStatus.textContent = 'Błąd';
            chartStatus.style.color = '#dc3232';
        }
        
        if (typeof indexfixer_stats !== 'undefined') {
            statsStatus.textContent = 'OK';
            statsStatus.style.color = '#46b450';
        } else {
            statsStatus.textContent = 'Brak danych';
            statsStatus.style.color = '#dc3232';
        }
    });
</script>

<!-- NOWA SEKCJA: Historyczne statystyki i trendy -->

<?php 
// DEBUG: Sprawdź zmienne
echo "<!-- DEBUG: historical_stats = " . (isset($historical_stats) ? count($historical_stats) : 'UNDEFINED') . " -->";
echo "<!-- DEBUG: trend_stats = " . (isset($trend_stats) ? 'SET' : 'UNDEFINED') . " -->";
?>

<div style="margin: 30px 0; padding: 20px; background: #fff; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
    <h2 style="margin-top: 0; margin-bottom: 20px; color: #23282d;">📈 Historyczne Statystyki (Ostatnie 30 dni)</h2>
    <?php if ($trend_stats): ?>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 30px; padding: 15px; background: #f8f9fa; border-radius: 6px;">
        <div style="text-align: center;">
            <div style="font-size: 24px; font-weight: bold; color: <?php echo $trend_stats['indexed_change'] >= 0 ? '#46b450' : '#dc3232'; ?>;">
                <?php echo $trend_stats['indexed_change'] >= 0 ? '+' : ''; ?><?php echo $trend_stats['indexed_change']; ?>
            </div>
            <div style="font-size: 14px; color: #666;">Zmiana zaindeksowanych (vs wczoraj)</div>
        </div>
        <div style="text-align: center;">
            <div style="font-size: 24px; font-weight: bold; color: <?php echo $trend_stats['not_indexed_change'] <= 0 ? '#46b450' : '#dc3232'; ?>;">
                <?php echo $trend_stats['not_indexed_change'] >= 0 ? '+' : ''; ?><?php echo $trend_stats['not_indexed_change']; ?>
            </div>
            <div style="font-size: 14px; color: #666;">Zmiana niezaindeksowanych (vs wczoraj)</div>
        </div>
        <div style="text-align: center;">
            <div style="font-size: 24px; font-weight: bold; color: #0073aa;">
                <?php echo $trend_stats['new_indexed_today']; ?>
            </div>
            <div style="font-size: 14px; color: #666;">Nowo zaindeksowanych dzisiaj</div>
        </div>
        <div style="text-align: center;">
            <div style="font-size: 24px; font-weight: bold; color: #ffb900;">
                <?php echo $trend_stats['status_changes_today']; ?>
            </div>
            <div style="font-size: 14px; color: #666;">Zmian statusów dzisiaj</div>
        </div>
    </div>
    <div style="margin-bottom: 30px;">
        <canvas id="trend-chart" height="300"></canvas>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var ctx = document.getElementById('trend-chart').getContext('2d');
        var labels = <?php echo json_encode(array_map(function($row){return date('d.m.Y', strtotime($row->date_recorded));}, $historical_stats)); ?>;
        var indexed = <?php echo json_encode(array_map(function($row){return (int)$row->indexed;}, $historical_stats)); ?>;
        var notIndexed = <?php echo json_encode(array_map(function($row){return (int)$row->not_indexed;}, $historical_stats)); ?>;
        var discovered = <?php echo json_encode(array_map(function($row){return (int)$row->discovered;}, $historical_stats)); ?>;
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Zaindeksowane',
                        data: indexed,
                        borderColor: '#46b450',
                        backgroundColor: 'rgba(70, 180, 80, 0.1)',
                        tension: 0.3,
                        fill: false
                    },
                    {
                        label: 'Niezaindeksowane',
                        data: notIndexed,
                        borderColor: '#dc3232',
                        backgroundColor: 'rgba(220, 50, 50, 0.1)',
                        tension: 0.3,
                        fill: false
                    },
                    {
                        label: 'Odkryte',
                        data: discovered,
                        borderColor: '#ffb900',
                        backgroundColor: 'rgba(255, 185, 0, 0.1)',
                        tension: 0.3,
                        fill: false
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return value.toLocaleString();
                            }
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: true,
                        position: 'top'
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': ' + context.parsed.y.toLocaleString();
                            }
                        }
                    }
                }
            }
        });
    });
    </script>
    <?php endif; ?>
    <h3 style="margin-top: 40px;">Pełna Historia Statystyk</h3>
    <div style="max-height: 500px; overflow-y: auto; border: 1px solid #e1e5e9; border-radius: 4px;">
        <table class="wp-list-table widefat fixed striped">
            <thead style="position: sticky; top: 0; background: #f1f1f1; z-index: 10;">
                <tr>
                    <th style="width: 12%;">Data</th>
                    <th style="width: 10%;">Łącznie</th>
                    <th style="width: 12%;">Zaindeksowane</th>
                    <th style="width: 14%;">Niezaindeksowane</th>
                    <th style="width: 12%;">Odkryte</th>
                    <th style="width: 12%;">Nowe dzisiaj</th>
                    <th style="width: 14%;">Zmiany statusów</th>
                    <th style="width: 14%;">Procent indeksowania</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach (array_reverse($historical_stats) as $day_stats): ?>
                    <tr>
                        <td><?php echo esc_html(date('d.m.Y', strtotime($day_stats->date_recorded))); ?></td>
                        <td><?php echo (int)$day_stats->total_urls; ?></td>
                        <td style="color: #46b450; font-weight: bold;"><?php echo (int)$day_stats->indexed; ?></td>
                        <td style="color: #dc3232; font-weight: bold;"><?php echo (int)$day_stats->not_indexed; ?></td>
                        <td style="color: #ffb900; font-weight: bold;"><?php echo (int)$day_stats->discovered; ?></td>
                        <td style="color: #0073aa; font-weight: bold;">
                            <?php echo (int)$day_stats->new_indexed_today > 0 ? '+' . (int)$day_stats->new_indexed_today . '✅' : (int)$day_stats->new_indexed_today; ?>
                        </td>
                        <td><?php echo (int)$day_stats->status_changes_today; ?></td>
                        <td style="color: #dc3232; font-weight: bold;">
                            <?php echo $day_stats->total_urls > 0 ? round(($day_stats->indexed / $day_stats->total_urls) * 100, 1) : 0; ?>%
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- SEKCJA LOGÓW -->
<div style="margin: 30px 0; padding: 20px; background: #fff; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
    <h2 style="margin-top: 0; margin-bottom: 20px; color: #23282d;">📋 Ostatnie Logi (50 najnowszych)</h2>
    
    <?php
    $logs = IndexFixer_Logger::get_logs(50);
    if (!empty($logs)):
    ?>
        <div style="max-height: 400px; overflow-y: auto; background: #f8f9fa; padding: 15px; border: 1px solid #e1e5e9; border-radius: 4px; font-family: monospace; font-size: 13px; line-height: 1.4;">
            <?php foreach ($logs as $log): ?>
                <div style="margin-bottom: 8px; padding: 6px 10px; background: #fff; border-left: 3px solid <?php 
                    echo $log['type'] === 'error' ? '#dc3232' : 
                        ($log['type'] === 'success' ? '#46b450' : 
                        ($log['type'] === 'warning' ? '#ffb900' : '#0073aa')); 
                ?>; border-radius: 3px;">
                    <span style="color: #666; font-size: 11px;"><?php echo esc_html($log['timestamp']); ?></span>
                    <span style="color: <?php 
                        echo $log['type'] === 'error' ? '#dc3232' : 
                            ($log['type'] === 'success' ? '#46b450' : 
                            ($log['type'] === 'warning' ? '#ffb900' : '#23282d')); 
                    ?>; font-weight: 500; margin-left: 10px;">
                        <?php echo esc_html($log['message']); ?>
                    </span>
                </div>
            <?php endforeach; ?>
        </div>
        
        <div style="margin-top: 15px; text-align: center;">
            <button type="button" onclick="location.reload()" class="button button-secondary">
                🔄 Odśwież logi
            </button>
            <button type="button" onclick="clearLogs()" class="button button-secondary" style="margin-left: 10px;">
                🗑️ Wyczyść logi
            </button>
        </div>
    <?php else: ?>
        <div style="text-align: center; padding: 40px; color: #666; background: #f8f9fa; border: 1px solid #e1e5e9; border-radius: 4px;">
            <p style="margin: 0; font-size: 16px;">📝 Brak logów do wyświetlenia</p>
            <p style="margin: 10px 0 0 0; font-size: 14px;">Logi pojawią się po uruchomieniu sprawdzania URL-ów</p>
        </div>
    <?php endif; ?>
</div>

<script>
function clearLogs() {
    if (confirm('Czy na pewno chcesz wyczyścić wszystkie logi?')) {
        fetch(ajaxurl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                action: 'indexfixer_clear_logs',
                nonce: '<?php echo wp_create_nonce('indexfixer_clear_logs'); ?>'
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Błąd podczas czyszczenia logów: ' + data.data);
            }
        })
        .catch(error => {
            alert('Błąd: ' + error.message);
        });
    }
}

function saveDailyStats() {
    const button = event.target;
    const originalText = button.textContent;
    button.textContent = '⏳ Zapisywanie...';
    button.disabled = true;
    
    fetch(ajaxurl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
            action: 'indexfixer_save_daily_stats',
            nonce: '<?php echo wp_create_nonce('indexfixer_save_stats'); ?>'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('✅ ' + data.data.message);
            location.reload();
        } else {
            alert('❌ ' + data.data);
        }
    })
    .catch(error => {
        alert('❌ Błąd: ' + error.message);
    })
    .finally(() => {
        button.textContent = originalText;
        button.disabled = false;
    });
}

function testStatsCron() {
    const button = event.target;
    const originalText = button.textContent;
    button.textContent = '⏳ Testowanie...';
    button.disabled = true;
    
    fetch(ajaxurl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
            action: 'indexfixer_test_stats_cron',
            nonce: '<?php echo wp_create_nonce('indexfixer_nonce'); ?>'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('✅ ' + data.data.message + '\n\nLogi zostały dodane - odśwież stronę aby je zobaczyć.');
            location.reload();
        } else {
            alert('❌ ' + data.data);
        }
    })
    .catch(error => {
        alert('❌ Błąd: ' + error.message);
    })
    .finally(() => {
        button.textContent = originalText;
        button.disabled = false;
    });
}
</script>

<!-- Koniec dashboard -->