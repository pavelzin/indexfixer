<?php
/**
 * Automatyczne aktualizacje IndexFixer przez GitHub
 */

if (!defined('ABSPATH')) {
    exit;
}

class IndexFixer_Updater {
    private $plugin_slug;
    private $version;
    private $plugin_path;
    private $plugin_file;
    private $github_username;
    private $github_repo;
    
    public function __construct($plugin_file) {
        $this->plugin_file = $plugin_file;
        $this->plugin_slug = plugin_basename($plugin_file);
        $this->version = INDEXFIXER_VERSION;
        $this->plugin_path = plugin_dir_path($plugin_file);
        $this->github_username = 'pavelzin';
        $this->github_repo = 'indexfixer';
        
        add_filter('pre_set_site_transient_update_plugins', array($this, 'check_for_update'));
        add_filter('plugins_api', array($this, 'plugin_info'), 20, 3);
        add_filter('upgrader_pre_download', array($this, 'download_package'), 10, 3);
    }
    
    /**
     * Sprawdza czy jest dostępna nowa wersja
     */
    public function check_for_update($transient) {
        IndexFixer_Logger::log("🔍 CHECK_FOR_UPDATE wywołane", 'info');
        
        if (empty($transient->checked)) {
            IndexFixer_Logger::log("⚠️ Brak checked plugins w transient", 'warning');
            return $transient;
        }
        
        IndexFixer_Logger::log("📋 Plugin slug: {$this->plugin_slug}", 'info');
        IndexFixer_Logger::log("📋 Checked plugins: " . implode(', ', array_keys($transient->checked)), 'info');
        
        // Sprawdź czy nasz plugin jest w liście sprawdzanych
        if (!isset($transient->checked[$this->plugin_slug])) {
            IndexFixer_Logger::log("⚠️ Nasz plugin NIE JEST w liście sprawdzanych!", 'warning');
            return $transient;
        }
        
        IndexFixer_Logger::log("✅ Nasz plugin jest w liście sprawdzanych", 'info');
        
        // Pobierz informacje o najnowszej wersji z GitHub
        $remote_version = $this->get_remote_version();
        IndexFixer_Logger::log("📊 Aktualna wersja: {$this->version}, GitHub wersja: $remote_version", 'info');
        
        if (version_compare($this->version, $remote_version, '<')) {
            $package_url = $this->get_download_url($remote_version);
            
            $transient->response[$this->plugin_slug] = (object) array(
                'slug' => dirname($this->plugin_slug),
                'plugin' => $this->plugin_slug,
                'new_version' => $remote_version,
                'url' => "https://github.com/{$this->github_username}/{$this->github_repo}",
                'package' => $package_url
            );
            
            IndexFixer_Logger::log("🔄 DODANO AKTUALIZACJĘ: $remote_version (package: $package_url)", 'success');
        } else {
            IndexFixer_Logger::log("ℹ️ Brak nowszej wersji na GitHub", 'info');
        }
        
        return $transient;
    }
    
    /**
     * Pobiera najnowszą wersję z GitHub API
     */
    private function get_remote_version() {
        $request = wp_remote_get("https://api.github.com/repos/{$this->github_username}/{$this->github_repo}/releases/latest");
        
        if (!is_wp_error($request) && wp_remote_retrieve_response_code($request) === 200) {
            $body = wp_remote_retrieve_body($request);
            $data = json_decode($body, true);
            
            if (isset($data['tag_name'])) {
                // Usuń 'v' z początku tagu jeśli istnieje (np. v1.0.32 -> 1.0.32)
                return ltrim($data['tag_name'], 'v');
            }
        }
        
        return $this->version; // Zwróć aktualną wersję jeśli nie można pobrać
    }
    
    /**
     * Generuje URL do pobrania najnowszej wersji
     */
    private function get_download_url($version) {
        // Sprawdź czy istnieje asset w release (nasze niestandardowe archiwum)
        $request = wp_remote_get("https://api.github.com/repos/{$this->github_username}/{$this->github_repo}/releases/tags/v{$version}");
        
        if (!is_wp_error($request) && wp_remote_retrieve_response_code($request) === 200) {
            $body = wp_remote_retrieve_body($request);
            $data = json_decode($body, true);
            
            // Sprawdź czy są assety (nasze ZIP-y)
            if (isset($data['assets']) && !empty($data['assets'])) {
                foreach ($data['assets'] as $asset) {
                    if (strpos($asset['name'], 'IndexFixer-v') === 0 && strpos($asset['name'], '.zip') !== false) {
                        IndexFixer_Logger::log("📦 Znaleziono niestandardowe archiwum: " . $asset['browser_download_url'], 'info');
                        return $asset['browser_download_url'];
                    }
                }
            }
        }
        
        // Fallback do standardowego GitHub download
        IndexFixer_Logger::log("📦 Używam standardowego GitHub download", 'info');
        return "https://github.com/{$this->github_username}/{$this->github_repo}/archive/refs/tags/v{$version}.zip";
    }
    
