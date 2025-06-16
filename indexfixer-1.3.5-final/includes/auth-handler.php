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
    private $refresh_token;
    
    /**
     * Konstruktor
     */
    public function __construct() {
        // Sprawdź czy WordPress jest dostępny
        if (function_exists('get_option')) {
            $this->client_id = get_option('indexfixer_gsc_client_id');
            $this->client_secret = get_option('indexfixer_gsc_client_secret');
            $this->access_token = get_option('indexfixer_gsc_access_token');
            $this->refresh_token = get_option('indexfixer_gsc_refresh_token');
            

        }
    }
    
    /**
     * Przeładowuje tokeny z bazy danych (przydatne po ich usunięciu)
     */
    public function reload_tokens_from_database() {
        if (function_exists('get_option')) {
            $this->access_token = get_option('indexfixer_gsc_access_token');
            $this->refresh_token = get_option('indexfixer_gsc_refresh_token');
            
            IndexFixer_Logger::log('Przeładowano tokeny z bazy danych', 'info');
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
            IndexFixer_Logger::log('Brak Client ID lub Client Secret', 'debug');
            return false;
        }
        
        if (empty($this->access_token)) {
            IndexFixer_Logger::log('Brak Access Token', 'debug');
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
                'debug'
            );
            return false;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            IndexFixer_Logger::log(
                sprintf('Token nieważny (kod %d)', $response_code),
                'debug'
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
        
        $auth_url = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
        
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
            
            // Zapisz refresh token jeśli jest dostępny
            if (isset($body['refresh_token'])) {
                $this->refresh_token = $body['refresh_token'];
                update_option('indexfixer_gsc_refresh_token', $this->refresh_token);
                IndexFixer_Logger::log('Zapisano Refresh Token', 'success');
            }
            
            // NOWE: Zapisz czas wygaśnięcia tokenu przy pierwszej autoryzacji
            $expires_at = time() + 3600; // 1 godzina domyślnie
            if (isset($body['expires_in'])) {
                $expires_at = time() + intval($body['expires_in']);
            }
            update_option('indexfixer_gsc_token_expires_at', $expires_at);
            IndexFixer_Logger::log(sprintf('Zapisano czas wygaśnięcia tokenu: %s (UTC)', gmdate('Y-m-d H:i:s', $expires_at)), 'info');
            
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
    
    /**
     * Odnawia access token używając refresh token
     */
    public function refresh_access_token() {
        if (empty($this->refresh_token)) {
            IndexFixer_Logger::log('Brak Refresh Token - wymagana ponowna autoryzacja', 'error');
            return false;
        }
        
        if (empty($this->client_id) || empty($this->client_secret)) {
            IndexFixer_Logger::log('Brak Client ID lub Client Secret podczas odnawiania tokenu', 'error');
            return false;
        }
        
        IndexFixer_Logger::log('Próba odnawiania Access Token...', 'info');
        
        $response = wp_remote_post('https://oauth2.googleapis.com/token', array(
            'body' => array(
                'client_id' => $this->client_id,
                'client_secret' => $this->client_secret,
                'refresh_token' => $this->refresh_token,
                'grant_type' => 'refresh_token'
            )
        ));
        
        if (is_wp_error($response)) {
            IndexFixer_Logger::log(
                sprintf('Błąd podczas odnawiania tokenu: %s', $response->get_error_message()),
                'error'
            );
            return false;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        // Dodaj szczegółowe logowanie odpowiedzi Google
        IndexFixer_Logger::log(
            sprintf('Odpowiedź Google przy refresh (kod %d): %s', $response_code, wp_remote_retrieve_body($response)),
            'info'
        );
        
        if (isset($body['access_token'])) {
            $this->access_token = $body['access_token'];
            update_option('indexfixer_gsc_access_token', $this->access_token);
            
            // NOWE: Zapisz nowy czas wygaśnięcia tokenu (Google tokeny wygasają po 1 godzinie)
            $expires_at = time() + 3600; // 1 godzina
            if (isset($body['expires_in'])) {
                $expires_at = time() + intval($body['expires_in']);
            }
            update_option('indexfixer_gsc_token_expires_at', $expires_at);
            
            IndexFixer_Logger::log('Access Token został pomyślnie odnowiony', 'success');
            IndexFixer_Logger::log(sprintf('Nowy token wygasa: %s (UTC)', gmdate('Y-m-d H:i:s', $expires_at)), 'info');
            IndexFixer_Logger::log(sprintf('Nowy token wygasa: %s (lokalny)', date('Y-m-d H:i:s', $expires_at)), 'info');
            return true;
        }
        
        // Jeśli Google zwraca błąd 400 lub inne błędy związane z refresh tokenem
        if ($response_code === 400 || (isset($body['error']) && in_array($body['error'], ['invalid_grant', 'invalid_client']))) {
            IndexFixer_Logger::log('Refresh token jest nieważny - usuwam tokeny i wymuszam ponowną autoryzację', 'warning');
            
            // Usuń wszystkie tokeny z bazy
            delete_option('indexfixer_gsc_access_token');
            delete_option('indexfixer_gsc_refresh_token');
            delete_option('indexfixer_gsc_token_expires_at'); // NOWE: Usuń również czas wygaśnięcia
            
            // Wyczyść tokeny w obiekcie
            $this->access_token = '';
            $this->refresh_token = '';
            
            // Zaloguj wymaganie ponownej autoryzacji
            IndexFixer_Logger::log('WYMAGANA PONOWNA AUTORYZACJA: Przejdź do IndexFixer i kliknij "Zaloguj się przez Google"', 'error');
            
            return false;
        }
        
        IndexFixer_Logger::log(
            sprintf('Błąd odnawiania tokenu: %s', json_encode($body)),
            'error'
        );
        return false;
    }
    
    /**
     * Sprawdza czy użytkownik jest autoryzowany z automatycznym odnawianiem tokenu
     */
    public function is_authorized_with_refresh() {
        // Najpierw sprawdź podstawowe wymagania
        if (empty($this->client_id) || empty($this->client_secret)) {
            IndexFixer_Logger::log('Brak Client ID lub Client Secret', 'debug');
            return false;
        }
        
        if (empty($this->access_token)) {
            IndexFixer_Logger::log('Brak Access Token', 'debug');
            return false;
        }
        
        // POPRAWKA: Sprawdź czy token wygasa w ciągu najbliższych 5 minut (proaktywne odnawianie)
        $token_expires_at = get_option('indexfixer_gsc_token_expires_at', 0);
        $current_time = time();
        
        // NOWA LOGIKA: Jeśli nie ma expires_at LUB token wygasa za mniej niż 5 minut
        if ($token_expires_at == 0 || ($token_expires_at - $current_time < 300)) {
            IndexFixer_Logger::log('Token wymaga sprawdzenia/odnowienia (brak expires_at lub wygasa za <5min)', 'info');
            
            // Jeśli nie ma expires_at, sprawdź token przez API
            if ($token_expires_at == 0) {
                $response = wp_remote_get(
                    'https://www.googleapis.com/oauth2/v1/tokeninfo?access_token=' . $this->access_token
                );
                
                if (is_wp_error($response)) {
                    IndexFixer_Logger::log('Błąd weryfikacji tokenu - próba odnowienia', 'info');
                    return $this->refresh_access_token();
                }
                
                $response_code = wp_remote_retrieve_response_code($response);
                if ($response_code !== 200) {
                    IndexFixer_Logger::log('Token nieważny - próba odnowienia', 'info');
                    return $this->refresh_access_token();
                }
                
                // Zapisz czas wygaśnięcia na podstawie odpowiedzi Google
                $body = json_decode(wp_remote_retrieve_body($response), true);
                if (isset($body['expires_in'])) {
                    $expires_at = $current_time + intval($body['expires_in']);
                    update_option('indexfixer_gsc_token_expires_at', $expires_at);
                    IndexFixer_Logger::log(sprintf('Zapisano czas wygaśnięcia tokenu: %s (UTC)', gmdate('Y-m-d H:i:s', $expires_at)), 'info');
                    
                    // Sprawdź czy wygasa za mniej niż 5 minut
                    if ($expires_at - $current_time < 300) {
                        IndexFixer_Logger::log('Token wygasa za mniej niż 5 minut - odnawiam', 'info');
                        return $this->refresh_access_token();
                    }
                }
            } else {
                // Mamy expires_at i wygasa za mniej niż 5 minut
                IndexFixer_Logger::log('Token wygasa za mniej niż 5 minut - proaktywne odnawianie', 'info');
                return $this->refresh_access_token();
            }
        }
        
        IndexFixer_Logger::log('Autoryzacja poprawna', 'success');
        return true;
    }
    
    /**
     * Pobiera informacje o czasie wygaśnięcia tokenu
     */
    public function get_token_expiry_info() {
        $expires_at = get_option('indexfixer_gsc_token_expires_at', 0);
        if ($expires_at == 0) {
            return array(
                'expires_at' => 0,
                'expires_in_minutes' => null,
                'is_expired' => false,
                'expires_soon' => false
            );
        }
        
        $current_time = time();
        $expires_in_seconds = $expires_at - $current_time;
        $expires_in_minutes = round($expires_in_seconds / 60);
        
        return array(
            'expires_at' => $expires_at,
            'expires_at_formatted' => date('Y-m-d H:i:s', $expires_at),
            'expires_in_minutes' => $expires_in_minutes,
            'is_expired' => $expires_in_seconds <= 0,
            'expires_soon' => $expires_in_seconds > 0 && $expires_in_seconds < 300 // 5 minut
        );
    }
    
    /**
     * Automatyczne odnawianie tokenów (wywoływane przez cron co 30 minut)
     */
    public static function auto_refresh_tokens() {
        IndexFixer_Logger::log('🔄 CRON: Rozpoczynam automatyczne sprawdzanie tokenów', 'info');
        
        $auth_handler = new self();
        
        // Jeśli dane nie zostały załadowane przez konstruktor, spróbuj załadować bezpośrednio
        if (empty($auth_handler->client_id) && function_exists('get_option')) {
            IndexFixer_Logger::log('⚠️ CRON: Dane nie załadowane przez konstruktor - próbuję załadować bezpośrednio', 'warning');
            
            $auth_handler->client_id = get_option('indexfixer_gsc_client_id');
            $auth_handler->client_secret = get_option('indexfixer_gsc_client_secret'); 
            $auth_handler->access_token = get_option('indexfixer_gsc_access_token');
            $auth_handler->refresh_token = get_option('indexfixer_gsc_refresh_token');
            
            IndexFixer_Logger::log('✅ CRON: Dane załadowane bezpośrednio', 'info');
        }
        
        // Dodaj szczegółowe logowanie co mamy załadowane
        IndexFixer_Logger::log(sprintf('Client ID: %s', empty($auth_handler->client_id) ? 'BRAK' : 'OK'), 'info');
        IndexFixer_Logger::log(sprintf('Client Secret: %s', empty($auth_handler->client_secret) ? 'BRAK' : 'OK'), 'info');
        IndexFixer_Logger::log(sprintf('Access Token: %s', empty($auth_handler->access_token) ? 'BRAK' : 'OK'), 'info');
        IndexFixer_Logger::log(sprintf('Refresh Token: %s', empty($auth_handler->refresh_token) ? 'BRAK' : 'OK'), 'info');
        
        // Sprawdź czy mamy podstawowe dane
        if (empty($auth_handler->client_id) || empty($auth_handler->client_secret)) {
            IndexFixer_Logger::log('❌ Brak Client ID lub Client Secret - cron przerwany. Skonfiguruj OAuth w dashboardzie IndexFixer.', 'warning');
            return;
        }
        
        if (empty($auth_handler->access_token)) {
            IndexFixer_Logger::log('❌ Brak Access Token - cron przerwany. Zaloguj się przez Google w dashboardzie IndexFixer.', 'warning');
            return;
        }
        
        $token_expires_at = get_option('indexfixer_gsc_token_expires_at', 0);
        $current_time = time();
        
        // Jeśli token wygasa w ciągu 45 minut - odnów go
        if ($token_expires_at > 0 && ($token_expires_at - $current_time < 2700)) { // 45 minut = 2700 sekund
            $minutes_left = round(($token_expires_at - $current_time) / 60);
            
            IndexFixer_Logger::log("🔄 AUTOMATYCZNE ODNAWIANIE TOKENU - wygasa za $minutes_left minut", 'info');
            
            if (empty($auth_handler->refresh_token)) {
                IndexFixer_Logger::log('❌ Brak Refresh Token - nie można automatycznie odnowić. Wymagana ponowna autoryzacja w dashboardzie IndexFixer.', 'error');
                return;
            }
            
            $result = $auth_handler->refresh_access_token();
            
            if ($result) {
                IndexFixer_Logger::log('✅ Token automatycznie odnowiony przez cron', 'success');
            } else {
                IndexFixer_Logger::log('❌ Nie udało się automatycznie odnowić tokenu', 'error');
            }
        } else {
            $token_info = $auth_handler->get_token_expiry_info();
            if ($token_info['expires_at'] > 0) {
                $minutes_left = $token_info['expires_in_minutes'];
                IndexFixer_Logger::log("✅ Token jeszcze ważny - wygasa za $minutes_left minut", 'info');
            } else {
                IndexFixer_Logger::log('ℹ️ Brak informacji o wygaśnięciu tokenu', 'info');
            }
        }
    }
} 