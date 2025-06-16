<?php
/**
 * Obsługa akcji AJAX dla IndexFixer
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Rejestracja akcji AJAX
 */
function indexfixer_register_ajax_actions() {
    // Ładowanie zawartości zakładki
    add_action('wp_ajax_indexfixer_load_tab', 'indexfixer_ajax_load_tab');
    
    // Obsługa listy URL-i
    add_action('wp_ajax_indexfixer_filter_urls', 'indexfixer_ajax_filter_urls');
    add_action('wp_ajax_indexfixer_sort_urls', 'indexfixer_ajax_sort_urls');
    add_action('wp_ajax_indexfixer_load_urls_page', 'indexfixer_ajax_load_urls_page');
    add_action('wp_ajax_indexfixer_refresh_urls', 'indexfixer_ajax_refresh_urls');
    add_action('wp_ajax_indexfixer_export_csv', 'indexfixer_ajax_export_csv');
    
    // Obsługa przeglądu
    add_action('wp_ajax_indexfixer_refresh_overview', 'indexfixer_ajax_refresh_overview');
    
    // Obsługa ustawień
    add_action('wp_ajax_indexfixer_save_settings', 'indexfixer_ajax_save_settings');
    
    // Obsługa diagnostyki
    add_action('wp_ajax_indexfixer_clear_logs', 'indexfixer_ajax_clear_logs');
}
add_action('init', 'indexfixer_register_ajax_actions');

/**
 * Ładowanie zawartości zakładki
 */
function indexfixer_ajax_load_tab() {
    check_ajax_referer('indexfixer_nonce', 'nonce');
    
    $tab = isset($_POST['tab']) ? sanitize_text_field($_POST['tab']) : 'overview';
    $tab_file = INDEXFIXER_PLUGIN_DIR . 'templates/tabs/' . $tab . '.php';
    
    if (file_exists($tab_file)) {
        ob_start();
        include $tab_file;
        $content = ob_get_clean();
        wp_send_json_success($content);
    } else {
        wp_send_json_error('Nie znaleziono zawartości dla wybranej zakładki.');
    }
}

/**
 * Filtrowanie URL-i
 */
function indexfixer_ajax_filter_urls() {
    check_ajax_referer('indexfixer_nonce', 'nonce');
    
    $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';
    $robots = isset($_POST['robots']) ? sanitize_text_field($_POST['robots']) : '';
    $post_type = isset($_POST['post_type']) ? sanitize_text_field($_POST['post_type']) : '';
    
    $urls = indexfixer_get_filtered_urls($status, $robots, $post_type);
    $html = indexfixer_render_urls_table($urls);
    
    wp_send_json_success($html);
}

/**
 * Sortowanie URL-i
 */
function indexfixer_ajax_sort_urls() {
    check_ajax_referer('indexfixer_nonce', 'nonce');
    
    $column = isset($_POST['column']) ? sanitize_text_field($_POST['column']) : '';
    $direction = isset($_POST['direction']) ? sanitize_text_field($_POST['direction']) : 'asc';
    
    $urls = indexfixer_get_sorted_urls($column, $direction);
    $html = indexfixer_render_urls_table($urls);
    
    wp_send_json_success($html);
}

/**
 * Ładowanie strony URL-i
 */
function indexfixer_ajax_load_urls_page() {
    check_ajax_referer('indexfixer_nonce', 'nonce');
    
    $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
    $per_page = 20; // Liczba URL-i na stronę
    
    $urls = indexfixer_get_paginated_urls($page, $per_page);
    $total_urls = indexfixer_get_total_urls();
    $total_pages = ceil($total_urls / $per_page);
    
    $table_html = indexfixer_render_urls_table($urls);
    $pagination_html = indexfixer_render_pagination($page, $total_pages);
    
    wp_send_json_success(array(
        'table' => $table_html,
        'pagination' => $pagination_html
    ));
}

/**
 * Odświeżanie danych URL-i
 */
