<?php
/**
 * Obsługa autoryzacji Google Search Console
 */

if (!defined('ABSPATH')) {
    exit;
}

class IndexFixer_Auth_Handler {
    private $client_id;
    private $client_secret;
    private $access_token;
    
    /**
     * Konstruktor
     */
    public function __construct() {
        // Sprawdź czy WordPress jest dostępny
        if (function_exists('get_option')) {
            $this->client_id = get_option('indexfixer_gsc_client_id');
            $this->client_secret = get_option('indexfixer_gsc_client_secret');
            $this->access_token = get_option('indexfixer_gsc_access_token');
        }
    }
    
    /**
     * Ustawia dane uwierzytelniające
     */
    public function set_client_credentials($client_id, $client_secret) {
        IndexFixer_Logger::log('Próba zapisania danych uwierzytelniających', 'info');
        IndexFixer_Logger::log(sprintf('Client ID: %s', $client_id), 'info');
        IndexFixer_Logger::log(sprintf('Client Secret: %s', $client_secret), 'info');
        
        if (function_exists('update_option')) {
            update_option('indexfixer_gsc_client_id', $client_id);
            update_option('indexfixer_gsc_client_secret', $client_secret);
        }
        
        $this->client_id = $client_id;
        $this->client_secret = $client_secret;
        
        IndexFixer_Logger::log('Dane uwierzytelniające zostały zapisane', 'success');
    }
    
    /**
     * Pobiera Client ID
     */
    public function get_client_id() {
        return $this->client_id;
    }
    
    /**
     * Pobiera Client Secret
     */
    public function get_client_secret() {
        return $this->client_secret;
    }
    
    /**
     * Sprawdza czy użytkownik jest autoryzowany
     */
    public function is_authorized() {
        if (empty($this->client_id) || empty($this->client_secret)) {
            IndexFixer_Logger::log('Brak Client ID lub Client Secret', 'error');
            return false;
        }
        
        if (empty($this->access_token)) {
            IndexFixer_Logger::log('Brak Access Token', 'error');
            return false;
        }
        
        // Sprawdź czy token jest ważny
        if (!function_exists('wp_remote_get')) {
            IndexFixer_Logger::log('WordPress HTTP API nie jest dostępne', 'error');
            return false;
        }
        
        $response = wp_remote_get(
            'https://www.googleapis.com/oauth2/v1/tokeninfo?access_token=' . $this->access_token
        );
        
        if (is_wp_error($response)) {
            IndexFixer_Logger::log(
                sprintf('Błąd podczas weryfikacji tokenu: %s', $response->get_error_message()),
                'error'
            );
            return false;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            IndexFixer_Logger::log(
                sprintf('Token nieważny (kod %d)', $response_code),
                'error'
            );
            return false;
        }
        
        IndexFixer_Logger::log('Autoryzacja poprawna', 'success');
        return true;
    }
    
    /**
     * Pobiera token dostępu
     */
    public function get_access_token() {
        return $this->access_token;
    }
    
    /**
     * Ustawia token dostępu
     */
    public function set_access_token($token) {
        $this->access_token = $token;
        if (function_exists('update_option')) {
            update_option('indexfixer_gsc_access_token', $token);
        }
        IndexFixer_Logger::log('Zapisano nowy Access Token', 'success');
    }
    
    /**
     * Generuje URL do autoryzacji
     */
    public function get_auth_url() {
        if (empty($this->client_id)) {
            IndexFixer_Logger::log('Brak Client ID podczas generowania URL autoryzacji', 'error');
            return false;
        }
        
        if (!function_exists('admin_url')) {
            IndexFixer_Logger::log('WordPress admin_url nie jest dostępne', 'error');
            return false;
        }
        
        $redirect_uri = admin_url('admin.php?page=indexfixer');
        $state = function_exists('wp_create_nonce') ? wp_create_nonce('indexfixer_auth') : 'fallback_state';
        
        IndexFixer_Logger::log('Generowanie URL autoryzacji', 'info');
        IndexFixer_Logger::log(sprintf('Client ID: %s', $this->client_id), 'info');
        IndexFixer_Logger::log(sprintf('Redirect URI: %s', $redirect_uri), 'info');
        IndexFixer_Logger::log('WAŻNE: Upewnij się, że powyższy Redirect URI jest dokładnie taki sam w Google Cloud Console', 'warning');
        
        $params = array(
            'client_id' => $this->client_id,
            'redirect_uri' => $redirect_uri,
            'response_type' => 'code',
            'scope' => 'https://www.googleapis.com/auth/webmasters.readonly',
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => $state
        );
        
        if (function_exists('add_query_arg')) {
            $auth_url = add_query_arg($params, 'https://accounts.google.com/o/oauth2/v2/auth');
        } else {
            $auth_url = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
        }
        
        IndexFixer_Logger::log('Wygenerowany URL autoryzacji:', 'info');
        IndexFixer_Logger::log($auth_url, 'info');
        IndexFixer_Logger::log('WAŻNE: Skopiuj dokładnie ten URL do Google Cloud Console jako Authorized redirect URI', 'warning');
        
        return $auth_url;
    }
    
    /**
     * Obsługuje callback z autoryzacji
     */
    public function handle_auth_callback($code) {
        if (empty($this->client_id) || empty($this->client_secret)) {
            IndexFixer_Logger::log('Brak Client ID lub Client Secret podczas callback', 'error');
            return false;
        }
        
        if (!function_exists('admin_url') || !function_exists('wp_remote_post')) {
            IndexFixer_Logger::log('WordPress HTTP API nie jest dostępne', 'error');
            return false;
        }
        
        $redirect_uri = admin_url('admin.php?page=indexfixer');
        
        IndexFixer_Logger::log('Callback z autoryzacji', 'info');
        IndexFixer_Logger::log(sprintf('Redirect URI: %s', $redirect_uri), 'info');
        IndexFixer_Logger::log(sprintf('Code: %s', $code), 'info');
        
        $response = wp_remote_post('https://oauth2.googleapis.com/token', array(
            'body' => array(
                'code' => $code,
                'client_id' => $this->client_id,
                'client_secret' => $this->client_secret,
                'redirect_uri' => $redirect_uri,
                'grant_type' => 'authorization_code'
            )
        ));
        
        if (is_wp_error($response)) {
            IndexFixer_Logger::log(
                sprintf('Błąd podczas pobierania tokenu: %s', $response->get_error_message()),
                'error'
            );
            return false;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        IndexFixer_Logger::log('Odpowiedź z Google:', 'info');
        IndexFixer_Logger::log(print_r($body, true), 'info');
        
        if (isset($body['access_token'])) {
            $this->access_token = $body['access_token'];
            $update_result = function_exists('update_option') ? update_option('indexfixer_gsc_access_token', $this->access_token) : false;
            
            IndexFixer_Logger::log(sprintf('Wynik zapisywania tokenu: %s', $update_result ? 'sukces' : 'błąd'), 'info');
            
            if ($update_result) {
                IndexFixer_Logger::log('Pomyślnie pobrano i zapisano nowy Access Token', 'success');
                IndexFixer_Logger::log(sprintf('Access Token: %s', $this->access_token), 'info');
                return true;
            } else {
                IndexFixer_Logger::log('Błąd podczas zapisywania tokenu w bazie danych', 'error');
                return false;
            }
        }
        
        IndexFixer_Logger::log(
            sprintf('Nieoczekiwana odpowiedź podczas pobierania tokenu: %s', json_encode($body)),
            'error'
        );
        return false;
    }
} 