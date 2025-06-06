<?php
/**
 * Strona zarządzania IndexFixer
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1>📊 IndexFixer - Zarządzanie</h1>
    
    <div style="background: white; padding: 20px; border: 1px solid #ddd; border-radius: 5px; margin: 20px 0;">
        <h2>🎯 Widget WordPress</h2>
        
        <p>Użyj tego widgetu aby wyświetlić niezaindeksowane posty na Twojej stronie:</p>
        
        <ol>
            <li><strong>Przejdź do:</strong> <a href="<?php echo admin_url('widgets.php'); ?>">Wygląd → Widgety</a></li>
            <li><strong>Znajdź widget:</strong> "IndexFixer - Niezaindeksowane posty"</li>
            <li><strong>Przeciągnij</strong> do wybranego obszaru widgetów (np. boczny panel)</li>
            <li><strong>Skonfiguruj ustawienia:</strong>
                <ul>
                    <li>Ustaw tytuł widget (np. "Posty do zalinkowania")</li>
                    <li>Wybierz liczbę postów do wyświetlania (5-10 najlepsze)</li>
                    <li>Włącz automatyczne sprawdzanie co 24h</li>
                </ul>
            </li>
        </ol>
        
        <div style="background: #fff3cd; padding: 15px; border-left: 3px solid #ffc107; margin: 20px 0;">
            <h3 style="margin: 0 0 10px 0;">💡 Jak to działa:</h3>
            <ul style="margin: 0;">
                <li><strong>Linkowanie wewnętrzne:</strong> Widget pokazuje niezaindeksowane posty na stronie</li>
                <li><strong>Automatyczne czyszczenie:</strong> Gdy Google zaindeksuje post, automatycznie zniknie z listy</li>
                <li><strong>Codzienne sprawdzanie:</strong> URL-e są sprawdzane automatycznie co 24h</li>
                <li><strong>Inteligentne odświeżanie:</strong> Nowe posty są automatycznie dodawane do sprawdzania</li>
            </ul>
        </div>
        
        <p>
            <a href="<?php echo admin_url('widgets.php'); ?>" class="button button-primary">
                📊 Przejdź do konfiguracji widgetów
            </a>
        </p>
    </div>
    
    <div style="background: white; padding: 20px; border: 1px solid #ddd; border-radius: 5px; margin: 20px 0;">
        <h2>🔧 Narzędzia Bazy Danych</h2>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            <div>
                <h3>🔄 Migracja Danych</h3>
                <p>Przenieś dane z wp_options do nowej tabeli:</p>
                <button type="button" onclick="migrateData()" class="button button-secondary">
                    Uruchom migrację
                </button>
                <div id="migration-result" style="margin-top: 10px;"></div>
            </div>
            
            <div>
                <h3>🧹 Czyszczenie Cache</h3>
                <p>Wyczyść stary cache wp_options:</p>
                <button type="button" onclick="clearCache()" class="button button-secondary">
                    Wyczyść cache
                </button>
                <div id="cache-result" style="margin-top: 10px;"></div>
            </div>
        </div>
        
        <div style="margin-top: 20px;">
            <h3>🔄 Wznów Sprawdzanie</h3>
            <p>Sprawdza tylko URL-e które nie mają jeszcze danych z Google Search Console:</p>
            <div style="background: #e7f3ff; padding: 10px; border-left: 3px solid #0073aa; margin: 10px 0; font-size: 14px;">
                <strong>💡 Jak to działa:</strong><br>
                • Znajduje URL-e bez danych API (status "unknown")<br>
                • Sprawdza tylko te które nie były jeszcze sprawdzone<br>
                • Nie zaczyna od nowa całego procesu
            </div>
            <button type="button" onclick="resumeChecking()" class="button button-primary" style="background: #28a745; border-color: #28a745;">
                🔄 Wznów Sprawdzanie URL-ów
            </button>
            <div id="resume-result" style="margin-top: 10px;"></div>
        </div>

        <div style="margin-top: 20px;">
            <h3>🔍 Debug wp_options</h3>
            <p>Sprawdź co jest w wp_options:</p>
            <button type="button" onclick="debugCache()" class="button button-secondary">
                Sprawdź wp_options
            </button>
            <div id="debug-result" style="margin-top: 10px; font-family: monospace; font-size: 12px;"></div>
        </div>
        
        <div style="margin-top: 20px;">
            <h3>🗄️ Debug Tabeli Bazy Danych</h3>
            <p>Sprawdź co jest w tabeli indexfixer_urls:</p>
            <button type="button" onclick="debugDatabase()" class="button button-secondary">
                Sprawdź tabelę
            </button>
            <div id="database-result" style="margin-top: 10px; font-family: monospace; font-size: 12px;"></div>
        </div>
        
        <div style="margin-top: 20px;">
            <h3>🔓 Odblokuj Proces Sprawdzania</h3>
            <p>Użyj gdy widzisz błąd "PROCES JEST JUŻ URUCHOMIONY - pomijam":</p>
            <button type="button" onclick="unlockProcess()" class="button button-secondary">
                Odblokuj proces
            </button>
            <div id="unlock-result" style="margin-top: 10px;"></div>
        </div>
    </div>
    
    <div style="background: white; padding: 20px; border: 1px solid #ddd; border-radius: 5px; margin: 20px 0;">
        <h2>ℹ️ Informacje</h2>
        
        <p><strong>Wersja wtyczki:</strong> <?php echo INDEXFIXER_VERSION; ?></p>
        <p><strong>Limit URL-ów:</strong> <?php echo INDEXFIXER_URL_LIMIT; ?></p>
        
        <div style="background: #e7f3ff; padding: 15px; border-left: 3px solid #0073aa; margin: 20px 0;">
            <h4 style="margin: 0 0 10px 0;">📝 Następne kroki:</h4>
            <ol style="margin: 0;">
                <li>Skonfiguruj widget w panelu widgetów WordPress</li>
                <li>Uruchom migrację danych do nowej tabeli bazy</li>
                <li>Widget automatycznie pokaże niezaindeksowane posty</li>
                <li>Sprawdzanie odbywa się automatycznie co 24h</li>
            </ol>
        </div>
    </div>
</div>

<script>
function migrateData() {
    const resultDiv = document.getElementById('migration-result');
    resultDiv.innerHTML = '<div style="color: #0073aa;">⏳ Trwa migracja danych...</div>';
    
    fetch(ajaxurl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
            action: 'indexfixer_migrate_data',
            nonce: '<?php echo wp_create_nonce('indexfixer_migrate'); ?>'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            resultDiv.innerHTML = '<div style="color: #00a32a;">✅ ' + data.data.message + '</div>';
            setTimeout(() => location.reload(), 2000);
        } else {
            resultDiv.innerHTML = '<div style="color: #d63638;">❌ ' + data.data + '</div>';
        }
    })
    .catch(error => {
        resultDiv.innerHTML = '<div style="color: #d63638;">❌ Błąd: ' + error.message + '</div>';
    });
}

function clearCache() {
    const resultDiv = document.getElementById('cache-result');
    resultDiv.innerHTML = '<div style="color: #0073aa;">⏳ Czyszczenie cache...</div>';
    
    fetch(ajaxurl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
            action: 'indexfixer_clear_cache',
            nonce: '<?php echo wp_create_nonce('indexfixer_clear'); ?>'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            resultDiv.innerHTML = '<div style="color: #00a32a;">✅ ' + data.data.message + '</div>';
        } else {
            resultDiv.innerHTML = '<div style="color: #d63638;">❌ ' + data.data + '</div>';
        }
    })
    .catch(error => {
        resultDiv.innerHTML = '<div style="color: #d63638;">❌ Błąd: ' + error.message + '</div>';
    });
}

function debugCache() {
    const resultDiv = document.getElementById('debug-result');
    resultDiv.innerHTML = '<div style="color: #0073aa;">⏳ Sprawdzanie wp_options...</div>';
    
    fetch(ajaxurl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
            action: 'indexfixer_debug_cache',
            nonce: '<?php echo wp_create_nonce('indexfixer_debug'); ?>'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            let html = '<div style="color: #00a32a;">✅ Znalezione opcje w wp_options:</div><br>';
            html += '<table style="border-collapse: collapse; width: 100%;">';
            html += '<tr style="background: #f1f1f1;"><th style="border: 1px solid #ddd; padding: 8px;">Opcja</th><th style="border: 1px solid #ddd; padding: 8px;">Typ</th><th style="border: 1px solid #ddd; padding: 8px;">Rozmiar</th><th style="border: 1px solid #ddd; padding: 8px;">Liczba</th><th style="border: 1px solid #ddd; padding: 8px;">Przykład</th></tr>';
            
            Object.keys(data.data).forEach(key => {
                const info = data.data[key];
                html += `<tr>
                    <td style="border: 1px solid #ddd; padding: 8px; font-weight: bold;">${key}</td>
                    <td style="border: 1px solid #ddd; padding: 8px;">${info.type}</td>
                    <td style="border: 1px solid #ddd; padding: 8px;">${info.length} znaków</td>
                    <td style="border: 1px solid #ddd; padding: 8px;">${info.count}</td>
                    <td style="border: 1px solid #ddd; padding: 8px; max-width: 200px; overflow: hidden;">${JSON.stringify(info.sample).substring(0, 100)}...</td>
                </tr>`;
            });
            
            html += '</table>';
            resultDiv.innerHTML = html;
        } else {
            resultDiv.innerHTML = '<div style="color: #d63638;">❌ ' + data.data + '</div>';
        }
    })
    .catch(error => {
        resultDiv.innerHTML = '<div style="color: #d63638;">❌ Błąd: ' + error.message + '</div>';
    });
}

function debugDatabase() {
    const resultDiv = document.getElementById('database-result');
    resultDiv.innerHTML = '<div style="color: #0073aa;">⏳ Sprawdzanie tabeli bazy danych...</div>';
    
    fetch(ajaxurl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
            action: 'indexfixer_debug_database',
            nonce: '<?php echo wp_create_nonce('indexfixer_debug_db'); ?>'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            let html = '<div style="color: #00a32a;">✅ Stan tabeli indexfixer_urls:</div><br>';
            
            html += `<div><strong>Łączna liczba rekordów:</strong> ${data.data.total_records}</div><br>`;
            
            if (data.data.sample_records && data.data.sample_records.length > 0) {
                html += '<div><strong>Przykładowe rekordy (5 najnowszych):</strong></div>';
                html += '<table style="border-collapse: collapse; width: 100%; margin-top: 10px;">';
                html += '<tr style="background: #f1f1f1;"><th style="border: 1px solid #ddd; padding: 4px;">URL</th><th style="border: 1px solid #ddd; padding: 4px;">Status</th><th style="border: 1px solid #ddd; padding: 4px;">Verdict</th><th style="border: 1px solid #ddd; padding: 4px;">Coverage State</th><th style="border: 1px solid #ddd; padding: 4px;">Last Checked</th></tr>';
                
                data.data.sample_records.forEach(record => {
                    const shortUrl = record.url.length > 40 ? record.url.substring(0, 40) + '...' : record.url;
                    html += `<tr>
                        <td style="border: 1px solid #ddd; padding: 4px; font-size: 11px;" title="${record.url}">${shortUrl}</td>
                        <td style="border: 1px solid #ddd; padding: 4px;">${record.status || '-'}</td>
                        <td style="border: 1px solid #ddd; padding: 4px;">${record.verdict || '-'}</td>
                        <td style="border: 1px solid #ddd; padding: 4px; font-size: 11px;">${record.coverage_state || '-'}</td>
                        <td style="border: 1px solid #ddd; padding: 4px; font-size: 11px;">${record.last_checked || '-'}</td>
                    </tr>`;
                });
                
                html += '</table>';
            } else {
                html += '<div style="color: #d63638;">⚠️ Brak rekordów w tabeli</div>';
            }
            
            resultDiv.innerHTML = html;
        } else {
            resultDiv.innerHTML = '<div style="color: #d63638;">❌ ' + data.data + '</div>';
        }
    })
    .catch(error => {
        resultDiv.innerHTML = '<div style="color: #d63638;">❌ Błąd: ' + error.message + '</div>';
    });
}

function resumeChecking() {
    const resultDiv = document.getElementById('resume-result');
    resultDiv.innerHTML = '<div style="color: #0073aa;">⏳ Wznawiam sprawdzanie URL-ów bez danych...</div>';
    
    fetch(ajaxurl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
            action: 'indexfixer_resume_checking',
            nonce: '<?php echo wp_create_nonce('indexfixer_resume'); ?>'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            resultDiv.innerHTML = '<div style="color: #00a32a;">✅ ' + data.data.message + '</div>';
            
            // Pokaż szczegóły
            if (data.data.details) {
                setTimeout(() => {
                    resultDiv.innerHTML += '<br><div style="color: #0073aa; font-size: 12px;">' + data.data.details + '</div>';
                }, 1000);
            }
        } else {
            resultDiv.innerHTML = '<div style="color: #d63638;">❌ ' + data.data + '</div>';
        }
    })
    .catch(error => {
        resultDiv.innerHTML = '<div style="color: #d63638;">❌ Błąd: ' + error.message + '</div>';
    });
}

function unlockProcess() {
    const resultDiv = document.getElementById('unlock-result');
    resultDiv.innerHTML = '<div style="color: #0073aa;">⏳ Odblokowywanie procesu...</div>';
    
    fetch(ajaxurl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
            action: 'indexfixer_unlock_process',
            nonce: '<?php echo wp_create_nonce('indexfixer_unlock'); ?>'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            resultDiv.innerHTML = '<div style="color: #00a32a;">✅ ' + data.data.message + '</div>';
        } else {
            resultDiv.innerHTML = '<div style="color: #d63638;">❌ ' + data.data + '</div>';
        }
    })
    .catch(error => {
        resultDiv.innerHTML = '<div style="color: #d63638;">❌ Błąd: ' + error.message + '</div>';
    });
}
</script> 