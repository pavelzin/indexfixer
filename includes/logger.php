<?php
/**
 * Obsługa logów
 */

if (!defined('ABSPATH')) {
    exit;
}

class IndexFixer_Logger {
    private static $log_option = 'indexfixer_logs';
    private static $max_logs = 100; // Maksymalna liczba wpisów w logu
    
    /**
     * Dodaje wpis do logu
     */
    public static function log($message, $type = 'info') {
        $logs = get_option(self::$log_option, array());
        
        $logs[] = array(
            'timestamp' => current_time('mysql'),
            'message' => $message,
            'type' => $type
        );
        
        // Ogranicz liczbę wpisów
        if (count($logs) > self::$max_logs) {
            $logs = array_slice($logs, -self::$max_logs);
        }
        
        update_option(self::$log_option, $logs);
    }
    
    /**
     * Pobiera wszystkie logi
     */
    public static function get_logs() {
        return get_option(self::$log_option, array());
    }
    
    /**
     * Czyści logi
     */
    public static function clear_logs() {
        delete_option(self::$log_option);
    }
    
    /**
     * Formatuje logi do wyświetlenia
     */
    public static function format_logs() {
        $logs = self::get_logs();
        $output = '';
        
        foreach (array_reverse($logs) as $log) {
            $class = 'info';
            switch ($log['type']) {
                case 'error':
                    $class = 'error';
                    break;
                case 'success':
                    $class = 'success';
                    break;
                case 'warning':
                    $class = 'warning';
                    break;
            }
            
            $output .= sprintf(
                '<div class="indexfixer-log-entry %s"><strong>%s</strong>: %s</div>',
                $class,
                $log['timestamp'],
                esc_html($log['message'])
            );
        }
        
        return $output;
    }
} 