function indexfixer_ajax_refresh_urls() {
    check_ajax_referer('indexfixer_nonce', 'nonce');
    
    // Odśwież dane URL-i
    indexfixer_refresh_urls_data();
    
    // Pobierz zaktualizowane dane
    $urls = indexfixer_get_urls();
    $html = indexfixer_render_urls_table($urls);
    
    wp_send_json_success($html);
}

/**
 * Eksport do CSV
 */
function indexfixer_ajax_export_csv() {
    check_ajax_referer('indexfixer_nonce', 'nonce');
    
    $urls = indexfixer_get_urls();
    $filename = 'indexfixer-urls-' . date('Y-m-d') . '.csv';
    
    // Nagłówki CSV
    $headers = array(
        'URL',
        'Status',
        'Stan pokrycia',
        'Robots.txt',
        'Ostatni crawl',
        'Ostatnie sprawdzenie',
        'Typ postu',
        'Data publikacji'
    );
    
    // Przygotuj dane
    $data = array();
    foreach ($urls as $url) {
        $data[] = array(
            $url['url'],
            $url['verdict'],
            $url['coverage_state'],
            $url['robots_txt_state'],
            $url['last_crawl_time'],
            $url['last_check_time'],
            $url['post_type'],
            $url['post_date']
        );
    }
    
    // Wyślij plik CSV
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, $headers);
    
    foreach ($data as $row) {
        fputcsv($output, $row);
    }
    
    fclose($output);
    exit;
}

/**
 * Odświeżanie danych przeglądu
 */
function indexfixer_ajax_refresh_overview() {
    check_ajax_referer('indexfixer_nonce', 'nonce');
    
    ob_start();
    include INDEXFIXER_PLUGIN_DIR . 'templates/tabs/overview.php';
    $content = ob_get_clean();
    
    wp_send_json_success($content);
}

/**
 * Zapisywanie ustawień
 */
function indexfixer_ajax_save_settings() {
    check_ajax_referer('indexfixer_nonce', 'nonce');
    
    parse_str($_POST['form_data'], $settings);
    
    $new_settings = array(
        'post_types' => isset($settings['post_types']) ? array_map('sanitize_text_field', $settings['post_types']) : array(),
        'check_interval' => isset($settings['check_interval']) ? intval($settings['check_interval']) : 24,
        'max_urls_per_check' => isset($settings['max_urls_per_check']) ? intval($settings['max_urls_per_check']) : 100,
        'auto_refresh' => isset($settings['auto_refresh']) ? true : false,
        'debug_mode' => isset($settings['debug_mode']) ? true : false
    );
    
    update_option('indexfixer_settings', $new_settings);
    
    wp_send_json_success('Ustawienia zostały zapisane.');
}

/**
 * Czyszczenie logów
 */
function indexfixer_ajax_clear_logs() {
    check_ajax_referer('indexfixer_nonce', 'nonce');
    
    update_option('indexfixer_debug_logs', array());
    
    wp_send_json_success('Logi zostały wyczyszczone.');
}

/**
 * Funkcje pomocnicze
 */

/**
 * Pobierz przefiltrowane URL-e
 */
function indexfixer_get_filtered_urls($status = '', $robots = '', $post_type = '') {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'indexfixer_urls';
    $where = array();
    $values = array();
    
    if ($status) {
        $where[] = '(verdict = %s OR coverage_state = %s)';
        $values[] = $status;
        $values[] = $status;
    }
    
    if ($robots) {
        $where[] = 'robots_txt_state = %s';
        $values[] = $robots;
    }
    
    if ($post_type) {
        $where[] = 'post_type = %s';
        $values[] = $post_type;
    }
    
    $sql = "SELECT * FROM $table_name";
    
    if (!empty($where)) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    
    $sql .= ' ORDER BY last_check_time DESC';
    
    if (!empty($values)) {
        $sql = $wpdb->prepare($sql, $values);
    }
    
    return $wpdb->get_results($sql, ARRAY_A);
}

/**
 * Pobierz posortowane URL-e
 */
