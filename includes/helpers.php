<?php
/**
 * Funkcje pomocnicze
 */

if (!defined('ABSPATH')) {
    exit;
}

class IndexFixer_Helpers {
    /**
     * Formatuje status indeksacji na czytelną formę
     * 
     * @param string $status Status z GSC
     * @return string Sformatowany status
     */
    public static function format_index_status($status) {
        $statuses = array(
            'INDEXED' => 'Zaindeksowany',
            'NOT_INDEXED' => 'Nie zaindeksowany',
            'PENDING' => 'Oczekujący',
            'EXCLUDED' => 'Wykluczony',
            'unknown' => 'Nieznany'
        );
        
        return isset($statuses[$status]) ? $statuses[$status] : $status;
    }
    
    /**
     * Generuje link do edycji posta
     * 
     * @param int $post_id ID posta
     * @return string URL do edycji
     */
    public static function get_edit_post_link($post_id) {
        return get_edit_post_link($post_id);
    }
    
    /**
     * Generuje link do podglądu posta
     * 
     * @param int $post_id ID posta
     * @return string URL do podglądu
     */
    public static function get_preview_post_link($post_id) {
        return get_preview_post_link($post_id);
    }
    
    /**
     * Sprawdza czy użytkownik ma uprawnienia do zarządzania wtyczką
     * 
     * @return bool
     */
    public static function can_manage_plugin() {
        return current_user_can('manage_options');
    }
    
    /**
     * Generuje komunikat o błędzie
     * 
     * @param string $message Treść komunikatu
     * @return string HTML komunikatu
     */
    public static function error_message($message) {
        return '<div class="indexfixer-message error">' . esc_html($message) . '</div>';
    }
    
    /**
     * Generuje komunikat o sukcesie
     * 
     * @param string $message Treść komunikatu
     * @return string HTML komunikatu
     */
    public static function success_message($message) {
        return '<div class="indexfixer-message success">' . esc_html($message) . '</div>';
    }
    
    /**
     * Generuje komunikat ostrzegawczy
     */
    public static function warning_message($message) {
        return '<div class="indexfixer-message warning">' . esc_html($message) . '</div>';
    }
    
    /**
     * Formatuje verdict z Google Search Console
     * 
     * @param string $verdict Verdict z API
     * @return string Sformatowany verdict
     */
    public static function format_verdict($verdict) {
        $verdicts = array(
            'PASS' => 'PASS – Google potwierdza, że strona może być zaindeksowana',
            'NEUTRAL' => 'NEUTRAL – Google nie mówi wprost, czy zaindeksuje, ale też nie planuje tego natychmiast',
            'FAIL' => 'FAIL – Google wykrył problemy, które mogą uniemożliwić indeksację'
        );
        
        return isset($verdicts[$verdict]) ? $verdicts[$verdict] : $verdict;
    }
    
    /**
     * Formatuje coverage state z Google Search Console
     * 
     * @param string $coverage_state Coverage state z API
     * @return string Sformatowany coverage state
     */
    public static function format_coverage_state($coverage_state) {
        $states = array(
            'Submitted and indexed' => 'strona została wysłana i zaindeksowana',
            'Crawled - currently not indexed' => 'strona została pobrana przez robota, ale nie została zaindeksowana',
            'Discovered - currently not indexed' => 'strona została odkryta, ale nie została zaindeksowana',
            'Page with redirect' => 'strona z przekierowaniem',
            'Excluded by robots.txt' => 'wykluczona przez robots.txt',
            'Blocked due to unauthorized request (401)' => 'zablokowana przez błąd 401',
            'Not found (404)' => 'nie znaleziono (404)'
        );
        
        return isset($states[$coverage_state]) ? $states[$coverage_state] : $coverage_state;
    }
} 