    /**
     * Dostarcza informacje o pluginie dla WordPress
     */
    public function plugin_info($false, $action, $response) {
        if ($action !== 'plugin_information') {
            return $false;
        }
        
        if ($response->slug !== dirname($this->plugin_slug)) {
            return $false;
        }
        
        $remote_version = $this->get_remote_version();
        
        return (object) array(
            'name' => 'IndexFixer',
            'slug' => dirname($this->plugin_slug),
            'version' => $remote_version,
            'author' => 'Pawel Zinkiewicz',
            'homepage' => "https://github.com/{$this->github_username}/{$this->github_repo}",
            'short_description' => 'Wtyczka do sprawdzania statusu indeksowania URL-i w Google Search Console',
            'sections' => array(
                'description' => 'IndexFixer automatycznie sprawdza status indeksowania Twoich URL-ów w Google Search Console i wyświetla niezaindeksowane posty w widgetach.',
                'changelog' => $this->get_changelog()
            ),
            'download_link' => $this->get_download_url($remote_version),
            'requires' => '5.0',
            'tested' => '6.4',
            'requires_php' => '7.4',
            'last_updated' => date('Y-m-d H:i:s'),
            'banners' => array(),
            'icons' => array()
        );
    }
    
    /**
     * Pobiera changelog z GitHub
     */
    private function get_changelog() {
        $request = wp_remote_get("https://api.github.com/repos/{$this->github_username}/{$this->github_repo}/releases");
        
        if (!is_wp_error($request) && wp_remote_retrieve_response_code($request) === 200) {
            $body = wp_remote_retrieve_body($request);
            $releases = json_decode($body, true);
            
            $changelog = '';
            foreach (array_slice($releases, 0, 5) as $release) { // Ostatnie 5 wersji
                $changelog .= "<h4>Wersja {$release['tag_name']}</h4>";
                $changelog .= "<p>" . nl2br(esc_html($release['body'])) . "</p>";
            }
            
            return $changelog;
        }
        
        return '<p>Sprawdź zmiany na <a href="https://github.com/' . $this->github_username . '/' . $this->github_repo . '/releases">GitHub</a></p>';
    }
    
    /**
     * Obsługuje pobieranie pakietu aktualizacji
     */
    public function download_package($reply, $package, $upgrader) {
        if (strpos($package, "github.com/{$this->github_username}/{$this->github_repo}") !== false) {
            IndexFixer_Logger::log("📦 Pobieranie aktualizacji IndexFixer z GitHub: $package", 'info');
        }
        
        return $reply;
    }
    
    /**
     * Sprawdza ręcznie czy jest dostępna aktualizacja
     */
    public function force_check() {
        IndexFixer_Logger::log("🔄 FORCE_CHECK wywoływane - czyszczę transient", 'info');
        
        delete_site_transient('update_plugins');
        wp_update_plugins();
        
        $remote_version = $this->get_remote_version();
        
        IndexFixer_Logger::log("📊 Force check - aktualna: {$this->version}, GitHub: $remote_version", 'info');
        
        if (version_compare($this->version, $remote_version, '<')) {
            IndexFixer_Logger::log("✅ Aktualizacja dostępna!", 'success');
            return array(
                'update_available' => true,
                'current_version' => $this->version,
                'latest_version' => $remote_version,
                'download_url' => $this->get_download_url($remote_version)
            );
        }
        
        IndexFixer_Logger::log("ℹ️ Brak aktualizacji", 'info');
        return array(
            'update_available' => false,
            'current_version' => $this->version,
            'latest_version' => $remote_version
        );
    }
    
    /**
     * Publiczna metoda do testowania
     */
    public static function test_updater() {
        global $indexfixer_updater;
        if (!$indexfixer_updater) {
            $indexfixer_updater = new self(INDEXFIXER_PLUGIN_DIR . 'indexfixer.php');
        }
        return $indexfixer_updater->force_check();
    }
} 