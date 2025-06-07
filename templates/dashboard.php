<?php
/**
 * Szablon dashboardu
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
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
    
    <!-- Statystyki -->
    <div style="margin-bottom: 20px; padding: 20px; background: #fff; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
        <h2 style="margin-top: 0; margin-bottom: 20px; color: #23282d;">📊 Statystyki URL-ów</h2>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 30px;">
            <div style="padding: 20px; background: #f8f9fa; border: 1px solid #e1e5e9; border-left: 4px solid #0073aa; border-radius: 4px; text-align: center;">
                <div style="font-size: 36px; font-weight: bold; margin-bottom: 8px; color: #23282d;"><?php echo $stats['total']; ?></div>
                <div style="font-size: 14px; color: #666; margin-bottom: 5px;">Łącznie URL-ów</div>
            </div>
            
            <div style="padding: 20px; background: #f8f9fa; border: 1px solid #e1e5e9; border-left: 4px solid #0085ba; border-radius: 4px; text-align: center;">
                <div style="font-size: 36px; font-weight: bold; margin-bottom: 8px; color: #23282d;"><?php echo $stats['checked']; ?></div>
                <div style="font-size: 14px; color: #666; margin-bottom: 5px;">Sprawdzonych</div>
                <div style="font-size: 12px; color: #999; font-weight: 500;"><?php echo $stats['total'] > 0 ? round(($stats['checked'] / $stats['total']) * 100, 1) : 0; ?>%</div>
            </div>
            
            <div style="padding: 20px; background: #f8f9fa; border: 1px solid #e1e5e9; border-left: 4px solid #46b450; border-radius: 4px; text-align: center;">
                <div style="font-size: 36px; font-weight: bold; margin-bottom: 8px; color: #23282d;"><?php echo $stats['indexed']; ?></div>
                <div style="font-size: 14px; color: #666; margin-bottom: 5px;">Zaindeksowanych</div>
                <div style="font-size: 12px; color: #999; font-weight: 500;"><?php echo $stats['total'] > 0 ? round(($stats['indexed'] / $stats['total']) * 100, 1) : 0; ?>%</div>
            </div>
            
            <div style="padding: 20px; background: #f8f9fa; border: 1px solid #e1e5e9; border-left: 4px solid #dc3232; border-radius: 4px; text-align: center;">
                <div style="font-size: 36px; font-weight: bold; margin-bottom: 8px; color: #23282d;"><?php echo $stats['not_indexed']; ?></div>
                <div style="font-size: 14px; color: #666; margin-bottom: 5px;">Nie zaindeksowanych</div>
                <div style="font-size: 12px; color: #999; font-weight: 500;"><?php echo $stats['total'] > 0 ? round(($stats['not_indexed'] / $stats['total']) * 100, 1) : 0; ?>%</div>
            </div>
            
            <div style="padding: 20px; background: #f8f9fa; border: 1px solid #e1e5e9; border-left: 4px solid #ffb900; border-radius: 4px; text-align: center;">
                <div style="font-size: 36px; font-weight: bold; margin-bottom: 8px; color: #23282d;"><?php echo $stats['discovered']; ?></div>
                <div style="font-size: 14px; color: #666; margin-bottom: 5px;">Odkrytych</div>
                <div style="font-size: 12px; color: #999; font-weight: 500;"><?php echo $stats['total'] > 0 ? round(($stats['discovered'] / $stats['total']) * 100, 1) : 0; ?>%</div>
            </div>
            
            <div style="padding: 20px; background: #f8f9fa; border: 1px solid #e1e5e9; border-left: 4px solid #666; border-radius: 4px; text-align: center;">
                <div style="font-size: 36px; font-weight: bold; margin-bottom: 8px; color: #23282d;"><?php echo $stats['excluded']; ?></div>
                <div style="font-size: 14px; color: #666; margin-bottom: 5px;">Wykluczonych</div>
                <div style="font-size: 12px; color: #999; font-weight: 500;"><?php echo $stats['total'] > 0 ? round(($stats['excluded'] / $stats['total']) * 100, 1) : 0; ?>%</div>
            </div>
        </div>
        
        <!-- Wykresy -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-top: 20px;">
            <div style="text-align: center; padding: 20px; background: #f8f9fa; border-radius: 4px; border: 1px solid #e1e5e9; width: 350px; height: 400px; overflow: hidden;">
                <h3 style="margin-top: 0; margin-bottom: 15px; color: #23282d; font-size: 16px;">Status indeksowania</h3>
                <div style="width: 300px; height: 300px; margin: 0 auto; border: 1px solid #ccc;">
                    <canvas id="indexing-chart" width="300" height="300" style="width: 300px !important; height: 300px !important; display: block; background: #fff;"></canvas>
                </div>
            </div>
            
            <div style="text-align: center; padding: 20px; background: #f8f9fa; border-radius: 4px; border: 1px solid #e1e5e9; width: 350px; height: 400px; overflow: hidden;">
                <h3 style="margin-top: 0; margin-bottom: 15px; color: #23282d; font-size: 16px;">Verdict Google</h3>
                <div style="width: 300px; height: 300px; margin: 0 auto; border: 1px solid #ccc;">
                    <canvas id="verdict-chart" width="300" height="300" style="width: 300px !important; height: 300px !important; display: block; background: #fff;"></canvas>
                </div>
                
                <!-- Debug info -->
                <div id="debug-info" style="margin-top: 10px; font-size: 12px; color: #666;">
                    <div>Chart.js: <span id="chart-status">Sprawdzam...</span></div>
                    <div>Stats: <span id="stats-status">Sprawdzam...</span></div>
                </div>
            </div>
        </div>
        
        <!-- Debug script -->
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Debug info
            document.getElementById('chart-status').textContent = typeof Chart !== 'undefined' ? 'OK' : 'BRAK';
            document.getElementById('stats-status').textContent = typeof indexfixer_stats !== 'undefined' ? 'OK' : 'BRAK';
            
            console.log('=== DEBUG WYKRESÓW ===');
            console.log('Chart.js loaded:', typeof Chart !== 'undefined');
            console.log('indexfixer_stats:', typeof indexfixer_stats !== 'undefined' ? indexfixer_stats : 'UNDEFINED');
            
            // Sprawdź czy canvas istnieją
            const canvas1 = document.getElementById('indexing-chart');
            const canvas2 = document.getElementById('verdict-chart');
            console.log('Canvas 1:', canvas1 ? 'OK' : 'BRAK');
            console.log('Canvas 2:', canvas2 ? 'OK' : 'BRAK');
            
            // Jeśli Chart.js nie działa, pokaż fallback
            if (typeof Chart === 'undefined') {
                if (canvas1) canvas1.getContext('2d').fillText('Chart.js nie załadowany', 50, 150);
                if (canvas2) canvas2.getContext('2d').fillText('Chart.js nie załadowany', 50, 150);
            }
        });
        </script>
    </div>

    <div class="indexfixer-filters">
        <h2>Wszystkie URL-e</h2>
        <select id="status-filter">
            <option value="">Wszystkie statusy</option>
            <option value="PASS">Verdict: PASS</option>
            <option value="NEUTRAL">Verdict: NEUTRAL</option>
            <option value="FAIL">Verdict: FAIL</option>
            <option value="Submitted and indexed">Zaindeksowane</option>
            <option value="Crawled - currently not indexed">Nie zaindeksowane</option>
            <option value="Discovered - currently not indexed">Odkryte</option>
        </select>
        
        <select id="robots-filter">
            <option value="">Wszystkie robots.txt</option>
            <option value="ALLOWED">Dozwolone</option>
            <option value="DISALLOWED">Zablokowane</option>
        </select>
        
        <button type="button" id="refresh-data" class="button">
            Odśwież dane
        </button>
        
        <button type="button" id="export-csv" class="button">
            Eksportuj do CSV
        </button>
    </div>
    
    <?php if (empty($urls)): ?>
        <div class="indexfixer-message warning">
            Nie znaleziono żadnych URL-i do sprawdzenia.
        </div>
    <?php else: ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width: 20%;">URL</th>
                    <th style="width: 10%;">Verdict</th>
                    <th style="width: 15%;">Coverage State</th>
                    <th style="width: 8%;">Robots.txt</th>
                    <th style="width: 10%;">Ostatni crawl</th>
                    <th style="width: 12%;">Ostatnie sprawdzenie API</th>
                    <th style="width: 8%;">Typ</th>
                    <th style="width: 12%;">Data publikacji</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($urls as $url_data): ?>
                    <?php
                    $status_data = isset($url_statuses[$url_data['url']]) 
                        ? $url_statuses[$url_data['url']] 
                        : array('simple_status' => 'unknown');
                    
                    // Jeśli to stary format (string), przekonwertuj na nowy
                    if (!is_array($status_data)) {
                        $status_data = array('simple_status' => $status_data);
                    }
                    
                    $simple_status = isset($status_data['simple_status']) ? $status_data['simple_status'] : 'unknown';
                    ?>
                    <tr data-status="<?php echo esc_attr($simple_status); ?>">
                        <td>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <span style="flex: 1; word-break: break-all;">
                                    <?php echo esc_html($url_data['url']); ?>
                                </span>
                                <button type="button" 
                                        class="button button-small check-single-url" 
                                        data-url="<?php echo esc_attr($url_data['url']); ?>"
                                        title="Sprawdź status tego URL"
                                        style="flex-shrink: 0;">
                                    🔄
                                </button>
                            </div>
                        </td>
                        <td>
                            <?php if (isset($status_data['verdict']) && $status_data['verdict'] !== 'unknown'): ?>
                                <span class="verdict-<?php echo esc_attr(strtolower($status_data['verdict'])); ?>" 
                                      title="<?php echo esc_attr(IndexFixer_Helpers::format_verdict($status_data['verdict'])); ?>">
                                    <?php echo esc_html($status_data['verdict']); ?>
                                </span>
                                <?php if (isset($status_data['indexingState']) && $status_data['indexingState'] !== 'unknown'): ?>
                                    <br><small class="<?php echo $status_data['indexingState'] == 'INDEXING_ALLOWED' ? 'good' : 'bad'; ?>">
                                        <?php echo esc_html($status_data['indexingState']); ?>
                                    </small>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="status-unknown">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (isset($status_data['coverageState']) && $status_data['coverageState'] !== 'unknown'): ?>
                                <span class="coverage-state" title="<?php echo esc_attr(IndexFixer_Helpers::format_coverage_state($status_data['coverageState'])); ?>">
                                    <?php echo esc_html($status_data['coverageState']); ?>
                                </span>
                            <?php else: ?>
                                <span class="status-unknown">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (isset($status_data['robotsTxtState']) && $status_data['robotsTxtState'] !== 'unknown'): ?>
                                <?php 
                                $tooltip_parts = array();
                                if (isset($status_data['pageFetchState']) && $status_data['pageFetchState'] !== 'unknown') {
                                    $tooltip_parts[] = 'pageFetchState: ' . $status_data['pageFetchState'];
                                }
                                if (isset($status_data['crawledAs']) && $status_data['crawledAs'] !== 'unknown') {
                                    $tooltip_parts[] = 'Crawled as: ' . $status_data['crawledAs'];
                                }
                                $tooltip = implode(', ', $tooltip_parts);
                                ?>
                                <span class="<?php echo $status_data['robotsTxtState'] == 'ALLOWED' ? 'good' : 'bad'; ?>"
                                      title="<?php echo esc_attr($tooltip); ?>">
                                    <?php echo esc_html($status_data['robotsTxtState']); ?>
                                </span>
                            <?php else: ?>
                                <span class="status-unknown">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (isset($status_data['lastCrawlTime']) && $status_data['lastCrawlTime'] !== 'unknown'): ?>
                                <?php 
                                $crawl_date = date('Y-m-d', strtotime($status_data['lastCrawlTime']));
                                echo esc_html($crawl_date);
                                ?>
                            <?php else: ?>
                                <span class="status-unknown">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (isset($status_data['lastChecked']) && $status_data['lastChecked'] !== 'unknown'): ?>
                                <?php 
                                $checked_date = date('Y-m-d H:i', strtotime($status_data['lastChecked']));
                                $time_ago = human_time_diff(strtotime($status_data['lastChecked']), current_time('timestamp'));
                                ?>
                                <span title="<?php echo esc_attr($status_data['lastChecked']); ?>">
                                    <?php echo esc_html($checked_date); ?><br>
                                    <small style="color: #666;"><?php echo esc_html($time_ago); ?> temu</small>
                                </span>
                            <?php else: ?>
                                <span class="status-unknown">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php echo esc_html($url_data['post_type']); ?>
                        </td>
                        <td>
                            <?php echo esc_html($url_data['post_date']); ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
    
    <div class="indexfixer-logs">
        <h2>Logi</h2>
        <div id="indexfixer-logs-content">
            <?php echo IndexFixer_Logger::format_logs(); ?>
        </div>
    </div>
</div>

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

<?php if (!empty($historical_stats)): ?>
<div style="margin: 30px 0; padding: 20px; background: #fff; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
    <h2 style="margin-top: 0; margin-bottom: 20px; color: #23282d;">📈 Historyczne Statystyki (Ostatnie 30 dni)</h2>
    
    <!-- Statystyki trendu -->
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
    <?php endif; ?>
    
    <!-- Wykres trendu indeksowania -->
    <div style="margin-bottom: 30px;">
        <h3 style="margin-bottom: 15px; color: #23282d;">Trend Indeksowania</h3>
        <div style="position: relative; height: 400px; background: #fff; border: 1px solid #e1e5e9; border-radius: 4px; padding: 20px;">
            <canvas id="trend-chart" style="width: 100%; height: 100%;"></canvas>
        </div>
    </div>
    
    <!-- Tabela historyczna -->
    <div>
        <h3 style="margin-bottom: 15px; color: #23282d;">Pełna Historia Statystyk</h3>
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
                    <td><strong><?php echo date('d.m.Y', strtotime($day_stats->date_recorded)); ?></strong></td>
                    <td><?php echo number_format($day_stats->total_urls); ?></td>
                    <td style="color: #46b450; font-weight: bold;"><?php echo number_format($day_stats->indexed); ?></td>
                    <td style="color: #dc3232; font-weight: bold;"><?php echo number_format($day_stats->not_indexed); ?></td>
                    <td style="color: #ffb900;"><?php echo number_format($day_stats->discovered); ?></td>
                    <td style="color: #0073aa;">
                        <?php if ($day_stats->new_indexed_today > 0): ?>
                            +<?php echo $day_stats->new_indexed_today; ?> ✅
                        <?php else: ?>
                            0
                        <?php endif; ?>
                    </td>
                    <td><?php echo number_format($day_stats->status_changes_today); ?></td>
                    <td>
                        <?php 
                        $percentage = $day_stats->checked_urls > 0 ? round(($day_stats->indexed / $day_stats->checked_urls) * 100, 1) : 0;
                        $color = $percentage >= 70 ? '#46b450' : ($percentage >= 40 ? '#ffb900' : '#dc3232');
                        ?>
                        <span style="color: <?php echo $color; ?>; font-weight: bold;"><?php echo $percentage; ?>%</span>
                    </td>
                </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

<!-- Script dla wykresu trendu -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof Chart !== 'undefined') {
        const ctx = document.getElementById('trend-chart').getContext('2d');
        
        // Przygotuj dane historyczne
        const historicalData = <?php echo json_encode($historical_stats); ?>;
        
        const labels = historicalData.map(item => {
            const date = new Date(item.date_recorded);
            return date.toLocaleDateString('pl-PL', { month: 'short', day: 'numeric' });
        });
        
        const indexedData = historicalData.map(item => parseInt(item.indexed));
        const notIndexedData = historicalData.map(item => parseInt(item.not_indexed));
        const discoveredData = historicalData.map(item => parseInt(item.discovered));
        
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Zaindeksowane',
                        data: indexedData,
                        borderColor: '#46b450',
                        backgroundColor: 'rgba(70, 180, 80, 0.1)',
                        tension: 0.3,
                        fill: false
                    },
                    {
                        label: 'Niezaindeksowane',
                        data: notIndexedData,
                        borderColor: '#dc3232',
                        backgroundColor: 'rgba(220, 50, 50, 0.1)',
                        tension: 0.3,
                        fill: false
                    },
                    {
                        label: 'Odkryte',
                        data: discoveredData,
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
    }
});
</script>
<?php else: ?>
<div style="margin: 30px 0; padding: 20px; background: #fff; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
    <h2 style="margin-top: 0; margin-bottom: 20px; color: #23282d;">📈 Historyczne Statystyki</h2>
    <div style="text-align: center; padding: 40px; color: #666; background: #f8f9fa; border: 1px solid #e1e5e9; border-radius: 4px;">
        <p style="margin: 0; font-size: 16px;">📊 Brak danych historycznych</p>
        <p style="margin: 10px 0 0 0; font-size: 14px;">Statystyki pojawią się po pierwszym sprawdzaniu lub użyj przycisku "Zapisz Dzisiejsze Statystyki"</p>
        <button type="button" onclick="saveDailyStats()" class="button button-secondary" style="margin-top: 15px;">
            💾 Zapisz Dzisiejsze Statystyki
        </button>
        <button type="button" onclick="testStatsCron()" class="button button-secondary" style="margin-top: 15px; margin-left: 10px;">
            🧪 Testuj Cron Statystyk
        </button>
    </div>
</div>
<?php endif; ?>



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
                    echo $log['level'] === 'error' ? '#dc3232' : 
                        ($log['level'] === 'success' ? '#46b450' : 
                        ($log['level'] === 'warning' ? '#ffb900' : '#0073aa')); 
                ?>; border-radius: 3px;">
                    <span style="color: #666; font-size: 11px;"><?php echo esc_html($log['timestamp']); ?></span>
                    <span style="color: <?php 
                        echo $log['level'] === 'error' ? '#dc3232' : 
                            ($log['level'] === 'success' ? '#46b450' : 
                            ($log['level'] === 'warning' ? '#ffb900' : '#23282d')); 
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