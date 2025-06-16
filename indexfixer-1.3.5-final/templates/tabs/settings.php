<?php
/**
 * Zakładka "Ustawienia" w dashboardzie
 */

if (!defined('ABSPATH')) {
    exit;
}

// Pobierz aktualne ustawienia
$settings = get_option('indexfixer_settings', array());
$active_post_types = isset($settings['post_types']) ? $settings['post_types'] : array('post', 'page');

// Pobierz dostępne typy postów
$post_types = get_post_types(['public' => true], 'objects');

// Obsługa zapisu ustawień
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['indexfixer_settings_save'])) {
    check_admin_referer('indexfixer_settings');
    $old_settings = get_option('indexfixer_settings', array());
    $new_settings = array(
        'post_types' => isset($_POST['post_types']) ? array_map('sanitize_text_field', (array)$_POST['post_types']) : array('post'),
        'check_interval' => isset($_POST['check_interval']) ? intval($_POST['check_interval']) : 24,
        'max_urls_per_check' => isset($_POST['max_urls_per_check']) ? intval($_POST['max_urls_per_check']) : 50,
        'auto_refresh' => isset($_POST['auto_refresh']) ? 1 : 0,
        'debug_mode' => isset($_POST['debug_mode']) ? 1 : 0,
    );
    $merged_settings = array_merge($old_settings, $new_settings);
    update_option('indexfixer_settings', $merged_settings);
    echo '<div class="notice notice-success"><p>Ustawienia zostały zapisane.</p></div>';
}
?>

<div class="indexfixer-settings">
    <h2>⚙️ Ustawienia</h2>
    
    <form method="post" action="">
        <?php wp_nonce_field('indexfixer_settings'); ?>
        
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="post_types">Typy postów do sprawdzania</label>
                </th>
                <td>
                    <fieldset>
                        <legend class="screen-reader-text">
                            <span>Typy postów do sprawdzania</span>
                        </legend>
                        
                        <?php foreach ($post_types as $post_type): ?>
                            <label>
                                <input type="checkbox" 
                                       name="post_types[]" 
                                       value="<?php echo esc_attr($post_type->name); ?>"
                                       <?php checked(in_array($post_type->name, $active_post_types)); ?>>
                                <?php echo esc_html($post_type->label); ?>
                                <span class="description">
                                    (<?php echo esc_html($post_type->name); ?>)
                                </span>
                            </label>
                            <br>
                        <?php endforeach; ?>
                        
                        <p class="description">
                            Wybierz typy postów, które mają być sprawdzane przez IndexFixer.
                            Możesz wybrać dowolną kombinację typów postów, w tym własne typy postów.
                        </p>
                    </fieldset>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="check_interval">Interwał sprawdzania (godziny)</label>
                </th>
                <td>
                    <input type="number" 
                           name="check_interval" 
                           id="check_interval" 
                           value="<?php echo esc_attr($settings['check_interval'] ?? 24); ?>"
                           min="1" 
                           max="168"
                           class="small-text">
                    <p class="description">
                        Jak często (w godzinach) IndexFixer ma sprawdzać status URL-i.
                        Minimalna wartość to 1 godzina, maksymalna to 168 godzin (7 dni).
                    </p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="max_urls_per_check">Maksymalna liczba URL-i na sprawdzenie</label>
                </th>
                <td>
                    <input type="number" 
                           name="max_urls_per_check" 
                           id="max_urls_per_check" 
                           value="<?php echo esc_attr($settings['max_urls_per_check'] ?? 100); ?>"
                           min="10" 
                           max="1000"
                           class="small-text">
                    <p class="description">
                        Maksymalna liczba URL-i, które będą sprawdzane podczas jednego cyklu.
                        Minimalna wartość to 10, maksymalna to 1000.
                    </p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">Automatyczne odświeżanie</th>
                <td>
                    <fieldset>
                        <legend class="screen-reader-text">
                            <span>Automatyczne odświeżanie</span>
                        </legend>
                        
                        <label>
                            <input type="checkbox" 
                                   name="auto_refresh" 
                                   value="1"
                                   <?php checked($settings['auto_refresh'] ?? false); ?>>
                            Automatycznie odświeżaj dane na dashboardzie
                        </label>
                        
                        <p class="description">
                            Jeśli zaznaczono, dane na dashboardzie będą automatycznie odświeżane co 5 minut.
                            Przydatne podczas monitorowania statusu URL-i.
                        </p>
                    </fieldset>
                </td>
            </tr>
            
            <tr>
                <th scope="row">Tryb debugowania</th>
                <td>
                    <fieldset>
                        <legend class="screen-reader-text">
                            <span>Tryb debugowania</span>
                        </legend>
                        
                        <label>
                            <input type="checkbox" 
                                   name="debug_mode" 
                                   value="1"
                                   <?php checked($settings['debug_mode'] ?? false); ?>>
                            Włącz tryb debugowania
                        </label>
                        
                        <p class="description">
                            Jeśli zaznaczono, IndexFixer będzie zapisywał dodatkowe informacje debugowania.
                            Przydatne podczas rozwiązywania problemów.
                        </p>
                    </fieldset>
                </td>
            </tr>
        </table>
        
        <p class="submit">
            <input type="submit" 
                   name="indexfixer_settings_save" 
                   class="button button-primary" 
                   value="Zapisz ustawienia">
        </p>
    </form>
</div>

<style>
.indexfixer-settings .form-table th {
    width: 200px;
}

.indexfixer-settings .form-table td {
    padding: 15px 10px;
}

.indexfixer-settings .description {
    color: #666;
    font-style: italic;
    margin-top: 5px;
}

.indexfixer-settings fieldset {
    margin: 0;
    padding: 0;
}

.indexfixer-settings fieldset label {
    display: block;
    margin-bottom: 8px;
}

.indexfixer-settings .submit {
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid #ddd;
}
</style> 