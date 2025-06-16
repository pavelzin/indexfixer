<?php
/**
 * Skrypt do czyszczenia cache i odblokowywania procesu IndexFixer
 * Uruchom bezpośrednio w przeglądarce gdy proces się zawiesił
 */

// Sprawdź czy to WordPress
if (!defined('ABSPATH')) {
    // Spróbuj załadować WordPress
    $wp_config_path = dirname(__FILE__) . '/../../../wp-config.php';
    if (file_exists($wp_config_path)) {
        require_once($wp_config_path);
    } else {
        die('Nie można znaleźć WordPress. Uruchom z poziomu wp-admin.');
    }
}

// Sprawdź uprawnienia (tylko admin)
if (!current_user_can('manage_options')) {
    die('Brak uprawnień administratora.');
}

echo '<h1>🔧 IndexFixer - Odblokowanie Procesu</h1>';

echo '<div style="background: #fff3cd; padding: 15px; border-left: 3px solid #ffc107; margin: 20px 0;">';
echo '<h3>⚠️ UWAGA:</h3>';
echo '<p>Ten skrypt odblokuje proces sprawdzania IndexFixer jeśli się zawiesił.</p>';
echo '<p>Użyj tylko gdy widzisz komunikat "PROCES JEST JUŻ URUCHOMIONY - pomijam".</p>';
echo '</div>';

// Sprawdź obecny stan
$process_running = get_transient('indexfixer_process_running');
$process_time = get_transient_timeout('indexfixer_process_running');

echo '<h2>📊 Stan procesu:</h2>';
echo '<ul>';
echo '<li><strong>Flaga procesu:</strong> ' . ($process_running ? '🔴 ZABLOKOWANY' : '🟢 WOLNY') . '</li>';
if ($process_running && $process_time) {
    $remaining = $process_time - time();
    echo '<li><strong>Czas do odblokowania:</strong> ' . ($remaining > 0 ? $remaining . ' sekund' : 'Przedawniony') . '</li>';
}
echo '</ul>';

// Formularz odblokowania
if (isset($_POST['unlock_process'])) {
    delete_transient('indexfixer_process_running');
    echo '<div style="background: #d1ecf1; padding: 15px; border-left: 3px solid #0dcaf0; margin: 20px 0;">';
    echo '<h3>✅ SUKCES!</h3>';
    echo '<p>Proces został odblokowany. Możesz teraz uruchomić sprawdzanie URL-ów.</p>';
    echo '</div>';
    
    // Sprawdź ponownie
    $process_running = get_transient('indexfixer_process_running');
    echo '<p><strong>Nowy stan:</strong> ' . ($process_running ? '🔴 NADAL ZABLOKOWANY' : '🟢 ODBLOKOWANY') . '</p>';
    
    echo '<p><a href="' . admin_url('admin.php?page=indexfixer') . '" class="button button-primary">🔙 Wróć do IndexFixer</a></p>';
} else {
    // Pokaż formularz tylko jeśli proces jest zablokowany
    if ($process_running) {
        echo '<form method="post" style="margin: 20px 0;">';
        echo '<h2>🔓 Odblokuj proces:</h2>';
        echo '<p><input type="submit" name="unlock_process" value="Odblokuj proces sprawdzania" class="button button-primary" onclick="return confirm(\'Czy na pewno chcesz odblokować proces?\')"></p>';
        echo '</form>';
    } else {
        echo '<div style="background: #d1ecf1; padding: 15px; border-left: 3px solid #0dcaf0; margin: 20px 0;">';
        echo '<h3>✅ Proces jest już odblokowany!</h3>';
        echo '<p>Możesz uruchomić sprawdzanie URL-ów.</p>';
        echo '</div>';
        
        echo '<p><a href="' . admin_url('admin.php?page=indexfixer') . '" class="button button-primary">🔙 Wróć do IndexFixer</a></p>';
    }
}

// Dodatkowe informacje debug
echo '<h2>🔍 Debug informacje:</h2>';
echo '<ul>';
echo '<li><strong>Bieżący czas:</strong> ' . date('Y-m-d H:i:s') . '</li>';
echo '<li><strong>WordPress timezone:</strong> ' . get_option('timezone_string', 'UTC') . '</li>';

// Sprawdź inne transients IndexFixer
global $wpdb;
$transients = $wpdb->get_results(
    "SELECT option_name, option_value FROM {$wpdb->options} 
     WHERE option_name LIKE '_transient_indexfixer%' 
     OR option_name LIKE '_transient_timeout_indexfixer%'
     ORDER BY option_name"
);

if ($transients) {
    echo '<li><strong>Aktywne transients:</strong></li>';
    echo '<ul>';
    foreach ($transients as $transient) {
        $clean_name = str_replace(['_transient_', '_transient_timeout_'], '', $transient->option_name);
        echo '<li>' . $clean_name . ' = ' . $transient->option_value . '</li>';
    }
    echo '</ul>';
} else {
    echo '<li><strong>Transients:</strong> Brak aktywnych</li>';
}
echo '</ul>';

echo '<style>
body { font-family: -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Oxygen-Sans,Ubuntu,Cantarell,"Helvetica Neue",sans-serif; margin: 40px; }
.button { background: #2271b1; color: white; padding: 8px 16px; text-decoration: none; border-radius: 3px; border: none; cursor: pointer; }
.button:hover { background: #135e96; }
.button-primary { background: #2271b1; }
</style>';
?> 