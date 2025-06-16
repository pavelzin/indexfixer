<?php
/**
 * Zakładka "Lista URL-i" w dashboardzie
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('get_post_types')) {
    require_once ABSPATH . 'wp-includes/post.php';
}

// Pobierz parametry paginacji
$page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$per_page = isset($_GET['per_page']) ? max(10, min(100, intval($_GET['per_page']))) : 20;
$offset = ($page - 1) * $per_page;

// Pobierz filtry
$status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
$robots_filter = isset($_GET['robots']) ? sanitize_text_field($_GET['robots']) : '';
$post_type_filter = isset($_GET['post_type']) ? sanitize_text_field($_GET['post_type']) : '';

// Pobierz URL-e z bazy z paginacją i filtrami
$urls = array();
$total_urls = 0;
global $wpdb;
$table_name = $wpdb->prefix . 'indexfixer_urls';
$where = array();
$values = array();

if ($status_filter) {
    $where[] = '(verdict = %s OR coverage_state = %s)';
    $values[] = $status_filter;
    $values[] = $status_filter;
}
if ($robots_filter) {
    $where[] = 'robots_txt_state = %s';
    $values[] = $robots_filter;
}
if ($post_type_filter) {
    $where[] = 'p.post_type = %s';
    $values[] = $post_type_filter;
}

$sql = "SELECT u.*, p.post_type, p.post_date 
        FROM $table_name u 
        LEFT JOIN {$wpdb->posts} p ON u.post_id = p.ID";
if (!empty($where)) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql_count = str_replace('SELECT u.*, p.post_type, p.post_date', 'SELECT COUNT(*)', $sql);
$sql .= ' ORDER BY u.last_checked DESC LIMIT %d OFFSET %d';


if (!empty($where)) {
    // Jeśli są filtry, używaj prepare() z wartościami filtrów + limit/offset
    $values_with_limit = $values;
    $values_with_limit[] = $per_page;
    $values_with_limit[] = $offset;
    $urls = $wpdb->get_results($wpdb->prepare($sql, $values_with_limit), ARRAY_A);
    $total_urls = $wpdb->get_var($wpdb->prepare($sql_count, $values));
} else {
    // Jeśli nie ma filtrów, nie używaj prepare() i nie przekazuj żadnych wartości!
    $sql_no_filter = "SELECT * FROM $table_name ORDER BY last_checked DESC LIMIT $per_page OFFSET $offset";
    $urls = $wpdb->get_results($sql_no_filter, ARRAY_A);
    $total_urls = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
}
$total_pages = ceil($total_urls / $per_page);

// Pobierz dostępne typy postów
$post_types = get_post_types(['public' => true], 'objects');


?>

<div class="indexfixer-urls">
    <h2>🔗 Lista URL-i</h2>
    
    <!-- Filtry -->
    <div class="indexfixer-filters">
        <form method="get" action="">
            <input type="hidden" name="page" value="indexfixer">
            <input type="hidden" name="tab" value="urls">
            
            <select name="status" id="status-filter">
                <option value="">Wszystkie statusy</option>
                <option value="PASS" <?php selected($status_filter, 'PASS'); ?>>Verdict: PASS</option>
                <option value="NEUTRAL" <?php selected($status_filter, 'NEUTRAL'); ?>>Verdict: NEUTRAL</option>
                <option value="FAIL" <?php selected($status_filter, 'FAIL'); ?>>Verdict: FAIL</option>
                <option value="Submitted and indexed" <?php selected($status_filter, 'Submitted and indexed'); ?>>Zaindeksowane</option>
                <option value="Crawled - currently not indexed" <?php selected($status_filter, 'Crawled - currently not indexed'); ?>>Nie zaindeksowane</option>
                <option value="Discovered - currently not indexed" <?php selected($status_filter, 'Discovered - currently not indexed'); ?>>Odkryte</option>
            </select>
            
            <select name="robots" id="robots-filter">
                <option value="">Wszystkie robots.txt</option>
                <option value="ALLOWED" <?php selected($robots_filter, 'ALLOWED'); ?>>Dozwolone</option>
                <option value="DISALLOWED" <?php selected($robots_filter, 'DISALLOWED'); ?>>Zablokowane</option>
            </select>
            
            <select name="post_type" id="post-type-filter">
                <option value="">Wszystkie typy</option>
                <?php foreach ($post_types as $post_type): ?>
                    <option value="<?php echo esc_attr($post_type->name); ?>" <?php selected($post_type_filter, $post_type->name); ?>>
                        <?php echo esc_html($post_type->label); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            
            <select name="per_page" id="per-page">
                <option value="20" <?php selected($per_page, 20); ?>>20 na stronę</option>
                <option value="50" <?php selected($per_page, 50); ?>>50 na stronę</option>
                <option value="100" <?php selected($per_page, 100); ?>>100 na stronę</option>
            </select>
            
            <button type="submit" class="button">Filtruj</button>
            <button type="button" id="refresh-data" class="button">Odśwież dane</button>
            <button type="button" id="export-csv" class="button">Eksportuj do CSV</button>
        </form>
    </div>
    
    <?php if (empty($urls)): ?>
        <div class="indexfixer-message warning">
            Nie znaleziono żadnych URL-i do sprawdzenia.
        </div>
    <?php else: ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>URL</th>
                    <th>Verdict</th>
                    <th>Coverage State</th>
                    <th>Robots.txt</th>
                    <th>Ostatni crawl</th>
                    <th>Ostatnie sprawdzenie API</th>
                    <th>Typ</th>
                    <th>Data publikacji</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($urls as $url_data): ?>
                    <?php
                    // Dane są już w $url_data z bazy danych
                    $status_data = array(
                        'verdict' => $url_data['verdict'],
                        'coverageState' => $url_data['coverage_state'],
                        'robotsTxtState' => $url_data['robots_txt_state'],
                        'indexingState' => $url_data['indexing_state'],
                        'pageFetchState' => $url_data['page_fetch_state'],
                        'crawledAs' => $url_data['crawled_as'],
                        'lastCrawlTime' => $url_data['last_crawl_time'],
                        'lastChecked' => $url_data['last_checked']
                    );
                    
                    $simple_status = $url_data['status'] ?: 'unknown';
                    ?>
                    <tr data-status="<?php echo esc_attr($simple_status); ?>">
                        <td>
                            <div class="url-cell">
                                <span class="url-text"><?php echo esc_html($url_data['url']); ?></span>
                                <button type="button" 
                                        class="button button-small check-single-url" 
                                        data-url="<?php echo esc_attr($url_data['url']); ?>"
                                        title="Sprawdź status tego URL">
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
        
        <!-- Paginacja -->
        <?php if ($total_pages > 1): ?>
            <div class="tablenav bottom">
                <div class="tablenav-pages">
                    <span class="displaying-num">
                        <?php printf(_n('%s element', '%s elementów', $total_urls, 'indexfixer'), number_format_i18n($total_urls)); ?>
                    </span>
                    
                    <span class="pagination-links">
                        <?php
                        echo paginate_links(array(
                            'base' => add_query_arg('paged', '%#%'),
                            'format' => '',
                            'prev_text' => __('&laquo;'),
                            'next_text' => __('&raquo;'),
                            'total' => $total_pages,
                            'current' => $page
                        ));
                        ?>
                    </span>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<style>
.indexfixer-filters {
    margin-bottom: 20px;
    padding: 15px;
    background: #f8f9fa;
    border: 1px solid #e1e5e9;
    border-radius: 4px;
}

.indexfixer-filters form {
    display: flex;
    gap: 10px;
    align-items: center;
    flex-wrap: wrap;
}

.indexfixer-filters select {
    min-width: 150px;
}

.url-cell {
    display: flex;
    align-items: center;
    gap: 8px;
}

.url-text {
    flex: 1;
    word-break: break-all;
}

.verdict-pass { color: #46b450; }
.verdict-neutral { color: #ffb900; }
.verdict-fail { color: #dc3232; }

.good { color: #46b450; }
.bad { color: #dc3232; }

.status-unknown {
    color: #999;
    font-style: italic;
}

.tablenav {
    margin: 15px 0;
    text-align: right;
}

.tablenav-pages {
    display: inline-block;
    margin-left: 10px;
}

.pagination-links {
    margin-left: 10px;
}

.pagination-links a,
.pagination-links span {
    display: inline-block;
    padding: 5px 10px;
    margin: 0 2px;
    border: 1px solid #ddd;
    background: #f7f7f7;
    text-decoration: none;
}

.pagination-links span.current {
    background: #0073aa;
    color: #fff;
    border-color: #0073aa;
}
</style>

<script>
// Lokalizacja zmiennych
var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
var indexfixer = {
    check_url_nonce: '<?php echo wp_create_nonce('indexfixer_check_url'); ?>'
};

jQuery(document).ready(function($) {
    console.log('IndexFixer: Skrypt urls.php załadowany');
    console.log('IndexFixer: ajaxurl =', ajaxurl);
    console.log('IndexFixer: indexfixer =', indexfixer);
    
    // Obsługa przycisku sprawdzania pojedynczego URL
    $('.check-single-url').on('click', function() {
        console.log('IndexFixer: Przycisk kliknięty w zakładce URLs');
        
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
                    
                    // Aktualizuj verdict
                    if (status.verdict) {
                        var verdictClass = 'verdict-' + status.verdict.toLowerCase();
                        $row.find('td:nth-child(2)').html(
                            '<span class="' + verdictClass + '">' + status.verdict + '</span>'
                        );
                    }
                    
                    // Aktualizuj coverage state
                    if (status.coverageState) {
                        $row.find('td:nth-child(3)').html(
                            '<span class="coverage-state">' + status.coverageState + '</span>'
                        );
                    }
                    
                    // Aktualizuj robots.txt state
                    if (status.robotsTxtState) {
                        var robotsClass = status.robotsTxtState === 'ALLOWED' ? 'good' : 'bad';
                        $row.find('td:nth-child(4)').html(
                            '<span class="' + robotsClass + '">' + status.robotsTxtState + '</span>'
                        );
                    }
                    
                    // Aktualizuj ostatnie sprawdzenie
                    $row.find('td:nth-child(6)').html(
                        '<span title="' + status.lastChecked + '">' + 
                        new Date().toLocaleString() + '<br>' +
                        '<small style="color: #666;">przed chwilą</small>' +
                        '</span>'
                    );
                    
                    // Pokaż komunikat sukcesu
                    var $notice = $('<div class="notice notice-success is-dismissible"><p>✅ Status URL został zaktualizowany</p></div>');
                    $('.indexfixer-urls h2').after($notice);
                    setTimeout(function() {
                        $notice.fadeOut();
                    }, 3000);
                    
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
    
    // Obsługa sortowania (jeśli będzie potrzebna w przyszłości)
    $('.wp-list-table th').on('click', function() {
        console.log('IndexFixer: Kliknięto nagłówek tabeli - sortowanie nie jest jeszcze zaimplementowane');
    });
});
</script> 