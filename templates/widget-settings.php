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
            
            <hr style="margin: 30px 0;">
            
            <h3>🔥 Wymuś Pełne Odświeżenie</h3>
            <p><strong>Wymusza sprawdzenie WSZYSTKICH URL-ów</strong> (ignoruje cache):</p>
            <div style="background: #fff3cd; padding: 10px; border-left: 3px solid #ffc107; margin: 10px 0; font-size: 14px;">
                <strong>⚠️ Jak to działa:</strong><br>
                • Wyczyści CAŁY cache (24h) wszystkich URL-ów<br>
                • Sprawdzi ponownie WSZYSTKIE URL-e przez Google API<br>
                • Może potrwać 20+ minut dla 458 URL-ów<br>
                • Użyj gdy chcesz "odświeżyć bazę ręcznie"
            </div>
            <button type="button" onclick="forceFullRefresh()" class="button" style="background: #ff6b35; border-color: #ff6b35; color: white; font-weight: bold;">
                🔥 WYMUŚ PEŁNE ODŚWIEŻENIE (wyczyść cache + sprawdź wszystkie)
            </button>
            <div id="force-refresh-result" style="margin-top: 10px;"></div>
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
        
        <div style="margin-top: 20px;">
            <h3>📊 Zarządzanie Statystykami</h3>
            <p>Zapisuj dzienne snapshots statystyk do śledzenia postępu indeksowania w czasie:</p>
            <div style="background: #e7f3ff; padding: 10px; border-left: 3px solid #0073aa; margin: 10px 0; font-size: 14px;">
                <strong>💡 Jak to działa:</strong><br>
                • Zapisuje obecne statystyki jako snapshot dnia dzisiejszego<br>
                • Pozwala porównywać postęp dzień do dnia<br>
                • Automatycznie odbywa się po każdym sprawdzaniu cron
            </div>
            <button type="button" onclick="saveDailyStats()" class="button button-secondary">
                💾 Zapisz Dzisiejsze Statystyki
            </button>
            <div id="stats-result" style="margin-top: 10px;"></div>
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
    
    <!-- NOWA SEKCJA: Zarządzanie automatycznym sprawdzaniem -->
    <div style="background: white; padding: 20px; border: 1px solid #ddd; border-radius: 5px; margin: 20px 0;">
        <h2>🤖 Automatyczne sprawdzanie widgetów</h2>
        
        <div id="scheduler-status" style="background: #f0f0f1; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
            <p><strong>Status:</strong> <span id="schedule-info">Ładowanie...</span></p>
            <p><strong>Tryb:</strong> <span id="test-mode-info">Sprawdzam...</span></p>
        </div>
        
        <div style="margin-bottom: 20px;">
            <h3>🧪 Tryb testowy</h3>
            <p>W trybie testowym URL-e są sprawdzane co 10 minut zamiast co 24 godziny. Idealny do testowania czy mechanizm działa.</p>
            
            <button type="button" id="enable-test-mode" class="button button-secondary">
                Włącz tryb testowy (10 min)
            </button>
            
            <button type="button" id="disable-test-mode" class="button button-secondary">
                Wyłącz tryb testowy (24h)
            </button>
        </div>
        
        <div style="margin-bottom: 20px;">
            <h3>⚡ Ręczne sprawdzanie</h3>
            <p>Uruchom sprawdzanie widgetów natychmiast (niezależnie od harmonogramu).</p>
            
            <button type="button" id="run-manual-check" class="button button-primary">
                Uruchom sprawdzanie teraz
            </button>
        </div>
        
        <div style="margin-bottom: 20px;">
            <h3>🔑 Testowe odświeżanie tokenu</h3>
            <p>Ręcznie odśwież token Google Search Console (do testowania mechanizmu autoryzacji).</p>
            
            <button type="button" id="test-refresh-token" class="button button-secondary" style="background: #ff6b35; border-color: #ff6b35; color: white;">
                🧪 Odśwież token Google
            </button>
            <div id="token-refresh-result" style="margin-top: 10px;"></div>
        </div>
        
        <div style="margin-bottom: 20px;">
            <h3>🔄 Test systemu aktualizacji</h3>
            <p>Sprawdź czy system automatycznych aktualizacji przez GitHub działa poprawnie.</p>
            
            <button type="button" id="test-updater" class="button button-secondary" style="background: #0073aa; border-color: #0073aa; color: white;">
                🔄 Test aktualizacji
            </button>
            <div id="updater-test-result" style="margin-top: 10px;"></div>
        </div>
        
        <div style="margin-bottom: 20px;">
            <h3>⏰ Planowanie crona odnawiania tokenów</h3>
            <p>Jeśli nie widzisz crona odnawiania tokenów w pluginie do cronów, zaplanuj go ręcznie.</p>
            
            <button type="button" id="schedule-token-cron" class="button button-secondary" style="background: #46b450; border-color: #46b450; color: white;">
                ⏰ Zaplanuj cron tokenów
            </button>
            <div id="schedule-cron-result" style="margin-top: 10px;"></div>
        </div>
        
        <div style="margin-bottom: 20px;">
            <h3>🔧 Naprawa crona widgetów</h3>
            <p>Jeśli cron widgetów pracuje z nieprawidłowym interwałem (np. 10 min zamiast 24h), wymuś przebudowę.</p>
            
            <button type="button" id="force-rebuild-schedule" class="button button-secondary" style="background: #dc3545; border-color: #dc3545; color: white;">
                🔧 Przebuduj harmonogram widgetów
            </button>
            <div id="rebuild-schedule-result" style="margin-top: 10px;"></div>
        </div>
        
        <div style="margin-bottom: 20px;">
            <button type="button" id="refresh-schedule-status" class="button">
                🔄 Odśwież status
            </button>
        </div>
        
        <div style="background: #fff3cd; padding: 15px; border-left: 3px solid #ffc107; margin: 20px 0;">
            <h3 style="margin: 0 0 10px 0;">📋 Jak to działa:</h3>
            <ul style="margin: 0;">
                <li><strong>Automatyczne sprawdzanie:</strong> System sprawdza niezaindeksowane URL-e z widgetów</li>
                <li><strong>Inteligentne logowanie:</strong> Wszystkie działania są zapisywane w logach</li>
                <li><strong>Tryb testowy:</strong> Pozwala szybko przetestować czy mechanizm działa (10 min zamiast 24h)</li>
                <li><strong>Automatyczne wyłączanie:</strong> Jeśli nie ma aktywnych widgetów, sprawdzanie się wyłącza</li>
            </ul>
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

