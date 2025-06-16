<?php
/**
 * Monitor limitów API Google Search Console
 * Bazuje na oficjalnej dokumentacji Google: https://developers.google.com/webmaster-tools/limits
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('IndexFixer_Quota_Monitor')) {
    class IndexFixer_Quota_Monitor {
        
        // Limity z oficjalnej dokumentacji Google
        const DAILY_LIMIT = 2000;          // 2000 QPD (queries per day) dla URL inspection
        const MINUTE_LIMIT = 600;          // 600 QPM (queries per minute) dla URL inspection  
        const WARNING_THRESHOLD = 0.8;     // Ostrzeżenie przy 80% limitu (1600 requestów)
        const CRITICAL_THRESHOLD = 0.95;   // Krytyczne przy 95% limitu (1900 requestów)
        
        private static $instance = null;
        
        /**
         * Singleton
         */
        public static function get_instance() {
            if (self::$instance === null) {
                self::$instance = new self();
            }
            return self::$instance;
        }
        
        /**
         * Konstruktor
         */
        private function __construct() {
            // Hook do czyszczenia statystyk o północy
            add_action('wp', array($this, 'schedule_daily_reset'));
        }
        
        /**
         * Rejestruje wykonany request do API
         * UWAGA: Używa Pacific Time zgodnie z resetowaniem limitów Google
         */
        public function record_api_request() {
            // WAŻNE: Google resetuje limity o północy czasu pacyficznego (PST/PDT)
            $pacific_timezone = new DateTimeZone('America/Los_Angeles');
            $now_pacific = new DateTime('now', $pacific_timezone);
            $today = $now_pacific->format('Y-m-d');
            $current_minute = $now_pacific->format('Y-m-d H:i');
            
            // Statystyki dzienne
            $daily_key = 'indexfixer_api_requests_' . $today;
            $daily_count = get_transient($daily_key);
            if ($daily_count === false) {
                $daily_count = 0;
            }
            $daily_count++;
            set_transient($daily_key, $daily_count, DAY_IN_SECONDS);
            
            // Statystyki minutowe (dla limitu 600 QPM)
            $minute_key = 'indexfixer_api_requests_minute_' . $current_minute;
            $minute_count = get_transient($minute_key);
            if ($minute_count === false) {
                $minute_count = 0;
            }
            $minute_count++;
            set_transient($minute_key, $minute_count, MINUTE_IN_SECONDS);
            
            IndexFixer_Logger::log(sprintf(
                '📊 API Request zarejestrowany: %d/2000 dzisiaj, %d/600 w tej minucie', 
                $daily_count, 
                $minute_count
            ), 'debug');
            
            // Sprawdź czy przekroczono progi ostrzeżeń
            $this->check_quota_warnings($daily_count, $minute_count);
            
            return array(
                'daily_count' => $daily_count,
                'minute_count' => $minute_count,
                'daily_remaining' => self::DAILY_LIMIT - $daily_count,
                'minute_remaining' => self::MINUTE_LIMIT - $minute_count
            );
        }
        
        /**
         * Sprawdza czy można wykonać request (nie przekroczy limitów)
         * UWAGA: Używa Pacific Time zgodnie z Google API
         */
        public function can_make_request() {
            // WAŻNE: Google resetuje limity o północy czasu pacyficznego (PST/PDT)
            $pacific_timezone = new DateTimeZone('America/Los_Angeles');
            $now_pacific = new DateTime('now', $pacific_timezone);
            $today = $now_pacific->format('Y-m-d');
            $current_minute = $now_pacific->format('Y-m-d H:i');
            
            // Sprawdź limit dzienny
            $daily_key = 'indexfixer_api_requests_' . $today;
            $daily_count = get_transient($daily_key);
            if ($daily_count === false) {
                $daily_count = 0;
            }
            
            if ($daily_count >= self::DAILY_LIMIT) {
                IndexFixer_Logger::log('🚫 LIMIT DZIENNY PRZEKROCZONY: ' . $daily_count . '/2000', 'error');
                return false;
            }
            
            // Sprawdź limit minutowy
            $minute_key = 'indexfixer_api_requests_minute_' . $current_minute;
            $minute_count = get_transient($minute_key);
            if ($minute_count === false) {
                $minute_count = 0;
            }
            
            if ($minute_count >= self::MINUTE_LIMIT) {
                IndexFixer_Logger::log('🚫 LIMIT MINUTOWY PRZEKROCZONY: ' . $minute_count . '/600', 'error');
                return false;
            }
            
            return true;
        }
        
        /**
         * Pobiera aktualne statystyki użycia
         * UWAGA: Używa Pacific Time zgodnie z Google API
         */
        public function get_usage_stats() {
            // WAŻNE: Google resetuje limity o północy czasu pacyficznego (PST/PDT)
            $pacific_timezone = new DateTimeZone('America/Los_Angeles');
            $now_pacific = new DateTime('now', $pacific_timezone);
            $today = $now_pacific->format('Y-m-d');
            $current_minute = $now_pacific->format('Y-m-d H:i');
            
            $daily_key = 'indexfixer_api_requests_' . $today;
            $minute_key = 'indexfixer_api_requests_minute_' . $current_minute;
            
            $daily_count = get_transient($daily_key);
            $minute_count = get_transient($minute_key);
            
            if ($daily_count === false) $daily_count = 0;
            if ($minute_count === false) $minute_count = 0;
            
            $daily_percentage = ($daily_count / self::DAILY_LIMIT) * 100;
            $minute_percentage = ($minute_count / self::MINUTE_LIMIT) * 100;
            
            return array(
                'daily' => array(
                    'used' => $daily_count,
                    'limit' => self::DAILY_LIMIT,
                    'remaining' => self::DAILY_LIMIT - $daily_count,
                    'percentage' => round($daily_percentage, 1)
                ),
                'minute' => array(
                    'used' => $minute_count,
                    'limit' => self::MINUTE_LIMIT,
                    'remaining' => self::MINUTE_LIMIT - $minute_count,
                    'percentage' => round($minute_percentage, 1)
                ),
                'status' => $this->get_quota_status($daily_percentage)
            );
        }
        
        /**
         * Określa status quota na podstawie procentowego wykorzystania
         */
        private function get_quota_status($percentage) {
            if ($percentage >= 100) {
                return 'exceeded';
            } elseif ($percentage >= (self::CRITICAL_THRESHOLD * 100)) {
                return 'critical';
            } elseif ($percentage >= (self::WARNING_THRESHOLD * 100)) {
                return 'warning';
            } else {
                return 'ok';
            }
        }
        
        /**
         * Sprawdza progi ostrzeżeń i loguje odpowiednie komunikaty
         */
        private function check_quota_warnings($daily_count, $minute_count) {
            $daily_percentage = ($daily_count / self::DAILY_LIMIT);
            $minute_percentage = ($minute_count / self::MINUTE_LIMIT);
            
            // Ostrzeżenia dzienne
            if ($daily_percentage >= self::CRITICAL_THRESHOLD && $daily_percentage < 1.0) {
                IndexFixer_Logger::log(sprintf(
                    '🔴 KRYTYCZNE: Wykorzystano %.1f%% dziennego limitu API (%d/2000)', 
                    $daily_percentage * 100, 
                    $daily_count
                ), 'error');
                
                // Wyślij powiadomienie administratorowi
                $this->send_quota_notification('critical', $daily_count, self::DAILY_LIMIT, 'dzienny');
                
            } elseif ($daily_percentage >= self::WARNING_THRESHOLD && $daily_percentage < self::CRITICAL_THRESHOLD) {
                IndexFixer_Logger::log(sprintf(
                    '🟡 OSTRZEŻENIE: Wykorzystano %.1f%% dziennego limitu API (%d/2000)', 
                    $daily_percentage * 100, 
                    $daily_count
                ), 'warning');
            }
            
            // Ostrzeżenia minutowe
            if ($minute_percentage >= self::CRITICAL_THRESHOLD) {
                IndexFixer_Logger::log(sprintf(
                    '🔴 KRYTYCZNE: Wykorzystano %.1f%% minutowego limitu API (%d/600)', 
                    $minute_percentage * 100, 
                    $minute_count
                ), 'error');
            }
        }
        
        /**
         * Wysyła powiadomienie email o przekroczeniu progów
         */
        private function send_quota_notification($level, $used, $limit, $period) {
            // Sprawdź czy już wysłano powiadomienie w ciągu ostatnich 4 godzin
            $notification_key = 'indexfixer_quota_notification_' . $level . '_' . date('Y-m-d-H');
            if (get_transient($notification_key)) {
                return; // Już wysłano
            }
            
            $admin_email = get_option('admin_email');
            $site_name = get_bloginfo('name');
            
            // POPRAWKA: Zabezpieczenie przed dzieleniem przez zero
            if ($limit <= 0) {
                IndexFixer_Logger::log('⚠️ Błąd: limit <= 0 w send_quota_notification', 'error');
                return;
            }
            
            $percentage = round(($used / $limit) * 100, 1);
            
            $subject = sprintf('[%s] IndexFixer - Ostrzeżenie o limitach API', $site_name);
            
            $message = sprintf(
                "Witaj,\n\n" .
                "IndexFixer na stronie %s osiągnął %s próg wykorzystania API Google Search Console.\n\n" .
                "Szczegóły:\n" .
                "- Wykorzystano: %d/%d requestów (%s)\n" .
                "- Procent wykorzystania: %.1f%%\n" .
                "- Okres: %s\n" .
                "- Czas: %s\n\n" .
                "Jeśli limit zostanie przekroczony, sprawdzanie URL-ów zostanie wstrzymane do następnego dnia.\n\n" .
                "Możesz sprawdzić szczegóły w panelu administracyjnym IndexFixer.\n\n" .
                "Pozdrawiam,\n" .
                "IndexFixer",
                $site_name,
                ($level === 'critical' ? 'krytyczny' : 'ostrzegawczy'),
                $used,
                $limit,
                ($level === 'critical' ? '🔴 KRYTYCZNY' : '🟡 OSTRZEŻENIE'),
                $percentage,
                $period,
                date('Y-m-d H:i:s')
            );
            
            wp_mail($admin_email, $subject, $message);
            
            // Oznacz jako wysłane na 4 godziny
            set_transient($notification_key, true, 4 * HOUR_IN_SECONDS);
            
            IndexFixer_Logger::log(sprintf(
                '📧 Wysłano powiadomienie email o %s przekroczeniu limitu do: %s', 
                $level, 
                $admin_email
            ), 'info');
        }
        
        /**
         * Planuje codzienne resetowanie statystyk
         * UWAGA: Używa strefy czasowej Pacific Time (PST/PDT) zgodnie z Google API
         */
        public function schedule_daily_reset() {
            if (!wp_next_scheduled('indexfixer_daily_quota_reset')) {
                // WAŻNE: Google resetuje limity o północy czasu pacyficznego (PST/PDT)
                $pacific_timezone = new DateTimeZone('America/Los_Angeles');
                $now_pacific = new DateTime('now', $pacific_timezone);
                $tomorrow_midnight_pacific = new DateTime('tomorrow 00:01', $pacific_timezone);
                
                // Konwertuj na timestamp UTC dla wp_schedule_event
                $reset_timestamp = $tomorrow_midnight_pacific->getTimestamp();
                
                wp_schedule_event($reset_timestamp, 'daily', 'indexfixer_daily_quota_reset');
                
                IndexFixer_Logger::log(sprintf(
                    '⏰ Zaplanowano codzienne resetowanie limitów API na %s (strefa czasowa Pacific: %s)',
                    $tomorrow_midnight_pacific->format('Y-m-d H:i:s T'),
                    $pacific_timezone->getName()
                ), 'info');
                
                IndexFixer_Logger::log('📋 WAŻNE: Google Search Console API resetuje limity o północy czasu pacyficznego (PST/PDT)', 'info');
            }
        }
        
        /**
         * Resetuje statystyki dzienne (wywoływane przez cron)
         * UWAGA: Używa strefy czasowej Pacific Time (PST/PDT) zgodnie z Google API
         */
        public function reset_daily_stats() {
            // WAŻNE: Google resetuje limity o północy czasu pacyficznego (PST/PDT)
            $pacific_timezone = new DateTimeZone('America/Los_Angeles');
            $now_pacific = new DateTime('now', $pacific_timezone);
            $yesterday_pacific = new DateTime('-1 day', $pacific_timezone);
            
            $yesterday_date = $yesterday_pacific->format('Y-m-d');
            $today_date = $now_pacific->format('Y-m-d');
            
            $yesterday_key = 'indexfixer_api_requests_' . $yesterday_date;
            $today_key = 'indexfixer_api_requests_' . $today_date;
            
            // Zapisz statystyki z wczoraj do historii
            $yesterday_count = get_transient($yesterday_key);
            if ($yesterday_count !== false) {
                update_option('indexfixer_quota_history_' . $yesterday_date, $yesterday_count);
                IndexFixer_Logger::log(sprintf(
                    '📊 Zapisano statystyki z %s: %d requestów (Pacific Time: %s)', 
                    $yesterday_date, 
                    $yesterday_count,
                    $pacific_timezone->getName()
                ), 'info');
                
                // Usuń stary transient
                delete_transient($yesterday_key);
            }
            
            // POPRAWKA: Resetuj dzisiejszy licznik do 0
            delete_transient($today_key);
            IndexFixer_Logger::log(sprintf(
                '🔄 Resetowano licznik API na 0 dla %s (Pacific Time)', 
                $today_date
            ), 'info');
            
            IndexFixer_Logger::log(sprintf(
                '🔄 Reset dziennych statystyk API - nowy dzień rozpoczęty (%s, Pacific Time: %s)', 
                $now_pacific->format('Y-m-d H:i:s T'),
                $pacific_timezone->getName()
            ), 'info');
            
            IndexFixer_Logger::log('📋 Zgodnie z dokumentacją Google: limity API resetują się o północy czasu pacyficznego', 'info');
        }
        
        /**
         * Pobiera historię wykorzystania z ostatnich 7 dni
         * UWAGA: Używa strefy czasowej Pacific Time (PST/PDT) zgodnie z Google API
         */
        public function get_usage_history($days = 7) {
            $history = array();
            // WAŻNE: Google resetuje limity o północy czasu pacyficznego (PST/PDT)
            $pacific_timezone = new DateTimeZone('America/Los_Angeles');
            
            for ($i = 0; $i < $days; $i++) {
                $date_obj = new DateTime("-$i days", $pacific_timezone);
                $date = $date_obj->format('Y-m-d');
                
                if ($i === 0) {
                    // Dzisiaj (w Pacific Time) - pobierz z transient
                    $count = get_transient('indexfixer_api_requests_' . $date);
                    if ($count === false) $count = 0;
                } else {
                    // Poprzednie dni - pobierz z opcji
                    $count = get_option('indexfixer_quota_history_' . $date, 0);
                }
                
                $history[$date] = $count;
            }
            
            return array_reverse($history, true);
        }
        
        /**
         * Wymusza ustawienie quota na maksimum gdy Google API zwróci 429
         */
        public function force_quota_exceeded() {
            // Użyj Pacific Time zgodnie z Google API
            $pacific_timezone = new DateTimeZone('America/Los_Angeles');
            $now_pacific = new DateTime('now', $pacific_timezone);
            $today = $now_pacific->format('Y-m-d');
            
            // Ustaw licznik dzienny na maksimum (2000)
            $daily_key = 'indexfixer_api_requests_' . $today;
            set_transient($daily_key, self::DAILY_LIMIT, DAY_IN_SECONDS);
            
            // Oblicz czas do następnego resetu (północ PST)
            $tomorrow_midnight = new DateTime('tomorrow 00:01', $pacific_timezone);
            $time_until_reset = $tomorrow_midnight->getTimestamp() - time();
            $hours_until_reset = round($time_until_reset / 3600, 1);
            
            // Konwertuj na czas serwera (lokalny)
            $server_timezone = wp_timezone();
            $reset_time_server = $tomorrow_midnight->setTimezone($server_timezone);
            
            IndexFixer_Logger::log(sprintf(
                '🚫 QUOTA EXCEEDED: Ustawiono licznik na %d/2000. Reset o %s (za %.1f godzin)',
                self::DAILY_LIMIT,
                $reset_time_server->format('Y-m-d H:i:s T'),
                $hours_until_reset
            ), 'error');
            
            IndexFixer_Logger::log(sprintf(
                '⏰ Następny reset limitów: %s czasu serwera (%s)',
                $reset_time_server->format('H:i'),
                $server_timezone->getName()
            ), 'info');
        }
        
        /**
         * Oblicza szacowany czas do wyczerpania limitu
         */
        public function estimate_quota_exhaustion() {
            $stats = $this->get_usage_stats();
            $daily_used = $stats['daily']['used'];
            $daily_remaining = $stats['daily']['remaining'];
            
            if ($daily_remaining <= 0) {
                return 'Limit już przekroczony';
            }
            
            // Pobierz historię z ostatnich 3 dni dla średniej
            $history = $this->get_usage_history(3);
            $total_requests = array_sum($history);
            $non_zero_days = array_filter($history); // Dni z aktywnością
            $days_count = count($non_zero_days);
            
            // POPRAWKA: Sprawdź czy są jakiekolwiek dane historyczne
            if ($days_count === 0 || $total_requests === 0) {
                return 'Brak danych historycznych do analizy';
            }
            
            $avg_per_day = $total_requests / $days_count;
            
            if ($avg_per_day <= 0) {
                return 'Brak aktywności w historii';
            }
            
            // Użyj Pacific Time dla godziny (zgodnie z Google API)
            $pacific_timezone = new DateTimeZone('America/Los_Angeles');
            $now_pacific = new DateTime('now', $pacific_timezone);
            $current_hour = (int) $now_pacific->format('H');
            $hours_passed = max(1, $current_hour + 1); // Minimum 1 żeby uniknąć dzielenia przez 0
            
            // POPRAWKA: Sprawdź czy daily_used > 0 przed dzieleniem
            if ($daily_used <= 0) {
                return 'Brak aktywności dzisiaj - nie można oszacować';
            }
            
            $current_rate_per_hour = $daily_used / $hours_passed;
            
            if ($current_rate_per_hour <= 0) {
                return 'Tempo wykorzystania zbyt niskie do oszacowania';
            }
            
            $hours_until_exhaustion = $daily_remaining / $current_rate_per_hour;
            
            if ($hours_until_exhaustion > 24) {
                return 'Limit nie zostanie przekroczony dzisiaj';
            }
            
            $exhaustion_time = time() + ($hours_until_exhaustion * 3600);
            return sprintf(
                'Szacowany czas wyczerpania: %s (za %.1f godzin)', 
                date('H:i', $exhaustion_time),
                $hours_until_exhaustion
            );
        }
    }
}

// Hook dla codziennego resetu
add_action('indexfixer_daily_quota_reset', array(IndexFixer_Quota_Monitor::get_instance(), 'reset_daily_stats')); 