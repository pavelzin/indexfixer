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
        if (empty($transient->checked)) {
            return $transient;
        }
        
        // Sprawdź czy nasz plugin jest w liście sprawdzanych
        if (!isset($transient->checked[$this->plugin_slug])) {
            return $transient;
        }
        
        // Pobierz informacje o najnowszej wersji z GitHub
        $remote_version = $this->get_remote_version();
        
        if (version_compare($this->version, $remote_version, '<')) {
            $transient->response[$this->plugin_slug] = (object) array(
                'slug' => dirname($this->plugin_slug),
                'plugin' => $this->plugin_slug,
                'new_version' => $remote_version,
                'url' => "https://github.com/{$this->github_username}/{$this->github_repo}",
                'package' => $this->get_download_url($remote_version)
            );
            
            IndexFixer_Logger::log("🔄 Dostępna nowa wersja IndexFixer: $remote_version (aktualna: {$this->version})", 'info');
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
        delete_site_transient('update_plugins');
        wp_update_plugins();
        
        $remote_version = $this->get_remote_version();
        
        if (version_compare($this->version, $remote_version, '<')) {
            return array(
                'update_available' => true,
                'current_version' => $this->version,
                'latest_version' => $remote_version,
                'download_url' => $this->get_download_url($remote_version)
            );
        }
        
        return array(
            'update_available' => false,
            'current_version' => $this->version,
            'latest_version' => $remote_version
        );
    }
} 