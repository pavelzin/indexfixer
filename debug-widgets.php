<?php
/**
 * Skrypt debug dla widgetów IndexFixer
 * 
 * UŻYCIE:
 * 1. Wgraj ten plik do katalogu głównego WordPress
 * 2. Otwórz w przeglądarce: yoursite.com/debug-widgets.php
 * 3. Skopiuj wyniki i wyślij do debugowania
 */

// Bezpieczeństwo - dodaj swój klucz debug
define('DEBUG_KEY', 'indexfixer-debug-2024');
if (!isset($_GET['key']) || $_GET['key'] !== DEBUG_KEY) {
    die('❌ Brak dostępu. Użyj: ?key=' . DEBUG_KEY);
}

// Ładuj WordPress
require_once('./wp-config.php');
require_once('./wp-load.php');

echo '<h1>🔍 IndexFixer Widget Debug</h1>';
echo '<pre>';

// 1. Sprawdź czy wtyczka jest aktywna
echo "=== SPRAWDZENIE WTYCZKI ===\n";
$active_plugins = get_option('active_plugins', array());
$indexfixer_active = false;
foreach ($active_plugins as $plugin) {
    if (strpos($plugin, 'indexfixer') !== false) {
        $indexfixer_active = true;
        echo "✅ IndexFixer aktywny: $plugin\n";
    }
}
if (!$indexfixer_active) {
    echo "❌ IndexFixer NIE JEST AKTYWNY!\n";
}

// 2. Sprawdź konfigurację widgetów
echo "\n=== KONFIGURACJA WIDGETÓW ===\n";
$widget_instances = get_option('widget_indexfixer_not_indexed', array());
echo "widget_indexfixer_not_indexed: " . print_r($widget_instances, true) . "\n";

$active_widgets_count = 0;
foreach ($widget_instances as $key => $instance) {
    if (is_array($instance) && !empty($instance['auto_check'])) {
        $active_widgets_count++;
        echo "✅ Aktywny widget #$key: auto_check={$instance['auto_check']}, count=" . ($instance['count'] ?? 5) . "\n";
    } else if (is_array($instance)) {
        echo "⚠️  Widget #$key BEZ auto_check: " . print_r($instance, true) . "\n";
    } else {
        echo "ℹ️  Widget #$key meta: $instance\n";
    }
}
echo "Łącznie aktywnych widgetów z auto_check: $active_widgets_count\n";

// 3. Sprawdź sidebary
echo "\n=== SPRAWDZENIE SIDEBARÓW ===\n";
$sidebars_widgets = get_option('sidebars_widgets', array());
foreach ($sidebars_widgets as $sidebar_id => $widgets) {
    if (is_array($widgets)) {
        foreach ($widgets as $widget) {
            if (strpos($widget, 'indexfixer_not_indexed') !== false) {
                echo "✅ Widget IndexFixer w sidebar '$sidebar_id': $widget\n";
            }
        }
    }
}

// 4. Sprawdź bloki w postach
echo "\n=== SPRAWDZENIE BLOKÓW ===\n";
global $wpdb;
$posts_with_blocks = $wpdb->get_results(
    "SELECT ID, post_title, post_type, post_status, post_content 
     FROM {$wpdb->posts} 
     WHERE post_content LIKE '%wp:indexfixer/not-indexed-posts%'"
);

echo "Znaleziono " . count($posts_with_blocks) . " postów z blokami IndexFixer:\n";
foreach ($posts_with_blocks as $post) {
    echo "- Post #{$post->ID}: {$post->post_title} ({$post->post_type}, {$post->post_status})\n";
    
    // Parsuj blok żeby wyciągnąć parametry
    preg_match('/wp:indexfixer\/not-indexed-posts\s*({[^}]*})?/', $post->post_content, $matches);
    if (!empty($matches[1])) {
        $block_attrs = json_decode($matches[1], true);
        echo "  Parametry bloku: " . print_r($block_attrs, true) . "\n";
    } else {
        echo "  Blok bez parametrów (domyślne)\n";
    }
}

// 5. Sprawdź tabelę bazy danych
echo "\n=== SPRAWDZENIE TABELI BAZY DANYCH ===\n";
$table_name = $wpdb->prefix . 'indexfixer_urls';
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name;

