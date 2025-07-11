<?php
/**
 * Obsługa API Google Search Console
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('IndexFixer_GSC_API')) {
    class IndexFixer_GSC_API {
        private $auth_handler;
        private static $cached_site_url = null;  // Cache dla wykrytego formatu siteUrl
        
        /**
         * Konstruktor
         */
        public function __construct() {
            $this->auth_handler = new IndexFixer_Auth_Handler();
        }
        
        /**
         * Sprawdza status URL-a w Google Search Console
         */
        public function check_url_status($url) {
            IndexFixer_Logger::log('=== POCZĄTEK check_url_status() ===', 'info');
            IndexFixer_Logger::log(sprintf('URL do sprawdzenia: %s', $url), 'info');
            
            // ZOPTYMALIZOWANE: Sprawdź token tylko raz na sesję, nie przed każdym URL
            static $token_checked_this_session = false;
            static $session_start_time = null;
            
            if (!$token_checked_this_session || (time() - $session_start_time > 1800)) { // 30 minut
                $session_start_time = time();
                
                // Przeładuj tokeny z bazy na wypadek gdyby zostały odświeżone w innej instancji
                $this->auth_handler->reload_tokens_from_database();
                IndexFixer_Logger::log('Przeładowano tokeny z bazy danych (początek sesji)', 'info');
                
                // Sprawdź i odnów token PRZED rozpoczęciem sesji sprawdzania
                if (!$this->ensure_fresh_token()) {
                    IndexFixer_Logger::log('❌ Nie udało się zapewnić świeżego tokenu', 'error');
                    return array('error' => 'Brak autoryzacji do Google Search Console - token wygasł i nie udało się go odnowić');
                }
                
                IndexFixer_Logger::log('✅ Token jest świeży, rozpoczynam sesję sprawdzania...', 'info');
                $token_checked_this_session = true;
            }
            
            // Sprawdź czy mamy już wykryty format siteUrl w cache (tylko raz na sesję)
            if (self::$cached_site_url === null) {
                self::$cached_site_url = $this->detect_working_site_url($url);
                if (self::$cached_site_url === false) {
                    IndexFixer_Logger::log('Wszystkie próby wykrycia formatu siteUrl nie powiodły się', 'error');
                    return false;
                }
            }
            
            // Użyj wykrytego formatu z cache
            IndexFixer_Logger::log(sprintf('Używam wykrytego formatu siteUrl: %s (z cache)', self::$cached_site_url), 'info');
            return $this->try_url_inspection($url, self::$cached_site_url);
        }
        
        /**
         * Wykrywa działający format siteUrl dla danej strony (tylko raz na sesję)
         */
        private function detect_working_site_url($sample_url) {
            $site_url = get_site_url();
            $site_formats = array(
                rtrim($site_url, '/') . '/',  // https://womensfitness.pl/
                $site_url,  // https://womensfitness.pl
                'sc-domain:' . str_replace(['http://', 'https://'], '', rtrim($site_url, '/')),  // sc-domain:womensfitness.pl
            );
            
            IndexFixer_Logger::log('🔍 Wykrywam działający format siteUrl: ' . implode(', ', $site_formats), 'info');
            
            foreach ($site_formats as $index => $format) {
                IndexFixer_Logger::log(sprintf('Próbuję format siteUrl: %s', $format), 'info');
                IndexFixer_Logger::log(sprintf('PORÓWNANIE: URL="%s" siteUrl="%s" czy URL zaczyna się od siteUrl? %s', 
                    $sample_url, 
                    $format, 
                    strpos($sample_url, rtrim($format, '/')) === 0 ? 'TAK' : 'NIE'
                ), 'info');
                
                // Dodaj opóźnienie przed kolejną próbą (poza pierwszą)
                if ($index > 0) {
                    IndexFixer_Logger::log('Czekam 2 sekundy przed kolejną próbą...', 'info');
                    sleep(2);
                }
                
                $result = $this->try_url_inspection($sample_url, $format);
                if ($result !== false) {
                    IndexFixer_Logger::log(sprintf('✅ Wykryto działający format siteUrl: %s - zapamiętano na sesję', $format), 'success');
                    return $format;
                }
            }
            
            IndexFixer_Logger::log('❌ Nie udało się wykryć działającego formatu siteUrl', 'error');
            return false;
        }
        
        /**
         * Sprawdza, czy token jest świeży i odnawia go jeśli wygasa w ciągu 30 minut
         */
        private function ensure_fresh_token() {
            // Jeśli dane nie zostały załadowane, spróbuj załadować bezpośrednio (fix dla crona)
            if (empty($this->auth_handler->get_client_id()) && function_exists('get_option')) {
                IndexFixer_Logger::log('⚠️ GSC API: Client ID pusty - ładuję dane bezpośrednio z bazy', 'warning');
                
                $client_id = get_option('indexfixer_gsc_client_id');
                $client_secret = get_option('indexfixer_gsc_client_secret');
                
                if (!empty($client_id) && !empty($client_secret)) {
                    $this->auth_handler->set_client_credentials($client_id, $client_secret);
                    IndexFixer_Logger::log('✅ GSC API: Dane OAuth załadowane bezpośrednio', 'info');
                } else {
                    IndexFixer_Logger::log('❌ GSC API: Brak Client ID lub Client Secret w bazie danych', 'error');
                    return false;
                }
            }
            
            // Sprawdź podstawowe wymagania
            if (empty($this->auth_handler->get_client_id()) || empty($this->auth_handler->get_client_secret())) {
                IndexFixer_Logger::log('❌ Brak Client ID lub Client Secret', 'error');
                return false;
            }
            
            if (empty($this->auth_handler->get_access_token())) {
                IndexFixer_Logger::log('❌ Brak Access Token', 'error');
                return false;
            }
            
            // Sprawdź czas wygaśnięcia tokenu
            $token_expires_at = get_option('indexfixer_gsc_token_expires_at', 0);
            $current_time = time();
            
            // Jeśli nie ma expires_at, sprawdź token przez API Google
            if ($token_expires_at == 0) {
                IndexFixer_Logger::log('⚠️ Brak informacji o wygaśnięciu tokenu - sprawdzam przez API Google', 'warning');
                return $this->auth_handler->is_authorized_with_refresh();
            }
            
            $time_until_expiry = $token_expires_at - $current_time;
            $minutes_until_expiry = round($time_until_expiry / 60);
            
            // Zawsze loguj info o tokenie przy sprawdzeniu sesji
            IndexFixer_Logger::log(sprintf('🕐 Token wygasa za %d minut (%s)', 
                $minutes_until_expiry, 
                date('Y-m-d H:i:s', $token_expires_at)
            ), 'info');
            
            // Jeśli token wygasł lub wygasa w ciągu 30 minut - odnów go
            if ($time_until_expiry <= 1800) { // 30 minut = 1800 sekund
                if ($time_until_expiry <= 0) {
                    IndexFixer_Logger::log('🔄 Token wygasł - próbuję odnowić', 'warning');
                } else {
                    IndexFixer_Logger::log('🔄 Token wygasa za mniej niż 30 minut - proaktywnie odnawiam', 'info');
                }
                
                $refresh_result = $this->auth_handler->refresh_access_token();
                if (!$refresh_result) {
                    IndexFixer_Logger::log('❌ Nie udało się odnowić tokenu', 'error');
                    return false;
                }
                
                IndexFixer_Logger::log('✅ Token został pomyślnie odnowiony', 'success');
                return true;
            }
            
            IndexFixer_Logger::log('✅ Token jest świeży (wygasa za więcej niż 30 minut)', 'success');
            return true;
        }
        
        /**
         * Próbuje sprawdzić URL z określonym formatem siteUrl
         */
        private function try_url_inspection($url, $site_url) {
            // NOWE: Sprawdź limity API przed wykonaniem requestu
            $quota_monitor = IndexFixer_Quota_Monitor::get_instance();
            if (!$quota_monitor->can_make_request()) {
                IndexFixer_Logger::log('🚫 Request anulowany - przekroczono limity API Google', 'error');
                return array('error' => 'Przekroczono dzienny limit API Google Search Console (2000 requestów/dzień)');
            }
            
            $endpoint = 'https://searchconsole.googleapis.com/v1/urlInspection/index:inspect';
            
            IndexFixer_Logger::log(sprintf('Endpoint API: %s', $endpoint), 'info');
            $request_body = array(
                'inspectionUrl' => $url,
                'siteUrl' => $site_url
            );
            
            IndexFixer_Logger::log(sprintf('Request body JSON: %s', json_encode($request_body)), 'info');
            IndexFixer_Logger::log(sprintf('Access token (pierwsze 20 znaków): %s...', substr($this->auth_handler->get_access_token(), 0, 20)), 'info');
            
            $response = wp_remote_post($endpoint, array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $this->auth_handler->get_access_token(),
                    'Content-Type' => 'application/json'
                ),
                'body' => json_encode($request_body),
                'timeout' => 30
            ));
            
            if (is_wp_error($response)) {
                IndexFixer_Logger::log(
                    sprintf('Błąd podczas sprawdzania URL %s: %s', $url, $response->get_error_message()),
                    'error'
                );
                return false;
            }
            
            $response_code = wp_remote_retrieve_response_code($response);
            $headers = wp_remote_retrieve_headers($response);
            $body = wp_remote_retrieve_body($response);
            
            IndexFixer_Logger::log(sprintf('Kod odpowiedzi: %d', $response_code), 'info');
            IndexFixer_Logger::log(sprintf('Headers odpowiedzi: %s', print_r($headers, true)), 'info');
            
            if ($response_code !== 200) {
                // NOWE: Obsługa błędu 429 Quota Exceeded
                if ($response_code === 429) {
                    IndexFixer_Logger::log(
                        sprintf('🚫 QUOTA EXCEEDED dla URL %s - Google API zwrócił błąd 429', $url),
                        'error'
                    );
                    
                    // Automatycznie ustaw licznik na maksimum (2000/2000)
                    $quota_monitor->force_quota_exceeded();
                    
                    return array('error' => 'Quota exceeded - limit 2000 requestów/dzień przekroczony');
                }
                
                IndexFixer_Logger::log(
                    sprintf('Błąd API GSC dla URL %s (kod %d): %s', $url, $response_code, $body),
                    'error'
                );
                return false;
            }
            
            // NOWE: Zarejestruj pomyślny request w monitorze limitów
            $quota_stats = $quota_monitor->record_api_request();
            IndexFixer_Logger::log(sprintf(
                '📊 Request zarejestrowany: %d/2000 dzisiaj (pozostało: %d)', 
                $quota_stats['daily_count'], 
                $quota_stats['daily_remaining']
            ), 'info');
            
            $data = json_decode($body, true);
            IndexFixer_Logger::log(sprintf('Odpowiedź API: %s', print_r($data, true)), 'info');
            
            if (!isset($data['inspectionResult'])) {
                IndexFixer_Logger::log(
                    sprintf('Nieoczekiwana odpowiedź API dla URL %s: %s', $url, $body),
                    'error'
                );
                return false;
            }
            
            return $data['inspectionResult'];
        }
    }
} 