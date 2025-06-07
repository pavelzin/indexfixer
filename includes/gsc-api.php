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
            
            // Przeładuj tokeny z bazy na wypadek gdyby zostały odświeżone w innej instancji
            $this->auth_handler->reload_tokens_from_database();
            
            // NOWE: Sprawdź i odnów token PRZED każdym requestem (30 minut przed wygaśnięciem)
            if (!$this->ensure_fresh_token()) {
                IndexFixer_Logger::log('❌ Nie udało się zapewnić świeżego tokenu', 'error');
                return array('error' => 'Brak autoryzacji do Google Search Console - token wygasł i nie udało się go odnowić');
            }

            IndexFixer_Logger::log('✅ Token jest świeży, przechodze dalej...', 'info');
            
            // Użyj standardowe formaty bazując na get_site_url()
            $site_url = get_site_url();
            $site_formats = array(
                rtrim($site_url, '/') . '/',  // https://fitrunner.pl/
                $site_url,  // https://fitrunner.pl
                'sc-domain:' . str_replace(['http://', 'https://'], '', rtrim($site_url, '/')),  // sc-domain:fitrunner.pl
            );
            
            IndexFixer_Logger::log('Próbuję formaty: ' . implode(', ', $site_formats), 'info');
            
            foreach ($site_formats as $index => $format) {
                IndexFixer_Logger::log(sprintf('Próbuję format siteUrl: %s', $format), 'info');
                IndexFixer_Logger::log(sprintf('PORÓWNANIE: URL="%s" siteUrl="%s" czy URL zaczyna się od siteUrl? %s', 
                    $url, 
                    $format, 
                    strpos($url, rtrim($format, '/')) === 0 ? 'TAK' : 'NIE'
                ), 'info');
                
                // Dodaj opóźnienie przed kolejną próbą (poza pierwszą)
                if ($index > 0) {
                    IndexFixer_Logger::log('Czekam 2 sekundy przed kolejną próbą...', 'info');
                    sleep(2);
                }
                
                $result = $this->try_url_inspection($url, $format);
                if ($result !== false) {
                    return $result;
                }
            }
            
            IndexFixer_Logger::log('Wszystkie próby nie powiodły się', 'error');
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
                IndexFixer_Logger::log(
                    sprintf('Błąd API GSC dla URL %s (kod %d): %s', $url, $response_code, $body),
                    'error'
                );
                return false;
            }
            
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