function forceFullRefresh() {
    if (!confirm('⚠️ UWAGA: To wyczyści cały cache i sprawdzi WSZYSTKIE URL-e przez API Google.\n\nMoże potrwać 20+ minut.\n\nCzy na pewno chcesz kontynuować?')) {
        return;
    }
    
    const resultDiv = document.getElementById('force-refresh-result');
    resultDiv.innerHTML = '<div style="color: #ff6b35; font-weight: bold;">🔥 Czyszczę cache i wymuszam pełne odświeżenie...</div>';
    
    fetch(ajaxurl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
            action: 'indexfixer_force_full_refresh',
            nonce: '<?php echo wp_create_nonce('indexfixer_force_refresh'); ?>'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            resultDiv.innerHTML = '<div style="color: #00a32a;">✅ ' + data.data.message + '</div>';
            
            // Pokaż szczegóły postępu
            if (data.data.details) {
                setTimeout(() => {
                    resultDiv.innerHTML += '<br><div style="color: #0073aa; font-size: 12px;">' + data.data.details + '</div>';
                }, 1000);
            }
            
            // Informacja o logach
            setTimeout(() => {
                resultDiv.innerHTML += '<br><div style="color: #666; font-size: 12px;">📊 Sprawdź logi aby śledzić postęp sprawdzania wszystkich URL-ów</div>';
            }, 2000);
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

function saveDailyStats() {
    const resultDiv = document.getElementById('stats-result');
    resultDiv.innerHTML = '<div style="color: #0073aa;">⏳ Zapisywanie dzisiejszych statystyk...</div>';
    
    fetch(ajaxurl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
            action: 'indexfixer_save_daily_stats',
            nonce: '<?php echo wp_create_nonce('indexfixer_save_stats'); ?>'
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

jQuery(document).ready(function($) {
    // Funkcja odświeżania statusu
    function refreshScheduleStatus() {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'indexfixer_get_schedule_status',
                nonce: '<?php echo wp_create_nonce('indexfixer_schedule_status'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    $('#schedule-info').text(response.data.message);
                    $('#test-mode-info').text(response.data.status.test_mode ? 'TESTOWY (10 min)' : 'PRODUKCYJNY (24h)');
                } else {
                    $('#schedule-info').text('Błąd: ' + (response.data || 'Nieznany błąd'));
                }
            },
            error: function() {
                $('#schedule-info').text('Błąd połączenia');
            }
        });
    }
    
    // Włącz tryb testowy
    $('#enable-test-mode').on('click', function() {
        var $button = $(this);
        var originalText = $button.text();
        
        $button.prop('disabled', true).text('Włączam...');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'indexfixer_enable_test_mode',
                nonce: '<?php echo wp_create_nonce('indexfixer_test_mode'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    alert('✅ ' + response.data.message);
                    refreshScheduleStatus();
                } else {
                    alert('❌ Błąd: ' + (response.data || 'Nieznany błąd'));
                }
                $button.prop('disabled', false).text(originalText);
            },
            error: function() {
                alert('❌ Błąd połączenia');
                $button.prop('disabled', false).text(originalText);
            }
        });
    });
    
    // Wyłącz tryb testowy
    $('#disable-test-mode').on('click', function() {
        var $button = $(this);
        var originalText = $button.text();
        
        $button.prop('disabled', true).text('Wyłączam...');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'indexfixer_disable_test_mode',
                nonce: '<?php echo wp_create_nonce('indexfixer_test_mode'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    alert('✅ ' + response.data.message);
                    refreshScheduleStatus();
                } else {
                    alert('❌ Błąd: ' + (response.data || 'Nieznany błąd'));
                }
                $button.prop('disabled', false).text(originalText);
            },
            error: function() {
                alert('❌ Błąd połączenia');
                $button.prop('disabled', false).text(originalText);
            }
        });
    });
    
    // Ręczne sprawdzanie
    $('#run-manual-check').on('click', function() {
        var $button = $(this);
        var originalText = $button.text();
        
        $button.prop('disabled', true).text('Sprawdzam...');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'indexfixer_run_manual_check',
                nonce: '<?php echo wp_create_nonce('indexfixer_manual_check'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    alert('✅ ' + response.data.message);
                    refreshScheduleStatus();
                } else {
                    alert('❌ Błąd: ' + (response.data || 'Nieznany błąd'));
                }
                $button.prop('disabled', false).text(originalText);
            },
            error: function() {
                alert('❌ Błąd połączenia');
                $button.prop('disabled', false).text(originalText);
            }
        });
    });
    
    // Testowe odświeżanie tokenu
    $('#test-refresh-token').on('click', function() {
        var $button = $(this);
        var $resultDiv = $('#token-refresh-result');
        var originalText = $button.text();
        
        $button.prop('disabled', true).text('Odświeżam token...');
        $resultDiv.html('<div style="color: #0073aa;">⏳ Próbuję odświeżyć token Google...</div>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'indexfixer_test_refresh_token',
                nonce: '<?php echo wp_create_nonce('indexfixer_nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    $resultDiv.html('<div style="color: #00a32a;">✅ ' + response.data.message + '</div>' +
                        '<div style="color: #666; font-size: 12px; margin-top: 5px;">' +
                        'Stary token wygasał: ' + response.data.old_expiry + '<br>' +
                        'Nowy token wygasa: ' + response.data.new_expiry + '<br>' +
                        'Za minut: ' + response.data.expires_in_minutes +
                        '</div>');
                } else {
                    $resultDiv.html('<div style="color: #d63638;">❌ ' + (response.data || 'Nieznany błąd') + '</div>');
                }
                $button.prop('disabled', false).text(originalText);
            },
            error: function() {
                $resultDiv.html('<div style="color: #d63638;">❌ Błąd połączenia</div>');
                $button.prop('disabled', false).text(originalText);
            }
        });
    });
    
    // Odśwież status
    $('#refresh-schedule-status').on('click', function() {
        refreshScheduleStatus();
    });
    
    // Przebudowa harmonogramu widgetów
    $('#force-rebuild-schedule').on('click', function() {
        var $button = $(this);
        var $resultDiv = $('#rebuild-schedule-result');
        var originalText = $button.text();
        
        $button.prop('disabled', true).text('Przebudowuję...');
        $resultDiv.html('<div style="color: #0073aa;">⏳ Przebudowuję harmonogram widgetów...</div>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'indexfixer_force_rebuild_widget_schedule',
                nonce: '<?php echo wp_create_nonce('indexfixer_nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    var message = '<div style="color: #00a32a;">✅ ' + response.data.message + '</div>';
                    if (response.data.test_mode) {
                        message += '<div style="color: #ff6b35; margin-top: 5px;">⚠️ UWAGA: Tryb testowy jest włączony w bazie danych!</div>';
                    }
                    $resultDiv.html(message);
                    refreshScheduleStatus(); // Odśwież status po przebudowie
                } else {
                    $resultDiv.html('<div style="color: #d63638;">❌ ' + (response.data || 'Nieznany błąd') + '</div>');
                }
                $button.prop('disabled', false).text(originalText);
            },
            error: function() {
                $resultDiv.html('<div style="color: #d63638;">❌ Błąd połączenia</div>');
                $button.prop('disabled', false).text(originalText);
            }
        });
    });

    // Załaduj status przy starcie
    refreshScheduleStatus();
});
</script> 