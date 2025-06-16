<?php
/**
 * Obsługa bazy danych dla IndexFixer
 */

if (!defined('ABSPATH')) {
    exit;
}

class IndexFixer_Database {
    
    private static $table_name = 'indexfixer_urls';
    
    /**
     * Inicjalizuje bazę danych
     */
    public static function init() {
        add_action('plugins_loaded', array(__CLASS__, 'maybe_upgrade_database'));
    }
    
    /**
     * Sprawdza czy potrzeba upgrade bazy
     */
    public static function maybe_upgrade_database() {
        $installed_version = get_option('indexfixer_db_version', '0');
        $current_version = INDEXFIXER_VERSION;
        
        if (version_compare($installed_version, $current_version, '<')) {
            self::create_tables();
            update_option('indexfixer_db_version', $current_version);
        }
    }
    
    /**
     * Tworzy tabele w bazie danych
     */
    public static function create_tables() {
        global $wpdb;
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        // Tabela główna URL-ów
        $table_name = self::get_table_name();
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            url varchar(500) NOT NULL,
            post_id bigint(20) unsigned DEFAULT 0,
            status varchar(50) DEFAULT 'unknown',
            verdict varchar(50) DEFAULT NULL,
            coverage_state varchar(100) DEFAULT NULL,
            robots_txt_state varchar(50) DEFAULT NULL,
            indexing_state varchar(100) DEFAULT NULL,
            page_fetch_state varchar(100) DEFAULT NULL,
            crawled_as varchar(50) DEFAULT NULL,
            last_crawl_time datetime DEFAULT NULL,
            last_checked datetime DEFAULT NULL,
            last_status_change datetime DEFAULT NULL,
            check_count int(11) DEFAULT 0,
            widget_since datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY url (url),
            KEY post_id (post_id),
            KEY status (status),
            KEY last_checked (last_checked),
            KEY coverage_state (coverage_state),
            KEY widget_since (widget_since)
        ) $charset_collate;";
        
        dbDelta($sql);
        
        // NOWA: Tabela statystyk historycznych
        $stats_table = $wpdb->prefix . 'indexfixer_stats';
        