if ($table_exists) {
    echo "✅ Tabela $table_name istnieje\n";
    
    // Sprawdź strukturę tabeli
    $columns = $wpdb->get_results("DESCRIBE $table_name");
    echo "Kolumny tabeli:\n";
    foreach ($columns as $column) {
        echo "- {$column->Field} ({$column->Type})\n";
    }
    
    // Statystyki ogólne
    $total_rows = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
    echo "\nŁączna liczba wierszy: $total_rows\n";
    
    // Statystyki statusów
    $status_stats = $wpdb->get_results("
        SELECT status, verdict, coverage_state, COUNT(*) as count 
        FROM $table_name 
        GROUP BY status, verdict, coverage_state 
        ORDER BY count DESC
    ");
    
    echo "\nStatystyki statusów:\n";
    foreach ($status_stats as $stat) {
        echo "- status: '{$stat->status}', verdict: '{$stat->verdict}', coverage_state: '{$stat->coverage_state}' => {$stat->count} URL-ów\n";
    }
    
    // Przykładowe URL-e NOT_INDEXED
    echo "\nPrzykładowe URL-e z verdict='NEUTRAL' i coverage_state LIKE '%not indexed%':\n";
    $not_indexed_samples = $wpdb->get_results("
        SELECT url, status, verdict, coverage_state, last_checked 
        FROM $table_name 
        WHERE verdict = 'NEUTRAL' AND coverage_state LIKE '%not indexed%' 
        LIMIT 5
    ");
    
    foreach ($not_indexed_samples as $sample) {
        echo "- {$sample->url} (status: {$sample->status}, last_checked: {$sample->last_checked})\n";
    }
    
    if (empty($not_indexed_samples)) {
        echo "❌ BRAK URL-ów spełniających kryteria 'not_indexed'!\n";
        
        // Sprawdź co jest w tabeli
        echo "\nPierwsze 5 URL-ów w tabeli (niezależnie od statusu):\n";
        $any_samples = $wpdb->get_results("
            SELECT url, status, verdict, coverage_state, last_checked 
            FROM $table_name 
            LIMIT 5
        ");
        
        foreach ($any_samples as $sample) {
            echo "- {$sample->url} (status: {$sample->status}, verdict: {$sample->verdict}, coverage_state: {$sample->coverage_state})\n";
        }
    }
    
} else {
    echo "❌ Tabela $table_name NIE ISTNIEJE!\n";
}

// 6. Test funkcji get_all_widget_urls()
echo "\n=== TEST FUNKCJI get_all_widget_urls() ===\n";
if (class_exists('IndexFixer_Widget_Scheduler')) {
    try {
        $widget_urls = IndexFixer_Widget_Scheduler::get_all_widget_urls();
        echo "Funkcja zwróciła " . count($widget_urls) . " URL-ów:\n";
        
        foreach ($widget_urls as $i => $url_data) {
            echo "[$i] {$url_data->url} - {$url_data->widget_source} (count: {$url_data->widget_count})\n";
            if ($i >= 4) {
                echo "... (showing first 5)\n";
                break;
            }
        }
    } catch (Exception $e) {
        echo "❌ Błąd wywołania funkcji: " . $e->getMessage() . "\n";
    }
} else {
    echo "❌ Klasa IndexFixer_Widget_Scheduler nie istnieje!\n";
}

// 7. Sprawdź logi
echo "\n=== OSTATNIE LOGI ===\n";
if (class_exists('IndexFixer_Logger')) {
    $logs = get_option('indexfixer_logs', array());
    $recent_logs = array_slice(array_reverse($logs), 0, 10);
    
    foreach ($recent_logs as $log) {
        echo "[{$log['timestamp']}] {$log['level']}: {$log['message']}\n";
    }
} else {
    echo "❌ Klasa IndexFixer_Logger nie istnieje!\n";
}

echo '</pre>';

echo '<hr><p><strong>Instrukcje:</strong></p>';
echo '<ol>';
echo '<li>Skopiuj całe powyższe wyjście</li>';
echo '<li>Sprawdź czy masz aktywny widget z włączonym auto_check</li>';
echo '<li>Sprawdź czy w tabeli są URL-e spełniające kryteria not_indexed</li>';
echo '<li>Wyślij wyniki do analizy</li>';
echo '</ol>';

echo '<p><strong>Możliwe problemy:</strong></p>';
echo '<ul>';
echo '<li>Brak aktywnych widgetów z auto_check=true</li>';
echo '<li>Brak URL-ów w tabeli z odpowiednim statusem (verdict=NEUTRAL + coverage_state LIKE %not indexed%)</li>';
echo '<li>Tabela bazy danych nie istnieje lub ma złą strukturę</li>';
echo '<li>Widget nie został zapisany poprawnie w sidebarze</li>';
echo '</ul>';
?> 