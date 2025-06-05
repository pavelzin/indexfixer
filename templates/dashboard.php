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
    
    <div class="indexfixer-single-check">
        <h2>Sprawdź pojedynczy URL</h2>
        <div class="single-url-form">
            <input type="url" id="single-url-input" placeholder="https://example.com/strona/" class="regular-text">
            <button type="button" id="check-single-url" class="button button-primary">
                Sprawdź URL
            </button>
            <span id="single-url-loading" style="display: none;">Sprawdzam...</span>
        </div>
        <div id="single-url-result" style="display: none; margin-top: 15px;"></div>
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
                    <th style="width: 25%;">URL</th>
                    <th style="width: 15%;">Verdict</th>
                    <th style="width: 20%;">Coverage State</th>
                    <th style="width: 10%;">Robots.txt</th>
                    <th style="width: 10%;">Ostatni crawl</th>
                    <th style="width: 10%;">Typ</th>
                    <th style="width: 10%;">Data publikacji</th>
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
                            <a href="<?php echo esc_url($url_data['url']); ?>" target="_blank">
                                <?php echo esc_html($url_data['url']); ?>
                            </a>
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