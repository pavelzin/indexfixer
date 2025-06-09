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
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY url (url),
            KEY post_id (post_id),
            KEY status (status),
            KEY last_checked (last_checked),
            KEY coverage_state (coverage_state)
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
        );
        
        // Sprawdź czy URL już istnieje
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE url = %s",
            $url
        ));
        
        if ($existing) {
            // Sprawdź czy status się zmienił
            if ($existing->status !== $data['status']) {
                $data['last_status_change'] = current_time('mysql');
            }
            
            $data['check_count'] = $existing->check_count + 1;
            
            // Update
            $wpdb->update(
                $table_name,
                $data,
                array('id' => $existing->id),
                array('%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d'),
                array('%d')
            );
        } else {
            // Insert
            $data['last_status_change'] = current_time('mysql');
            $data['check_count'] = 1;
            
            $wpdb->insert(
                $table_name,
                $data,
                array('%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d')
            );
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
            'checkCount' => $result->check_count
        );
    }
    
    /**
     * Pobiera URL-e według statusu
     */
    public static function get_urls_by_status($status, $limit = 10, $offset = 0) {
        global $wpdb;
        
        $table_name = self::get_table_name();
        
        // DEBUG: Dodaj szczegółowe logowanie
        IndexFixer_Logger::log("🔍 DEBUG: get_urls_by_status('$status', $limit, $offset) - tabela: $table_name", 'info');
        
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
        
        IndexFixer_Logger::log("🔍 DEBUG: WHERE clause dla '$status': $where_clause", 'info');
        
        $sql = "SELECT u.*, p.post_title, p.post_type, p.post_date 
                FROM $table_name u 
                LEFT JOIN {$wpdb->posts} p ON u.post_id = p.ID 
                WHERE $where_clause 
                ORDER BY u.last_status_change DESC, u.last_checked ASC 
                LIMIT %d OFFSET %d";
        
        $final_sql = $wpdb->prepare($sql, $limit, $offset);
        IndexFixer_Logger::log("🔍 DEBUG: Finalne zapytanie SQL: $final_sql", 'info');
        
        $results = $wpdb->get_results($final_sql);
        
        IndexFixer_Logger::log("🔍 DEBUG: Zapytanie zwróciło " . count($results) . " wyników", 'info');
        
        // DEBUG: Sprawdź też ogólne statystyki tabeli
        $total_rows = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        IndexFixer_Logger::log("🔍 DEBUG: Łączna liczba wierszy w tabeli: $total_rows", 'info');
        
        // DEBUG: Sprawdź przykładowe dane
        $sample_rows = $wpdb->get_results("SELECT url, status, verdict, coverage_state FROM $table_name LIMIT 5");
        IndexFixer_Logger::log("🔍 DEBUG: Pierwsze 5 wierszy w tabeli: " . print_r($sample_rows, true), 'info');
        
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
     * Pobiera historyczne statystyki (ostatnie N dni)
     */
    public static function get_historical_stats($days = 30) {
        global $wpdb;
        
        $stats_table = $wpdb->prefix . 'indexfixer_stats';
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $stats_table 
             ORDER BY date_recorded DESC 
             LIMIT %d",
            $days
        ));
        
        return array_reverse($results); // Odwróć żeby najstarsze były pierwsze
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
}

// Inicjalizuj bazę danych
IndexFixer_Database::init(); 