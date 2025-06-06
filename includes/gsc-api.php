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
            
            if (!$this->auth_handler->is_authorized_with_refresh()) {
                IndexFixer_Logger::log('Brak autoryzacji do Google Search Console', 'error');
                return array('error' => 'Brak autoryzacji do Google Search Console');
            }

            IndexFixer_Logger::log('Autoryzacja OK, przechodze dalej...', 'info');
            
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