jQuery(document).ready(function($) {
    // Filtrowanie po statusie
    $('#status-filter').on('change', function() {
        filterTable();
    });
    
    // Filtrowanie po robots.txt
    $('#robots-filter').on('change', function() {
        filterTable();
    });
    
    // Funkcja filtrowania tabeli
    function filterTable() {
        var statusFilter = $('#status-filter').val();
        var robotsFilter = $('#robots-filter').val();
        
        $('.wp-list-table tbody tr').each(function() {
            var $row = $(this);
            var showRow = true;
            
            // Filtr statusu (sprawdzaj verdict i coverage state)
            if (statusFilter) {
                var verdict = $row.find('td:nth-child(2)').text().trim();
                var coverageState = $row.find('td:nth-child(3)').text().trim();
                
                if (verdict !== statusFilter && coverageState !== statusFilter) {
                    showRow = false;
                }
            }
            
            // Filtr robots.txt
            if (robotsFilter && showRow) {
                var robotsTxt = $row.find('td:nth-child(4)').text().trim();
                if (robotsTxt !== robotsFilter) {
                    showRow = false;
                }
            }
            
            if (showRow) {
                $row.show();
            } else {
                $row.hide();
            }
        });
    }
    
    // Sortowanie tabeli
    $('.wp-list-table th').css('cursor', 'pointer').on('click', function() {
        var $table = $('.wp-list-table');
        var $th = $(this);
        var column = $th.index();
        var $tbody = $table.find('tbody');
        var rows = $tbody.find('tr').toArray();
        
        // Określ kierunek sortowania
        var isAscending = !$th.hasClass('sorted-asc');
        
        // Usuń klasy sortowania z wszystkich nagłówków
        $('.wp-list-table th').removeClass('sorted-asc sorted-desc');
        
        // Dodaj klasę do aktualnego nagłówka
        $th.addClass(isAscending ? 'sorted-asc' : 'sorted-desc');
        
        // Sortuj wiersze
        rows.sort(function(a, b) {
            var aText = $(a).find('td').eq(column).text().trim();
            var bText = $(b).find('td').eq(column).text().trim();
            
            // Sprawdź czy to data
            if (column === 4 || column === 6) { // Ostatni crawl lub Data publikacji
                aText = new Date(aText);
                bText = new Date(bText);
            }
            
            if (aText < bText) return isAscending ? -1 : 1;
            if (aText > bText) return isAscending ? 1 : -1;
            return 0;
        });
        
        // Dodaj posortowane wiersze z powrotem
        $tbody.empty().append(rows);
    });
    
    // Sprawdzanie pojedynczego URL-a
    $('#check-single-url').on('click', function() {
        var $button = $(this);
        var $input = $('#single-url-input');
        var $loading = $('#single-url-loading');
        var $result = $('#single-url-result');
        var url = $input.val().trim();
        
        if (!url) {
            alert('Podaj URL do sprawdzenia');
            return;
        }
        
        // Walidacja URL
        try {
            new URL(url);
        } catch (e) {
            alert('Podaj prawidłowy URL (z http:// lub https://)');
            return;
        }
        
        $button.prop('disabled', true);
        $loading.show();
        $result.hide();
        
        $.ajax({
            url: indexfixer.ajax_url,
            type: 'POST',
            data: {
                action: 'indexfixer_check_single_url',
                nonce: indexfixer.nonce,
                url: url
            },
            success: function(response) {
                if (response.success) {
                    var status = response.data.status;
                    var html = '<div class="single-url-details">';
                    html += '<h3>Wyniki dla: <a href="' + response.data.url + '" target="_blank">' + response.data.url + '</a></h3>';
                    
                    // Verdict
                    if (status.verdict && status.verdict !== 'unknown') {
                        var verdictClass = 'verdict-' + status.verdict.toLowerCase();
                        html += '<div class="verdict-result"><strong>Verdict:</strong> <span class="' + verdictClass + '">' + status.verdict + '</span></div>';
                    }
                    
                    // Coverage State
                    if (status.coverageState && status.coverageState !== 'unknown') {
                        html += '<div class="coverage-result"><strong>Coverage State:</strong> ' + status.coverageState + '</div>';
                    }
                    
                    // Technical details
                    html += '<div class="technical-details">';
                    if (status.robotsTxtState && status.robotsTxtState !== 'unknown') {
                        var robotsClass = status.robotsTxtState === 'ALLOWED' ? 'good' : 'bad';
                        html += '<div><span class="' + robotsClass + '">robots.txt: ' + status.robotsTxtState + '</span></div>';
                    }
                    if (status.indexingState && status.indexingState !== 'unknown') {
                        var indexingClass = status.indexingState === 'INDEXING_ALLOWED' ? 'good' : 'bad';
                        html += '<div><span class="' + indexingClass + '">indexingState: ' + status.indexingState + '</span></div>';
                    }
                    if (status.pageFetchState && status.pageFetchState !== 'unknown') {
                        var fetchClass = status.pageFetchState === 'SUCCESSFUL' ? 'good' : 'bad';
                        html += '<div><span class="' + fetchClass + '">pageFetchState: ' + status.pageFetchState + '</span></div>';
                    }
                    if (status.crawledAs && status.crawledAs !== 'unknown') {
                        html += '<div>Crawled as: ' + status.crawledAs + '</div>';
                    }
                    html += '</div>';
                    
                    // Last crawl time
                    if (status.lastCrawlTime && status.lastCrawlTime !== 'unknown') {
                        var crawlDate = new Date(status.lastCrawlTime).toLocaleDateString();
                        html += '<div class="crawl-time"><strong>📆 Data ostatniego crawl\'a:</strong> ' + crawlDate + '</div>';
                    }
                    
                    // Referring URLs
                    if (status.referringUrls && status.referringUrls.length > 0) {
                        html += '<div class="referring-urls"><strong>🔗 Linki wewnętrzne:</strong><ul>';
                        for (var i = 0; i < Math.min(status.referringUrls.length, 5); i++) {
                            html += '<li>' + status.referringUrls[i] + '</li>';
                        }
                        if (status.referringUrls.length > 5) {
                            html += '<li>... i ' + (status.referringUrls.length - 5) + ' więcej</li>';
                        }
                        html += '</ul></div>';
                    }
                    
                    html += '</div>';
                    $result.html(html).show();
                    
                    // Odśwież logi
                    if (response.data.logs) {
                        $('#indexfixer-logs-content').html(response.data.logs);
                    }
                } else {
                    alert('Błąd: ' + response.data);
                }
            },
            error: function() {
                alert('Wystąpił błąd podczas sprawdzania URL');
            },
            complete: function() {
                $button.prop('disabled', false);
                $loading.hide();
            }
        });
    });
    
    // Odświeżanie danych
    $('#refresh-data').on('click', function() {
        var $button = $(this);
        var originalText = $button.text();
        
        $button.prop('disabled', true).text('Odświeżanie...');
        
        $.ajax({
            url: indexfixer.ajax_url,
            type: 'POST',
            data: {
                action: 'indexfixer_refresh_data',
                nonce: indexfixer.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Odśwież logi
                    $('#indexfixer-logs-content').html(response.data.logs);
                    // Odśwież stronę po 2 sekundach
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                } else {
                    alert('Wystąpił błąd podczas odświeżania danych.');
                }
            },
            error: function() {
                alert('Wystąpił błąd podczas odświeżania danych.');
            },
            complete: function() {
                $button.prop('disabled', false).text(originalText);
            }
        });
    });
    
    // Eksport do CSV
    $('#export-csv').on('click', function() {
        var $button = $(this);
        var originalText = $button.text();
        
        $button.prop('disabled', true).text('Eksportowanie...');
        
        $.ajax({
            url: indexfixer.ajax_url,
            type: 'POST',
            data: {
                action: 'indexfixer_export_csv',
                nonce: indexfixer.nonce
            },
            success: function(response) {
                if (response.success) {
                    var blob = new Blob([response.data.content], { type: 'text/csv' });
                    var url = window.URL.createObjectURL(blob);
                    var a = document.createElement('a');
                    a.href = url;
                    a.download = response.data.filename;
                    document.body.appendChild(a);
                    a.click();
                    window.URL.revokeObjectURL(url);
                    document.body.removeChild(a);
                } else {
                    alert('Wystąpił błąd podczas eksportowania danych.');
                }
            },
            error: function() {
                alert('Wystąpił błąd podczas eksportowania danych.');
            },
            complete: function() {
                $button.prop('disabled', false).text(originalText);
            }
        });
    });
}); 