function indexfixer_get_sorted_urls($column, $direction) {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'indexfixer_urls';
    $allowed_columns = array(
        'url',
        'verdict',
        'coverage_state',
        'robots_txt_state',
        'last_crawl_time',
        'last_check_time',
        'post_type',
        'post_date'
    );
    
    if (!in_array($column, $allowed_columns)) {
        $column = 'last_check_time';
    }
    
    $direction = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
    
    $sql = "SELECT * FROM $table_name ORDER BY $column $direction";
    
    return $wpdb->get_results($sql, ARRAY_A);
}

/**
 * Pobierz URL-e z paginacją
 */
function indexfixer_get_paginated_urls($page, $per_page) {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'indexfixer_urls';
    $offset = ($page - 1) * $per_page;
    
    $sql = $wpdb->prepare(
        "SELECT * FROM $table_name ORDER BY last_check_time DESC LIMIT %d OFFSET %d",
        $per_page,
        $offset
    );
    
    return $wpdb->get_results($sql, ARRAY_A);
}

/**
 * Pobierz całkowitą liczbę URL-i
 */
function indexfixer_get_total_urls() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'indexfixer_urls';
    
    return $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
}

/**
 * Renderuj tabelę URL-i
 */
function indexfixer_render_urls_table($urls) {
    ob_start();
    ?>
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th class="sortable" data-column="url">URL</th>
                <th class="sortable" data-column="verdict">Status</th>
                <th class="sortable" data-column="coverage_state">Stan pokrycia</th>
                <th class="sortable" data-column="robots_txt_state">Robots.txt</th>
                <th class="sortable" data-column="last_crawl_time">Ostatni crawl</th>
                <th class="sortable" data-column="last_check_time">Ostatnie sprawdzenie</th>
                <th class="sortable" data-column="post_type">Typ postu</th>
                <th class="sortable" data-column="post_date">Data publikacji</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($urls as $url): ?>
                <tr>
                    <td>
                        <a href="<?php echo esc_url($url['url']); ?>" target="_blank">
                            <?php echo esc_html($url['url']); ?>
                        </a>
                    </td>
                    <td>
                        <span class="verdict-<?php echo esc_attr(strtolower($url['verdict'])); ?>">
                            <?php echo esc_html($url['verdict']); ?>
                        </span>
                    </td>
                    <td>
                        <span class="coverage-state">
                            <?php echo esc_html($url['coverage_state']); ?>
                        </span>
                    </td>
                    <td>
                        <span class="robots-txt-state">
                            <?php echo esc_html($url['robots_txt_state']); ?>
                        </span>
                    </td>
                    <td>
                        <?php echo esc_html($url['last_crawl_time']); ?>
                    </td>
                    <td>
                        <?php echo esc_html($url['last_check_time']); ?>
                    </td>
                    <td>
                        <?php echo esc_html($url['post_type']); ?>
                    </td>
                    <td>
                        <?php echo esc_html($url['post_date']); ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php
    return ob_get_clean();
}

/**
 * Renderuj paginację
 */
function indexfixer_render_pagination($current_page, $total_pages) {
    ob_start();
    ?>
    <div class="pagination">
        <?php if ($current_page > 1): ?>
            <a href="#" class="prev-page" data-page="<?php echo esc_attr($current_page - 1); ?>">
                &laquo; Poprzednia
            </a>
        <?php endif; ?>
        
        <?php
        $start = max(1, $current_page - 2);
        $end = min($total_pages, $current_page + 2);
        
        for ($i = $start; $i <= $end; $i++):
        ?>
            <a href="#" class="page-number <?php echo $i === $current_page ? 'current' : ''; ?>"
               data-page="<?php echo esc_attr($i); ?>">
                <?php echo esc_html($i); ?>
            </a>
        <?php endfor; ?>
        
        <?php if ($current_page < $total_pages): ?>
            <a href="#" class="next-page" data-page="<?php echo esc_attr($current_page + 1); ?>">
                Następna &raquo;
            </a>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
} 