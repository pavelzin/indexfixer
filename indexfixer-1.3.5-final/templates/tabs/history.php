<?php
/**
 * Zakładka Historia - analiza zmian statusów URL-ów w czasie
 */

if (!defined('ABSPATH')) {
    exit;
}

// Pobierz dane historyczne
$indexing_stats = IndexFixer_Database::get_indexing_time_stats(null, 30);
$trends = IndexFixer_Database::get_indexing_trends(30);
$dropped_urls = IndexFixer_Database::get_dropped_urls(7);

// Pobierz ostatnie zmiany statusów
$recent_changes = IndexFixer_Database::get_recent_status_changes(10);

// Obsługa AJAX dla timeline konkretnego URL-a
$selected_url_timeline = array();
if (isset($_GET['timeline_url']) && !empty($_GET['timeline_url'])) {
    $timeline_url = sanitize_url($_GET['timeline_url']);
    $selected_url_timeline = IndexFixer_Database::get_url_history($timeline_url);
}
?>

<div class="indexfixer-history-tab">
    <h2 style="font-size: 24px; margin-bottom: 24px; display: flex; align-items: center; gap: 12px;">
        <span style="font-size: 28px;">📈</span> 
        Historia i analiza indeksacji
    </h2>
    
    <!-- Ostatnie zmiany statusów -->
    <?php if (!empty($recent_changes)): ?>
    <div class="indexfixer-recent-changes-section" style="background: #fff; padding: 24px; border-radius: 8px; border: 1px solid #e5e5e5; margin-bottom: 24px;">
        <h3 style="font-size: 18px; margin-bottom: 16px; display: flex; align-items: center; gap: 8px;">
            <span style="font-size: 20px;">🔄</span> Ostatnie zmiany statusów (10 najnowszych)
        </h3>
        
        <div style="overflow-x: auto;">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 15%;">Kiedy</th>
                        <th style="width: 25%;">URL</th>
                        <th style="width: 20%;">Tytuł</th>
                        <th style="width: 15%;">Nowy status</th>
                        <th style="width: 15%;">Poprzedni status</th>
                        <th style="width: 10%;">Typ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_changes as $change): ?>
                        <tr>
                            <td style="font-size: 12px;">
                                <?php echo esc_html(wp_date('Y-m-d H:i', strtotime($change->changed_at))); ?>
                            </td>
                            <td>
                                <a href="<?php echo esc_url($change->url); ?>" target="_blank" style="text-decoration: none; font-size: 12px;">
                                    <?php echo esc_html(wp_trim_words($change->url, 4)); ?>
                                </a>
                            </td>
                            <td style="font-size: 12px;">
                                <?php echo esc_html(wp_trim_words($change->post_title ?: 'Brak tytułu', 4)); ?>
                            </td>
                            <td>
                                <span class="status-badge status-<?php echo esc_attr(strtolower(str_replace(' ', '_', $change->coverage_state))); ?>" style="font-size: 10px;">
                                    <?php echo esc_html($change->coverage_state ?: 'Nieznany'); ?>
                                </span>
                            </td>
                            <td style="font-size: 11px; color: #666;">
                                <?php echo esc_html($change->previous_coverage_state ?: 'Pierwszy wpis'); ?>
                            </td>
                            <td style="font-size: 11px;">
                                <?php 
                                switch($change->change_type) {
                                    case 'new': echo '🆕'; break;
                                    case 'status_change': echo '🔄'; break;
                                    default: echo '❓';
                                }
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div style="margin-top: 12px; padding: 8px; background: #f0f6fc; border-radius: 4px; font-size: 12px; color: #2c3e50;">
            💡 <strong>Ta sekcja pokazuje najnowsze zmiany statusów</strong> - idealnie widać czy system pracuje i monitoruje URL-e w czasie rzeczywistym.
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Statystyki czasów indeksacji -->
    <div class="indexfixer-stats-section" style="background: #fff; padding: 24px; border-radius: 8px; border: 1px solid #e5e5e5; margin-bottom: 24px;">
        <h3 style="font-size: 18px; margin-bottom: 16px; display: flex; align-items: center; gap: 8px;">
            <span style="font-size: 20px;">⏱️</span> Czasy indeksacji (ostatnie 30 dni)
        </h3>
        
        <?php if (!empty($indexing_stats)): ?>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 16px;">
                <?php foreach ($indexing_stats as $stat): ?>
                    <div style="background: #f8f9fa; padding: 16px; border-radius: 6px; border-left: 4px solid #0073aa;">
                        <div style="font-weight: bold; margin-bottom: 8px;">
                            <?php echo esc_html($stat->post_type ?: 'Wszystkie typy'); ?>
                        </div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                            <span>Średnio:</span>
                            <strong><?php echo round($stat->avg_days_to_index, 1); ?> dni</strong>
                        </div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                            <span>Najszybciej:</span>
                            <span><?php echo $stat->min_days_to_index; ?> dni</span>
                        </div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                            <span>Najwolniej:</span>
                            <span><?php echo $stat->max_days_to_index; ?> dni</span>
                        </div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                            <span>Sprawdzonych:</span>
                            <span><?php echo $stat->total_count; ?></span>
                        </div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                            <span>Zaindeksowanych:</span>
                            <span style="color: #46b450;"><?php echo $stat->indexed_count; ?></span>
                        </div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                            <span>Niezaindeksowanych:</span>
                            <span style="color: #dc3232;"><?php echo $stat->not_indexed_count; ?></span>
                        </div>
                        <div style="display: flex; justify-content: space-between;">
                            <span>Nieznanych:</span>
                            <span style="color: #999;"><?php echo $stat->unknown_count; ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p style="color: #666; font-style: italic;">Brak danych o indeksacji z ostatnich 30 dni.</p>
        <?php endif; ?>
    </div>
    
    <!-- Trendy indeksacji -->
    <?php if ($trends['current'] && $trends['previous']): ?>
    <div class="indexfixer-trends-section" style="background: #fff; padding: 24px; border-radius: 8px; border: 1px solid #e5e5e5; margin-bottom: 24px;">
        <h3 style="font-size: 18px; margin-bottom: 16px; display: flex; align-items: center; gap: 8px;">
            <span style="font-size: 20px;">📊</span> Trendy indeksacji
        </h3>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px;">
            <div style="background: #f8f9fa; padding: 16px; border-radius: 6px;">
                <div style="font-weight: bold; margin-bottom: 8px;">Ostatnie 30 dni</div>
                <div>Średnio: <strong><?php echo round($trends['current']->avg_days, 1); ?> dni</strong></div>
                <div>Zaindeksowano: <strong><?php echo $trends['current']->count; ?></strong></div>
            </div>
            <div style="background: #f8f9fa; padding: 16px; border-radius: 6px;">
                <div style="font-weight: bold; margin-bottom: 8px;">Poprzednie 30 dni</div>
                <div>Średnio: <strong><?php echo round($trends['previous']->avg_days, 1); ?> dni</strong></div>
                <div>Zaindeksowano: <strong><?php echo $trends['previous']->count; ?></strong></div>
            </div>
            <div style="background: #f8f9fa; padding: 16px; border-radius: 6px; border-left: 4px solid <?php echo $trends['trend'] < 0 ? '#46b450' : '#dc3232'; ?>;">
                <div style="font-weight: bold; margin-bottom: 8px;">Trend</div>
                <div style="color: <?php echo $trends['trend'] < 0 ? '#46b450' : '#dc3232'; ?>;">
                    <?php if ($trends['trend'] < 0): ?>
                        📈 Indeksacja przyspieszyła o <?php echo abs(round($trends['trend'], 1)); ?> dni
                    <?php else: ?>
                        📉 Indeksacja zwolniła o <?php echo round($trends['trend'], 1); ?> dni
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- URL-e które wypadły z indeksu -->
    <?php if (!empty($dropped_urls)): ?>
    <div class="indexfixer-dropped-section" style="background: #fff; padding: 24px; border-radius: 8px; border: 1px solid #e5e5e5; margin-bottom: 24px;">
        <h3 style="font-size: 18px; margin-bottom: 16px; display: flex; align-items: center; gap: 8px;">
            <span style="font-size: 20px;">⚠️</span> URL-e które wypadły z indeksu (ostatnie 7 dni)
        </h3>
        
        <div style="overflow-x: auto;">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>URL</th>
                        <th>Tytuł</th>
                        <th>Typ</th>
                        <th>Poprzedni status</th>
                        <th>Nowy status</th>
                        <th>Kiedy</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($dropped_urls as $dropped): ?>
                        <tr>
                            <td>
                                <a href="<?php echo esc_url($dropped->url); ?>" target="_blank" style="text-decoration: none;">
                                    <?php echo esc_html(wp_trim_words($dropped->url, 6)); ?>
                                </a>
                            </td>
                            <td><?php echo esc_html($dropped->post_title ?: 'Brak tytułu'); ?></td>
                            <td><?php echo esc_html($dropped->post_type ?: 'Nieznany'); ?></td>
                            <td style="color: #46b450;"><?php echo esc_html($dropped->previous_coverage_state); ?></td>
                            <td style="color: #dc3232;"><?php echo esc_html($dropped->coverage_state); ?></td>
                            <td><?php echo esc_html(wp_date('Y-m-d H:i', strtotime($dropped->changed_at))); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Timeline dla konkretnego URL-a -->
    <div class="indexfixer-timeline-section" style="background: #fff; padding: 24px; border-radius: 8px; border: 1px solid #e5e5e5; margin-bottom: 24px;">
        <h3 style="font-size: 18px; margin-bottom: 16px; display: flex; align-items: center; gap: 8px;">
            <span style="font-size: 20px;">🕐</span> Timeline URL-a
        </h3>
        
        <form method="get" style="margin-bottom: 20px;">
            <input type="hidden" name="page" value="indexfixer">
            <input type="hidden" name="tab" value="history">
            <div style="display: flex; gap: 12px; align-items: center;">
                <label for="timeline_url" style="font-weight: bold;">URL do analizy:</label>
                <input type="url" 
                       id="timeline_url" 
                       name="timeline_url" 
                       value="<?php echo esc_attr($timeline_url ?? ''); ?>"
                       placeholder="https://example.com/artykul"
                       style="flex: 1; max-width: 400px; padding: 8px;">
                <button type="submit" class="button button-primary">Pokaż historię</button>
            </div>
        </form>
        
        <?php if (!empty($selected_url_timeline)): ?>
            <div style="border-left: 4px solid #0073aa; padding-left: 16px;">
                <h4 style="margin-bottom: 16px;">Historia URL-a: <?php echo esc_html($timeline_url); ?></h4>
                
                <div style="overflow-x: auto;">
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>Data</th>
                                <th>Status</th>
                                <th>Coverage State</th>
                                <th>Verdict</th>
                                <th>Typ zmiany</th>
                                <th>Dni od publikacji</th>
                                <th>Dni od ost. zmiany</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($selected_url_timeline as $entry): ?>
                                <tr>
                                    <td><?php echo esc_html(wp_date('Y-m-d H:i', strtotime($entry->changed_at))); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo esc_attr(strtolower($entry->status)); ?>">
                                            <?php echo esc_html($entry->status); ?>
                                        </span>
                                    </td>
                                    <td><?php echo esc_html($entry->coverage_state ?: 'Nieznany'); ?></td>
                                    <td>
                                        <?php if ($entry->verdict): ?>
                                            <span class="verdict-<?php echo esc_attr(strtolower($entry->verdict)); ?>">
                                                <?php echo esc_html($entry->verdict); ?>
                                            </span>
                                        <?php else: ?>
                                            <span style="color: #999;">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="change-type-<?php echo esc_attr($entry->change_type); ?>">
                                            <?php 
                                            switch($entry->change_type) {
                                                case 'new': echo '🆕 Nowy'; break;
                                                case 'status_change': echo '🔄 Zmiana'; break;
                                                case 'recheck': echo '🔍 Ponowna'; break;
                                                default: echo esc_html($entry->change_type);
                                            }
                                            ?>
                                        </span>
                                    </td>
                                    <td><?php echo $entry->days_since_publish !== null ? $entry->days_since_publish . ' dni' : '-'; ?></td>
                                    <td><?php echo $entry->days_since_last_change !== null ? $entry->days_since_last_change . ' dni' : '-'; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php elseif (isset($timeline_url)): ?>
            <p style="color: #666; font-style: italic;">Brak danych historycznych dla podanego URL-a.</p>
        <?php endif; ?>
    </div>
</div>

<style>
.status-badge {
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: bold;
    text-transform: uppercase;
}

.status-indexed { background: #d4edda; color: #155724; }
.status-not_indexed { background: #f8d7da; color: #721c24; }
.status-pending { background: #fff3cd; color: #856404; }
.status-unknown { background: #e2e3e5; color: #383d41; }
.status-submitted_and_indexed { background: #d4edda; color: #155724; }
.status-url_is_unknown_to_google { background: #e2e3e5; color: #383d41; }
.status-url_is_not_on_google { background: #f8d7da; color: #721c24; }

.verdict-pass { color: #46b450; font-weight: bold; }
.verdict-neutral { color: #0073aa; font-weight: bold; }
.verdict-fail { color: #dc3232; font-weight: bold; }

.change-type-new { color: #46b450; }
.change-type-status_change { color: #0073aa; }
.change-type-recheck { color: #666; }
</style> 