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
    
    // Sprawdzanie pojedynczego URL-a z tabeli
    $(document).on('click', '.check-single-url', function() {
        var $button = $(this);
        var url = $button.data('url');
        var $row = $button.closest('tr');
        var originalIcon = $button.text();
        
        $button.prop('disabled', true).text('⏳');
        
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
                    
                    // Aktualizuj wiersz w tabeli
                    if (status.verdict && status.verdict !== 'unknown') {
                        var verdictHtml = '<span class="verdict-' + status.verdict.toLowerCase() + '">' + status.verdict + '</span>';
                        if (status.indexingState && status.indexingState !== 'unknown') {
                            var indexingClass = status.indexingState === 'INDEXING_ALLOWED' ? 'good' : 'bad';
                            verdictHtml += '<br><small class="' + indexingClass + '">' + status.indexingState + '</small>';
                        }
                        $row.find('td:nth-child(2)').html(verdictHtml);
                    }
                    
                    if (status.coverageState && status.coverageState !== 'unknown') {
                        $row.find('td:nth-child(3)').html('<span class="coverage-state">' + status.coverageState + '</span>');
                    }
                    
                    if (status.robotsTxtState && status.robotsTxtState !== 'unknown') {
                        var robotsClass = status.robotsTxtState === 'ALLOWED' ? 'good' : 'bad';
                        $row.find('td:nth-child(4)').html('<span class="' + robotsClass + '">' + status.robotsTxtState + '</span>');
                    }
                    
                    if (status.lastCrawlTime && status.lastCrawlTime !== 'unknown') {
                        var crawlDate = new Date(status.lastCrawlTime).toLocaleDateString();
                        $row.find('td:nth-child(5)').text(crawlDate);
                    }
                    
                    // Odśwież logi
                    if (response.data.logs) {
                        $('#indexfixer-logs-content').html(response.data.logs);
                    }
                    
                    // Pokaż komunikat sukcesu
                    $button.text('✅').prop('title', 'URL sprawdzony pomyślnie');
                    setTimeout(function() {
                        $button.text(originalIcon).prop('title', 'Sprawdź status tego URL');
                    }, 2000);
                } else {
                    alert('Błąd: ' + response.data);
                    $button.text('❌').prop('title', 'Błąd podczas sprawdzania');
                    setTimeout(function() {
                        $button.text(originalIcon).prop('title', 'Sprawdź status tego URL');
                    }, 2000);
                }
            },
            error: function() {
                alert('Wystąpił błąd podczas sprawdzania URL');
                $button.text('❌').prop('title', 'Błąd podczas sprawdzania');
                setTimeout(function() {
                    $button.text(originalIcon).prop('title', 'Sprawdź status tego URL');
                }, 2000);
            },
            complete: function() {
                $button.prop('disabled', false);
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
    
    // Inicjalizacja wykresów
    console.log('Chart.js dostępny:', typeof Chart !== 'undefined');
    console.log('indexfixer_stats dostępny:', typeof indexfixer_stats !== 'undefined');
    
    if (typeof Chart !== 'undefined' && typeof indexfixer_stats !== 'undefined') {
        initCharts();
    } else {
        console.error('Problem z ładowaniem:', {
            'Chart.js': typeof Chart,
            'indexfixer_stats': typeof indexfixer_stats
        });
        
        // Fallback - pokaż komunikat
        const chartContainers = document.querySelectorAll('.chart-wrapper canvas');
        chartContainers.forEach(canvas => {
            const wrapper = canvas.parentElement;
            wrapper.innerHTML = '<p>⚠️ Nie można załadować wykresów</p>';
        });
    }
    
    function initCharts() {
        const stats = indexfixer_stats;
        console.log('IndexFixer Stats:', stats);
        
        // Wykres statusu indeksowania
        const indexingCtx = document.getElementById('indexing-chart');
        if (indexingCtx) {
            new Chart(indexingCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Zaindeksowane', 'Nie zaindeksowane', 'Odkryte', 'Wykluczone', 'Nieznane'],
                    datasets: [{
                        data: [
                            stats.indexed || 0,
                            stats.not_indexed || 0,
                            stats.discovered || 0,
                            stats.excluded || 0,
                            stats.unknown || 0
                        ],
                        backgroundColor: [
                            '#46b450',  // zielony - zaindeksowane
                            '#dc3232',  // czerwony - nie zaindeksowane
                            '#ffb900',  // żółty - odkryte
                            '#666666',  // szary - wykluczone
                            '#cccccc'   // jasny szary - nieznane
                        ],
                        borderWidth: 2,
                        borderColor: '#ffffff'
                    }]
                },
                options: {
                    responsive: false,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 20,
                                usePointStyle: true
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.parsed || 0;
                                    const total = stats.total || 0;
                                    const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                                    return `${label}: ${value} (${percentage}%)`;
                                }
                            }
                        }
                    }
                }
            });
        }
        
        // Wykres verdict
        const verdictCtx = document.getElementById('verdict-chart');
        if (verdictCtx) {
            new Chart(verdictCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Pass', 'Neutral', 'Fail', 'Niesprawdzone'],
                    datasets: [{
                        data: [
                            stats.pass || 0,
                            stats.neutral || 0,
                            stats.fail || 0,
                            (stats.total - stats.checked) || 0
                        ],
                        backgroundColor: [
                            '#46b450',  // zielony - pass
                            '#0073aa',  // niebieski - neutral
                            '#dc3232',  // czerwony - fail
                            '#cccccc'   // szary - niesprawdzone
                        ],
                        borderWidth: 2,
                        borderColor: '#ffffff'
                    }]
                },
                options: {
                    responsive: false,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 20,
                                usePointStyle: true
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.parsed || 0;
                                    const total = stats.total || 0;
                                    const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                                    return `${label}: ${value} (${percentage}%)`;
                                }
                            }
                        }
                    }
                }
            });
        }
    }
}); 