        $stats_sql = "CREATE TABLE $stats_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            date_recorded date NOT NULL,
            total_urls int(11) DEFAULT 0,
            checked_urls int(11) DEFAULT 0,
            indexed int(11) DEFAULT 0,
            not_indexed int(11) DEFAULT 0,
            discovered int(11) DEFAULT 0,
            excluded int(11) DEFAULT 0,
            unknown int(11) DEFAULT 0,
            verdict_pass int(11) DEFAULT 0,
            verdict_neutral int(11) DEFAULT 0,
            verdict_fail int(11) DEFAULT 0,
            robots_allowed int(11) DEFAULT 0,
            robots_disallowed int(11) DEFAULT 0,
            new_indexed_today int(11) DEFAULT 0,
            new_not_indexed_today int(11) DEFAULT 0,
            status_changes_today int(11) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY date_recorded (date_recorded),
            KEY indexed (indexed),
            KEY not_indexed (not_indexed)
        ) $charset_collate;";
        
        dbDelta($stats_sql);
        
        // NOWA: Tabela historii statusów URL-ów
        $history_table = $wpdb->prefix . 'indexfixer_url_history';
        
        $history_sql = "CREATE TABLE $history_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            url varchar(500) NOT NULL,
            post_id bigint(20) unsigned DEFAULT 0,
            status varchar(50) NOT NULL,
            verdict varchar(50) DEFAULT NULL,
            coverage_state varchar(100) DEFAULT NULL,
            robots_txt_state varchar(50) DEFAULT NULL,
            indexing_state varchar(100) DEFAULT NULL,
            page_fetch_state varchar(100) DEFAULT NULL,
            crawled_as varchar(50) DEFAULT NULL,
            last_crawl_time datetime DEFAULT NULL,
            changed_at datetime NOT NULL,
            previous_status varchar(50) DEFAULT NULL,
            previous_coverage_state varchar(100) DEFAULT NULL,
            change_type enum('new', 'status_change', 'recheck') DEFAULT 'status_change',
            days_since_publish int DEFAULT NULL,
            days_since_last_change int DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY url_time (url, changed_at),
            KEY post_id (post_id),
            KEY status_time (status, changed_at),
            KEY coverage_state (coverage_state),
            KEY change_type (change_type),
            KEY changed_at (changed_at)
        ) $charset_collate;";
        
        dbDelta($history_sql);
        
        IndexFixer_Logger::log('Tabele bazy danych zostały utworzone/zaktualizowane', 'success');
    }
    
    /**
     * Pobiera nazwę tabeli z prefixem
     */
    public static function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . self::$table_name;
    }
    
    /**
     * Zapisuje/aktualizuje status URL-a
     */
    public static function save_url_status($post_id, $url, $status_data) {
        global $wpdb;
        
        $table_name = self::get_table_name();
        
        // Przygotuj dane do zapisu
        $data = array(
            'post_id' => $post_id,
            'url' => $url,
            'status' => isset($status_data['simple_status']) ? $status_data['simple_status'] : (is_string($status_data) ? $status_data : 'unknown'),
            'verdict' => isset($status_data['verdict']) ? $status_data['verdict'] : null,
            'coverage_state' => isset($status_data['coverageState']) ? $status_data['coverageState'] : null,
            'robots_txt_state' => isset($status_data['robotsTxtState']) ? $status_data['robotsTxtState'] : null,
            'indexing_state' => isset($status_data['indexingState']) ? $status_data['indexingState'] : null,
            'page_fetch_state' => isset($status_data['pageFetchState']) ? $status_data['pageFetchState'] : null,
            'crawled_as' => isset($status_data['crawledAs']) ? $status_data['crawledAs'] : null,
            'last_crawl_time' => isset($status_data['lastCrawlTime']) && $status_data['lastCrawlTime'] !== 'unknown' 
                ? date('Y-m-d H:i:s', strtotime($status_data['lastCrawlTime'])) : null,
            'last_checked' => current_time('mysql'),
            'widget_since' => isset($status_data['widget_since']) ? $status_data['widget_since'] : null,
        );
        
        // Sprawdź czy URL już istnieje - pobierz poprzednie dane dla historii
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE url = %s",
            $url
        ));
        
        // Przygotuj dane poprzedniego statusu dla historii
        $previous_status_data = null;
        if ($existing) {
            $previous_status_data = array(
                'simple_status' => $existing->status,
                'verdict' => $existing->verdict,
                'coverageState' => $existing->coverage_state,
                'robotsTxtState' => $existing->robots_txt_state,
                'indexingState' => $existing->indexing_state,
                'pageFetchState' => $existing->page_fetch_state,
                'crawledAs' => $existing->crawled_as,
                'lastCrawlTime' => $existing->last_crawl_time
            );
        }
        
        // Sprawdź czy nastąpiła zmiana statusu (dla historii)
        $status_changed = false;
        if ($existing) {
            // Sprawdź czy status się zmienił
            if ($existing->status !== $data['status'] || 
                $existing->coverage_state !== $data['coverage_state'] ||
                $existing->verdict !== $data['verdict']) {
                $data['last_status_change'] = current_time('mysql');
                $status_changed = true;
            }
            
            $data['check_count'] = $existing->check_count + 1;
            
            // Update
            $wpdb->update(
                $table_name,
                $data,
                array('id' => $existing->id),
                array('%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s'),
                array('%d')
            );
        } else {
            // Insert - nowy URL
            $data['last_status_change'] = current_time('mysql');
            $data['check_count'] = 1;
            $status_changed = true; // Nowy URL to zawsze zmiana
            
            $wpdb->insert(
                $table_name,
                $data,
                array('%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d')
            );
        }
        
        // NOWE: Zapisz historię jeśli nastąpiła zmiana statusu lub to nowy URL
        if ($status_changed || !$existing) {
            self::save_url_history($post_id, $url, $status_data, $previous_status_data);
        }
    }
    
    /**
     * Pobiera status URL-a
     */
    public static function get_url_status($url) {
        global $wpdb;
        
        $table_name = self::get_table_name();
        
        $result = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE url = %s",
            $url
        ));
        
        if (!$result) {
            return false;
        }
        
        // Zwróć w formacie kompatybilnym z istniejącym kodem
        return array(
            'simple_status' => $result->status,
            'verdict' => $result->verdict,
            'coverageState' => $result->coverage_state,
            'robotsTxtState' => $result->robots_txt_state,
            'indexingState' => $result->indexing_state,
            'pageFetchState' => $result->page_fetch_state,
            'crawledAs' => $result->crawled_as,
            'lastCrawlTime' => $result->last_crawl_time,
            'lastChecked' => $result->last_checked,
            'lastStatusChange' => $result->last_status_change,
            'checkCount' => $result->check_count,
            'widget_since' => $result->widget_since
        );
    }
    
    /**
     * Pobiera URL-e według statusu
     */
    public static function get_urls_by_status($status, $limit = 10, $offset = 0) {
        global $wpdb;
        
        $table_name = self::get_table_name();
        
        // Mapuj logiczne statusy na prawdziwe kolumny w bazie
        $where_clause = '';
        
        switch ($status) {
            case 'not_indexed':
                // Niezaindeksowane to te z verdict NEUTRAL i coverage_state zawierającym "not indexed"
                $where_clause = "u.verdict = 'NEUTRAL' AND u.coverage_state LIKE '%not indexed%'";
                break;
            case 'indexed':
                // Zaindeksowane to te z verdict PASS i coverage_state zawierającym "indexed"
                $where_clause = "u.verdict = 'PASS' AND u.coverage_state LIKE '%indexed%'";
                break;
            case 'discovered':
                // Odkryte ale nieindeksowane
                $where_clause = "u.coverage_state LIKE '%Discovered%'";
                break;
            case 'excluded':
                // Wykluczone z indeksowania
                $where_clause = "u.verdict = 'FAIL' OR u.coverage_state LIKE '%excluded%'";
                break;
            case 'unknown':
                // Nieznany status
                $where_clause = "u.status = 'unknown' AND (u.verdict IS NULL OR u.verdict = '')";
                break;
            default:
                // Fallback do starej logiki
                $where_clause = $wpdb->prepare("u.status = %s", $status);
        }
        
        $sql = "SELECT u.*, p.post_title, p.post_type, p.post_date 
                FROM $table_name u 
                LEFT JOIN {$wpdb->posts} p ON u.post_id = p.ID 
                WHERE $where_clause 
                ORDER BY u.last_status_change DESC, u.last_checked ASC 
                LIMIT %d OFFSET %d";
        
        $results = $wpdb->get_results($wpdb->prepare($sql, $limit, $offset));
        
        return $results;
    }
    
    /**
     * Pobiera najstarsze niezaindeksowane URL-e do sprawdzenia
     */
    public static function get_urls_for_checking($limit = 10) {
        global $wpdb;
        
        $table_name = self::get_table_name();
        
        // Pobierz URL-e które nie były sprawdzane przez 24h lub wcale
        // Skupiamy się na tych które mają status unknown lub mogą być niezaindeksowane
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT u.*, p.post_title, p.post_type, p.post_date 
             FROM $table_name u 
             LEFT JOIN {$wpdb->posts} p ON u.post_id = p.ID 
             WHERE (u.last_checked IS NULL 
                    OR u.last_checked < DATE_SUB(NOW(), INTERVAL 24 HOUR))
                   AND (u.status = 'unknown' 
                        OR u.verdict = 'NEUTRAL' 
                        OR u.coverage_state LIKE '%not indexed%'
                        OR u.coverage_state LIKE '%Discovered%')
             ORDER BY u.last_checked ASC 
             LIMIT %d",
            $limit
        ));
        
        return $results;
    }
    
    /**
     * NOWA: Pobiera URL-e które są aktualnie wyświetlane w widgetach i wymagają sprawdzenia
     */
    public static function get_widget_urls_for_checking($limit = 10) {
        global $wpdb;
        
        $table_name = self::get_table_name();
        
        // Pobierz URL-e które są wyświetlane w widgetach (not_indexed) 
        // I które nie były sprawdzane przez 24h lub wcale
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT u.*, p.post_title, p.post_type, p.post_date 
             FROM $table_name u 
             LEFT JOIN {$wpdb->posts} p ON u.post_id = p.ID 
             WHERE (u.last_checked IS NULL 
                    OR u.last_checked < DATE_SUB(NOW(), INTERVAL 24 HOUR))
                   AND u.verdict = 'NEUTRAL' 
                   AND u.coverage_state LIKE '%not indexed%'
             ORDER BY u.last_status_change DESC, u.last_checked ASC 
             LIMIT %d",
            $limit
        ));
        
        return $results;
    }
    
    /**
     * Pobiera statystyki URL-ów
     */
    public static function get_statistics() {
        global $wpdb;
        
        $table_name = self::get_table_name();
        
        $stats = $wpdb->get_results(
            "SELECT 
                status,
                COUNT(*) as count,
                verdict,
                coverage_state,
                last_checked
             FROM $table_name 
             GROUP BY status, verdict, coverage_state, last_checked"
        );
        
        // Przetwórz statystyki do formatu kompatybilnego z istniejącym kodem
        $result = array(
            'total' => 0,
            'checked' => 0,
            'indexed' => 0,
            'not_indexed' => 0,
            'discovered' => 0,
            'excluded' => 0,
            'unknown' => 0,
            'pass' => 0,
            'neutral' => 0,
            'fail' => 0,
            'robots_allowed' => 0,
            'robots_disallowed' => 0
        );
        
        foreach ($stats as $stat) {
            $result['total'] += $stat->count;
            
            // POPRAWKA: URL jest sprawdzony tylko jeśli ma wypełnione last_checked (faktycznie sprawdzony przez API)
            if ($stat->status !== 'unknown' && !empty($stat->last_checked)) {
                $result['checked'] += $stat->count;
            }
            
            // Mapuj statusy
            switch ($stat->coverage_state) {
                case 'Submitted and indexed':
                    $result['indexed'] += $stat->count;
                    break;
                case 'Crawled - currently not indexed':
                    $result['not_indexed'] += $stat->count;
                    break;
                case 'Discovered - currently not indexed':
                    $result['discovered'] += $stat->count;
                    break;
                default:
                    if ($stat->status === 'unknown' || empty($stat->last_checked)) {
                        $result['unknown'] += $stat->count;
                    } else {
                        $result['excluded'] += $stat->count;
                    }
            }
            
            // Mapuj verdict
            switch (strtolower($stat->verdict)) {
                case 'pass':
                    $result['pass'] += $stat->count;
                    break;
                case 'neutral':
                    $result['neutral'] += $stat->count;
                    break;
                case 'fail':
                    $result['fail'] += $stat->count;
                    break;
            }
        }
        
        return $result;
    }
    
    /**
     * Migruje dane z wp_options do tabeli (kompatybilność wsteczna)
     */
    public static function migrate_from_cache() {
        // Najpierw sprawdź stary format
        $cache_data = get_option('indexfixer_url_statuses', array());
        
        // Jeśli nie ma, sprawdź transienty
        if (empty($cache_data)) {
            IndexFixer_Logger::log('Brak danych w indexfixer_url_statuses, sprawdzam transienty...', 'info');
            $cache_data = self::get_transient_data();
        }
        
        if (empty($cache_data)) {
            IndexFixer_Logger::log('Brak danych w wp_options i transientach do migracji', 'warning');
            return;
        }
        
        IndexFixer_Logger::log('Rozpoczęcie migracji danych z wp_options do tabeli...', 'info');
        IndexFixer_Logger::log('Znaleziono ' . count($cache_data) . ' URL-ów w cache', 'info');
        
        $migrated = 0;
        $failed = 0;
        
        foreach ($cache_data as $url => $status_data) {
            // Spróbuj znaleźć post_id na podstawie URL
            $post_id = url_to_postid($url);
            if (!$post_id) {
                // Spróbuj inne metody znalezienia post_id
                $post_id = self::find_post_id_by_url($url);
            }
            
            if ($post_id) {
                self::save_url_status($post_id, $url, $status_data);
                $migrated++;
                IndexFixer_Logger::log("✅ Zmigrowano: $url (post_id: $post_id)", 'info');
            } else {
                // Zapisz bez post_id - używając 0 jako placeholder
                self::save_url_status(0, $url, $status_data);
                $failed++;
                IndexFixer_Logger::log("⚠️ Brak post_id dla: $url - zapisano z post_id=0", 'warning');
            }
        }
        
        IndexFixer_Logger::log("🎯 MIGRACJA ZAKOŃCZONA:", 'success');
        IndexFixer_Logger::log("  • Zmigrowano z post_id: $migrated", 'success');
        IndexFixer_Logger::log("  • Zmigrowano bez post_id: $failed", 'warning');
        IndexFixer_Logger::log("  • Łącznie: " . ($migrated + $failed), 'success');
        
        // Nie usuwamy jeszcze wp_options - zostaw jako backup
        // delete_option('indexfixer_url_statuses');
    }
    
    /**
     * Pobiera dane z transientów IndexFixer
     */
    private static function get_transient_data() {
        global $wpdb;
        
        // Znajdź wszystkie transienty indexfixer
        $transients = $wpdb->get_results(
            "SELECT option_name, option_value 
             FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_indexfixer_url_status_%'"
        );
        
        IndexFixer_Logger::log('Znaleziono ' . count($transients) . ' transientów indexfixer', 'info');
        
        $cache_data = array();
        
        foreach ($transients as $transient) {
            // Wyciągnij URL z nazwy transienta lub z danych
            $data = maybe_unserialize($transient->option_value);
            
            if (is_array($data)) {
                // Spróbuj znaleźć URL na podstawie hash
                $hash = str_replace('_transient_indexfixer_url_status_', '', $transient->option_name);
                
                // Znajdź URL który ma ten hash
                $url = self::find_url_by_hash($hash);
                
                if ($url) {
                    $cache_data[$url] = $data;
                    IndexFixer_Logger::log("Znaleziono transient dla URL: $url", 'info');
                } else {
                    IndexFixer_Logger::log("Nie można znaleźć URL dla hash: $hash", 'warning');
                }
            }
        }
        
        return $cache_data;
    }
    
    /**
     * Próbuje znaleźć URL na podstawie hash
     */
    private static function find_url_by_hash($hash) {
        // Pobierz wszystkie URL-e ze strony
        if (class_exists('IndexFixer_Fetch_URLs')) {
            $all_urls = IndexFixer_Fetch_URLs::get_all_urls();
            
            foreach ($all_urls as $url_data) {
                $url = $url_data['url'];
                $computed_hash = md5($url);
                
                if ($computed_hash === $hash) {
                    return $url;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Pomocnicza funkcja do znalezienia post_id po URL
     */
    private static function find_post_id_by_url($url) {
        global $wpdb;
        
        // Usuń domenę z URL
        $path = parse_url($url, PHP_URL_PATH);
        if (!$path) return false;
        
        // Sprawdź czy to permalink
        $post_id = $wpdb->get_var($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} 
             WHERE post_name = %s 
             AND post_status = 'publish'
             LIMIT 1",
            basename(rtrim($path, '/'))
        ));
        
        return $post_id ? (int) $post_id : false;
    }
    
    /**
     * Zapisuje dzienne statystyki do tabeli historycznej
     */
    public static function save_daily_stats() {
        global $wpdb;
        
        $stats_table = $wpdb->prefix . 'indexfixer_stats';
        $today = current_time('Y-m-d');
        
        // Sprawdź czy już są statystyki na dzisiaj
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $stats_table WHERE date_recorded = %s",
            $today
        ));
        
        // Pobierz aktualne statystyki
        $current_stats = self::get_statistics();
        
        // Oblicz zmiany względem wczoraj
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $yesterday_stats = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $stats_table WHERE date_recorded = %s",
            $yesterday
        ));
        
        $new_indexed_today = 0;
        $new_not_indexed_today = 0;
        $status_changes_today = 0;
        
        if ($yesterday_stats) {
            $new_indexed_today = max(0, $current_stats['indexed'] - $yesterday_stats->indexed);
            $new_not_indexed_today = max(0, $current_stats['not_indexed'] - $yesterday_stats->not_indexed);
            
            // Oblicz całkowite zmiany statusu
            $total_changes = abs($current_stats['indexed'] - $yesterday_stats->indexed) +
                           abs($current_stats['not_indexed'] - $yesterday_stats->not_indexed) +
                           abs($current_stats['discovered'] - $yesterday_stats->discovered);
            $status_changes_today = $total_changes;
        }
        
        $data = array(
            'date_recorded' => $today,
            'total_urls' => $current_stats['total'],
            'checked_urls' => $current_stats['checked'],
            'indexed' => $current_stats['indexed'],
            'not_indexed' => $current_stats['not_indexed'],
            'discovered' => $current_stats['discovered'],
            'excluded' => $current_stats['excluded'],
            'unknown' => $current_stats['unknown'],
            'verdict_pass' => $current_stats['pass'],
            'verdict_neutral' => $current_stats['neutral'],
            'verdict_fail' => $current_stats['fail'],
            'robots_allowed' => $current_stats['robots_allowed'],
            'robots_disallowed' => $current_stats['robots_disallowed'],
            'new_indexed_today' => $new_indexed_today,
            'new_not_indexed_today' => $new_not_indexed_today,
            'status_changes_today' => $status_changes_today
        );
        
        if ($existing) {
            // Aktualizuj istniejące statystyki
            $result = $wpdb->update(
                $stats_table,
                $data,
                array('date_recorded' => $today),
                array('%s', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d'),
                array('%s')
            );
            IndexFixer_Logger::log("Zaktualizowano statystyki dzienne dla $today", 'info');
        } else {
            // Wstawit nowe statystyki
            $result = $wpdb->insert(
                $stats_table,
                $data,
                array('%s', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d')
            );
            IndexFixer_Logger::log("Zapisano nowe statystyki dzienne dla $today", 'success');
        }
        
        return $result !== false;
    }
    
    /**
     * Pobiera historyczne statystyki (ostatnie N dni) dla wybranego post_type (lub ogólne, jeśli null)
     */
    public static function get_historical_stats($days = 30) {
        global $wpdb;
        $table = $wpdb->prefix . 'indexfixer_stats';
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table ORDER BY date_recorded DESC LIMIT %d", $days
        ));
        return array_reverse($results); // od najstarszych do najnowszych
    }
    
    /**
     * Pobiera statystyki trendu (porównanie z wczoraj)
     */
    public static function get_trend_stats() {
        global $wpdb;
        
        $stats_table = $wpdb->prefix . 'indexfixer_stats';
        $today = current_time('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        
        $today_stats = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $stats_table WHERE date_recorded = %s",
            $today
        ));
        
        $yesterday_stats = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $stats_table WHERE date_recorded = %s",
            $yesterday
        ));
        
        if (!$today_stats || !$yesterday_stats) {
            return false;
        }
        
        return array(
            'indexed_change' => $today_stats->indexed - $yesterday_stats->indexed,
            'not_indexed_change' => $today_stats->not_indexed - $yesterday_stats->not_indexed,
            'discovered_change' => $today_stats->discovered - $yesterday_stats->discovered,
            'new_indexed_today' => $today_stats->new_indexed_today,
            'new_not_indexed_today' => $today_stats->new_not_indexed_today,
            'status_changes_today' => $today_stats->status_changes_today
        );
    }
    
    /**
     * Pobiera URL-e według statusu i typu postu
     */
    public static function get_urls_by_status_and_type($status, $post_type, $limit = 10, $offset = 0) {
        global $wpdb;
        $table_name = self::get_table_name();
        $where_clause = '';
        switch ($status) {
            case 'not_indexed':
                $where_clause = $wpdb->prepare("u.verdict = 'NEUTRAL' AND u.coverage_state LIKE %s", '%not indexed%');
                break;
            case 'indexed':
                $where_clause = $wpdb->prepare("u.verdict = 'PASS' AND u.coverage_state LIKE %s", '%indexed%');
                break;
            case 'discovered':
                $where_clause = "u.coverage_state LIKE '%Discovered%'";
                break;
            case 'excluded':
                $where_clause = "u.verdict = 'FAIL' OR u.coverage_state LIKE '%excluded%'";
                break;
            case 'unknown':
                $where_clause = "u.status = 'unknown' AND (u.verdict IS NULL OR u.verdict = '')";
                break;
            default:
                $where_clause = $wpdb->prepare("u.status = %s", $status);
        }
        $where_clause .= $wpdb->prepare(" AND p.post_type = %s", $post_type);
        $sql = "SELECT u.*, p.post_title, p.post_type, p.post_date 
                FROM $table_name u 
                LEFT JOIN {$wpdb->posts} p ON u.post_id = p.ID 
                WHERE $where_clause 
                ORDER BY u.last_status_change DESC, u.last_checked ASC 
                LIMIT %d OFFSET %d";
        $results = $wpdb->get_results($wpdb->prepare($sql, $limit, $offset));
        return $results;
    }
    
    // ================================
    // NOWA FUNKCJONALNOŚĆ: HISTORIA STATUSÓW
    // ================================
    
    /**
     * Zapisuje zmianę statusu URL-a do historii
     */
    public static function save_url_history($post_id, $url, $new_status_data, $previous_status_data = null) {
        global $wpdb;
        
        $history_table = $wpdb->prefix . 'indexfixer_url_history';
        
        // Określ typ zmiany
        $change_type = 'status_change';
        if (!$previous_status_data) {
            $change_type = 'new';
        } elseif (
            isset($previous_status_data['coverage_state']) && 
            isset($new_status_data['coverage_state']) &&
            $previous_status_data['coverage_state'] === $new_status_data['coverage_state']
        ) {
            $change_type = 'recheck';
        }
        
        // Oblicz dni od publikacji (jeśli mamy post_id)
        $days_since_publish = null;
        if ($post_id > 0) {
            $post_date = $wpdb->get_var($wpdb->prepare(
                "SELECT post_date FROM {$wpdb->posts} WHERE ID = %d",
                $post_id
            ));
            if ($post_date) {
                // POPRAWKA: Użyj current_time('timestamp') zamiast time() dla zgodności ze strefą czasową WordPress
                $current_timestamp = current_time('timestamp');
                $post_timestamp = strtotime($post_date);
                $days_since_publish = floor(($current_timestamp - $post_timestamp) / DAY_IN_SECONDS);
                
                // Debuguj obliczenia
                IndexFixer_Logger::log("📅 DEBUG: Post date: $post_date, Current: " . date('Y-m-d H:i:s', $current_timestamp) . ", Days: $days_since_publish", 'debug');
            }
        }
        
        // Oblicz dni od ostatniej zmiany
        $days_since_last_change = null;
        $last_change = $wpdb->get_var($wpdb->prepare(
            "SELECT changed_at FROM $history_table 
             WHERE url = %s 
             ORDER BY changed_at DESC 
             LIMIT 1",
            $url
        ));
        if ($last_change) {
            $days_since_last_change = floor((time() - strtotime($last_change)) / DAY_IN_SECONDS);
        }
        
        $data = array(
            'url' => $url,
            'post_id' => $post_id,
            'status' => isset($new_status_data['simple_status']) ? $new_status_data['simple_status'] : 'unknown',
            'verdict' => isset($new_status_data['verdict']) ? $new_status_data['verdict'] : null,
            'coverage_state' => isset($new_status_data['coverageState']) ? $new_status_data['coverageState'] : null,
            'robots_txt_state' => isset($new_status_data['robotsTxtState']) ? $new_status_data['robotsTxtState'] : null,
            'indexing_state' => isset($new_status_data['indexingState']) ? $new_status_data['indexingState'] : null,
            'page_fetch_state' => isset($new_status_data['pageFetchState']) ? $new_status_data['pageFetchState'] : null,
            'crawled_as' => isset($new_status_data['crawledAs']) ? $new_status_data['crawledAs'] : null,
            'last_crawl_time' => isset($new_status_data['lastCrawlTime']) ? $new_status_data['lastCrawlTime'] : null,
            'changed_at' => current_time('mysql'),
            'previous_status' => $previous_status_data ? (isset($previous_status_data['simple_status']) ? $previous_status_data['simple_status'] : null) : null,
            'previous_coverage_state' => $previous_status_data ? (isset($previous_status_data['coverageState']) ? $previous_status_data['coverageState'] : null) : null,
            'change_type' => $change_type,
            'days_since_publish' => $days_since_publish,
            'days_since_last_change' => $days_since_last_change
        );
        
        $result = $wpdb->insert(
            $history_table,
            $data,
            array('%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d')
        );
        
        if ($result !== false) {
            IndexFixer_Logger::log("💾 Historia: Zapisano zmianę statusu dla $url (typ: $change_type)", 'info');
            return true;
        } else {
            IndexFixer_Logger::log("❌ Historia: Błąd zapisywania zmiany dla $url: " . $wpdb->last_error, 'error');
            return false;
        }
    }
    
    /**
     * Pobiera historię statusów dla konkretnego URL-a
     */
    public static function get_url_history($url, $limit = 50) {
        global $wpdb;
        
        $history_table = $wpdb->prefix . 'indexfixer_url_history';
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT h.*, p.post_title, p.post_type 
             FROM $history_table h
             LEFT JOIN {$wpdb->posts} p ON h.post_id = p.ID
             WHERE h.url = %s 
             ORDER BY h.changed_at DESC 
             LIMIT %d",
            $url,
            $limit
        ));
        
        return $results;
    }
    
    /**
     * Pobiera statystyki czasów indeksacji
     */
    public static function get_indexing_time_stats($post_type = null, $days = 30) {
        global $wpdb;
        
        $history_table = $wpdb->prefix . 'indexfixer_url_history';
        
        // POPRAWKA: Pokaż wszystkie wpisy w historii, nie tylko zaindeksowane
        $where_clause = "WHERE h1.changed_at >= DATE_SUB(NOW(), INTERVAL %d DAY) 
                         AND h1.days_since_publish IS NOT NULL";
        $params = array($days);
        
        if ($post_type) {
            $where_clause .= " AND p.post_type = %s";
            $params[] = $post_type;
        }
        
        $sql = "SELECT 
                    AVG(h1.days_since_publish) as avg_days_to_index,
                    MIN(h1.days_since_publish) as min_days_to_index,
                    MAX(h1.days_since_publish) as max_days_to_index,
                    COUNT(*) as total_count,
                    COUNT(CASE WHEN h1.coverage_state = 'Submitted and indexed' THEN 1 END) as indexed_count,
                    COUNT(CASE WHEN h1.coverage_state LIKE '%not indexed%' THEN 1 END) as not_indexed_count,
                    COUNT(CASE WHEN h1.coverage_state LIKE '%unknown%' THEN 1 END) as unknown_count,
                    p.post_type
                FROM $history_table h1
                LEFT JOIN {$wpdb->posts} p ON h1.post_id = p.ID
                $where_clause
                GROUP BY p.post_type
                ORDER BY total_count DESC";
        
        $results = $wpdb->get_results($wpdb->prepare($sql, $params));
        
        return $results;
    }
    
    /**
     * Pobiera URL-e które wypadły z indeksu
     */
    public static function get_dropped_urls($days = 7) {
        global $wpdb;
        
        $history_table = $wpdb->prefix . 'indexfixer_url_history';
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT h.*, p.post_title, p.post_type
             FROM $history_table h
             LEFT JOIN {$wpdb->posts} p ON h.post_id = p.ID
             WHERE h.previous_coverage_state = 'Submitted and indexed'
             AND h.coverage_state != 'Submitted and indexed'
             AND h.changed_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
             ORDER BY h.changed_at DESC",
            $days
        ));
        
        return $results;
    }
    
    /**
     * Pobiera trendy indeksacji (porównanie okresów)
     */
    public static function get_indexing_trends($days = 30) {
        global $wpdb;
        
        $history_table = $wpdb->prefix . 'indexfixer_url_history';
        
        // Obecny okres
        $current_period = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                AVG(days_since_publish) as avg_days,
                COUNT(*) as count
             FROM $history_table 
             WHERE coverage_state = 'Submitted and indexed'
             AND changed_at >= DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        ));
        
        // Poprzedni okres
        $previous_period = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                AVG(days_since_publish) as avg_days,
                COUNT(*) as count
             FROM $history_table 
             WHERE coverage_state = 'Submitted and indexed'
             AND changed_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
             AND changed_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days * 2,
            $days
        ));
        
        return array(
            'current' => $current_period,
            'previous' => $previous_period,
            'trend' => $current_period && $previous_period ? 
                ($current_period->avg_days - $previous_period->avg_days) : null
        );
    }
    
    /**
     * Pobiera ostatnie zmiany statusów URL-ów
     */
    public static function get_recent_status_changes($limit = 10) {
        global $wpdb;
        
        $history_table = $wpdb->prefix . 'indexfixer_url_history';
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT h.*, p.post_title, p.post_type 
             FROM $history_table h
             LEFT JOIN {$wpdb->posts} p ON h.post_id = p.ID
             WHERE h.change_type IN ('status_change', 'new')
             ORDER BY h.changed_at DESC 
             LIMIT %d",
            $limit
        ));
        
        return $results;
    }
    
    /**
     * Sprawdza czy wszystkie wymagane tabele bazy danych istnieją
     */
    public static function check_database_tables() {
        global $wpdb;
        
        $results = array();
        
        // Lista wymaganych tabel
        $required_tables = array(
            'indexfixer_urls' => 'Główna tabela URL-ów',
            'indexfixer_stats' => 'Statystyki historyczne', 
            'indexfixer_url_history' => 'Historia statusów URL-ów'
        );
        
        foreach ($required_tables as $table_suffix => $description) {
            $table_name = $wpdb->prefix . $table_suffix;
            
            // Sprawdź czy tabela istnieje
            $table_exists = $wpdb->get_var($wpdb->prepare(
                "SHOW TABLES LIKE %s",
                $table_name
            )) === $table_name;
            
            $table_info = array(
                'name' => $table_name,
                'description' => $description,
                'exists' => $table_exists,
                'row_count' => 0,
                'size_mb' => 0
            );
            
            // Jeśli tabela istnieje, pobierz dodatkowe informacje
            if ($table_exists) {
                // Liczba rekordów
                $row_count = $wpdb->get_var("SELECT COUNT(*) FROM `$table_name`");
                $table_info['row_count'] = (int) $row_count;
                
                // Rozmiar tabeli w MB
                $size_query = $wpdb->get_row($wpdb->prepare(
                    "SELECT 
                        ROUND(((data_length + index_length) / 1024 / 1024), 2) AS size_mb
                     FROM information_schema.TABLES 
                     WHERE table_schema = DATABASE() 
                     AND table_name = %s",
                    $table_name
                ));
                
                if ($size_query) {
                    $table_info['size_mb'] = (float) $size_query->size_mb;
                }
            }
            
            $results[] = $table_info;
        }
        
        return $results;
    }
    
    // ================================
    // NOWA FUNKCJONALNOŚĆ: CZYSZCZENIE USUNIĘTYCH POSTÓW
    // ================================
    
    /**
     * Usuwa URL-e które nie mają odpowiadającego postu w WordPressie
     */
    public static function cleanup_orphaned_urls() {
        global $wpdb;
        
        $table_name = self::get_table_name();
        
        // Znajdź URL-e które mają post_id ale post już nie istnieje w WordPressie
        $orphaned_urls = $wpdb->get_results(
            "SELECT u.* FROM $table_name u 
             WHERE u.post_id > 0 
             AND NOT EXISTS (
                 SELECT 1 FROM {$wpdb->posts} p 
                 WHERE p.ID = u.post_id 
                 AND p.post_status IN ('publish', 'private', 'draft')
             )"
        );
        
        if (empty($orphaned_urls)) {
            IndexFixer_Logger::log('🧹 Cleanup: Brak osieroconych URL-ów do usunięcia', 'info');
            return 0;
        }
        
        $deleted_count = 0;
        
        IndexFixer_Logger::log('🧹 Usuwanie ' . count($orphaned_urls) . ' URL-ów z usuniętych postów...', 'info');
        
        foreach ($orphaned_urls as $url_data) {
            // Usuń z głównej tabeli
            $wpdb->delete($table_name, array('id' => $url_data->id));
            
            // Dodaj wpis do historii o usunięciu
            self::save_url_history(
                $url_data->post_id,
                $url_data->url,
                array(
                    'simple_status' => 'deleted',
                    'verdict' => 'DELETED',
                    'coverageState' => 'Post deleted from WordPress'
                ),
                array(
                    'simple_status' => $url_data->status,
                    'verdict' => $url_data->verdict,
                    'coverageState' => $url_data->coverage_state
                )
            );
            
            $deleted_count++;
            IndexFixer_Logger::log("🗑️ Usunięto URL z usuniętego postu (ID {$url_data->post_id}): {$url_data->url}", 'info');
        }
        
        IndexFixer_Logger::log("🧹 Cleanup zakończony: Usunięto $deleted_count URL-ów z usuniętych postów", 'success');
        
        return $deleted_count;
    }
    
    /**
     * Funkcja uruchamiana codziennie przez cron - usuwa tylko URL-e z usuniętych postów
     */
    public static function daily_cleanup() {
        IndexFixer_Logger::log('🧹 Rozpoczęcie codziennego czyszczenia...', 'info');
        
        // Wyczyść URL-e z usuniętych postów (sprawdza tylko czy post istnieje w WP)
        $cleaned_count = self::cleanup_orphaned_urls();
        
        if ($cleaned_count > 0) {
            IndexFixer_Logger::log("🎉 Codzienne czyszczenie: Usunięto $cleaned_count URL-ów z usuniętych postów", 'success');
            
            // Opcjonalnie: wyślij raport
            do_action('indexfixer_cleanup_completed', array(
                'deleted_posts' => $cleaned_count,
                'total' => $cleaned_count
            ));
        }
        
        return $cleaned_count;
    }
}

// Inicjalizuj bazę danych
IndexFixer_Database::init(); 