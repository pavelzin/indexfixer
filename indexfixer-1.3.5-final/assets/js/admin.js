jQuery(document).ready(function($) {
    // Obsługa zakładek
    $('.nav-tab').on('click', function(e) {
        e.preventDefault();
        var tab = $(this).attr('href').split('tab=')[1];
        window.history.pushState({}, '', '?page=indexfixer&tab=' + tab);
        loadTabContent(tab);
    });
    
    // Funkcja ładowania zawartości zakładki
    function loadTabContent(tab) {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'indexfixer_load_tab',
                tab: tab,
                nonce: indexfixer.nonce
            },
            success: function(response) {
                if (response.success) {
                    $('.tab-content').html(response.data);
                    initializeTabHandlers(tab);
                } else {
                    alert('Wystąpił błąd podczas ładowania zawartości zakładki.');
                }
            }
        });
    }
    
    // Inicjalizacja obsługi zdarzeń dla zakładki
    function initializeTabHandlers(tab) {
        switch (tab) {
            case 'urls':
                initializeUrlsTab();
                break;
            case 'overview':
                initializeOverviewTab();
                break;
            case 'settings':
                initializeSettingsTab();
                break;
            case 'diagnostics':
                initializeDiagnosticsTab();
                break;
        }
    }
    
    // Obsługa zakładki "Lista URL-i"
    function initializeUrlsTab() {
        // Filtrowanie
        $('#url-status-filter, #robots-txt-filter, #post-type-filter').on('change', function() {
            filterUrls();
        });
        
        // Sortowanie
        $('.sortable').on('click', function() {
            var column = $(this).data('column');
            var direction = $(this).data('direction') === 'asc' ? 'desc' : 'asc';
            $(this).data('direction', direction);
            sortUrls(column, direction);
        });
        
        // Paginacja
        $('.pagination a').on('click', function(e) {
            e.preventDefault();
            var page = $(this).data('page');
            loadUrlsPage(page);
        });
        
        // Odświeżanie danych
        $('#refresh-data').on('click', function() {
            refreshUrlsData();
        });
        
        // Eksport do CSV
        $('#export-csv').on('click', function() {
            exportToCsv();
        });
    }
    
    // Obsługa zakładki "Przegląd"
    function initializeOverviewTab() {
        // Odświeżanie danych
        $('#refresh-overview').on('click', function() {
            refreshOverviewData();
        });
        
        // Automatyczne odświeżanie
        if (indexfixer.auto_refresh) {
            setInterval(refreshOverviewData, 300000); // co 5 minut
        }
    }
    
    // Obsługa zakładki "Ustawienia"
    function initializeSettingsTab() {
        // Zapisywanie ustawień
        $('#indexfixer-settings-form').on('submit', function(e) {
            e.preventDefault();
            saveSettings();
        });
    }
    
    // Obsługa zakładki "Diagnostyka"
    function initializeDiagnosticsTab() {
        // Czyszczenie logów
        $('#clear-logs').on('click', function(e) {
            e.preventDefault();
            if (confirm('Czy na pewno chcesz wyczyścić wszystkie logi?')) {
                clearLogs();
            }
        });
    }
    
    // Funkcje pomocnicze
    function filterUrls() {
        var status = $('#url-status-filter').val();
        var robots = $('#robots-txt-filter').val();
        var postType = $('#post-type-filter').val();
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'indexfixer_filter_urls',
                status: status,
                robots: robots,
                post_type: postType,
                nonce: indexfixer.nonce
            },
            success: function(response) {
                if (response.success) {
                    $('.urls-table tbody').html(response.data);
                }
            }
        });
    }
    
    function sortUrls(column, direction) {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'indexfixer_sort_urls',
                column: column,
                direction: direction,
                nonce: indexfixer.nonce
            },
            success: function(response) {
                if (response.success) {
                    $('.urls-table tbody').html(response.data);
                }
            }
        });
    }
    
    function loadUrlsPage(page) {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'indexfixer_load_urls_page',
                page: page,
                nonce: indexfixer.nonce
            },
            success: function(response) {
                if (response.success) {
                    $('.urls-table tbody').html(response.data.table);
                    $('.pagination').html(response.data.pagination);
                }
            }
        });
    }
    
    function refreshUrlsData() {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'indexfixer_refresh_urls',
                nonce: indexfixer.nonce
            },
            success: function(response) {
                if (response.success) {
                    $('.urls-table tbody').html(response.data);
                }
            }
        });
    }
    
    function exportToCsv() {
        window.location.href = ajaxurl + '?action=indexfixer_export_csv&nonce=' + indexfixer.nonce;
    }
    
    function refreshOverviewData() {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'indexfixer_refresh_overview',
                nonce: indexfixer.nonce
            },
            success: function(response) {
                if (response.success) {
                    $('.overview-content').html(response.data);
                }
            }
        });
    }
    
    function saveSettings() {
        var formData = $('#indexfixer-settings-form').serialize();
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'indexfixer_save_settings',
                form_data: formData,
                nonce: indexfixer.nonce
            },
            success: function(response) {
                if (response.success) {
                    alert('Ustawienia zostały zapisane.');
                } else {
                    alert('Wystąpił błąd podczas zapisywania ustawień.');
                }
            }
        });
    }
    
    function clearLogs() {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'indexfixer_clear_logs',
                nonce: indexfixer.nonce
            },
            success: function(response) {
                if (response.success) {
                    $('.logs-table tbody').empty();
                    alert('Logi zostały wyczyszczone.');
                }
            }
        });
    }
    
    // Inicjalizacja początkowej zakładki
    var initialTab = $('.nav-tab-active').attr('href').split('tab=')[1];
    initializeTabHandlers(initialTab);

    // Obsługa kliknięcia w zakładki
    $('.indexfixer-dashboard .nav-tab').on('click', function(e) {
        e.preventDefault();
        window.location.href = this.href;
    });
    
    // Inicjalizacja wykresów
    function initCharts() {
        if (typeof Chart === 'undefined') return;
        
        // Główny wykres trendu
        const trendChart = document.getElementById('trend-chart');
        if (trendChart) {
            const ctx = trendChart.getContext('2d');
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: indexfixer_stats.dates,
                    datasets: [
                        {
                            label: 'Zaindeksowane',
                            data: indexfixer_stats.indexed,
                            borderColor: '#46b450',
                            backgroundColor: 'rgba(70, 180, 80, 0.1)',
                            tension: 0.3,
                            fill: false
                        },
                        {
                            label: 'Niezaindeksowane',
                            data: indexfixer_stats.not_indexed,
                            borderColor: '#dc3232',
                            backgroundColor: 'rgba(220, 50, 50, 0.1)',
                            tension: 0.3,
                            fill: false
                        },
                        {
                            label: 'Odkryte',
                            data: indexfixer_stats.discovered,
                            borderColor: '#ffb900',
                            backgroundColor: 'rgba(255, 185, 0, 0.1)',
                            tension: 0.3,
                            fill: false
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return value.toLocaleString();
                                }
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top'
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.dataset.label + ': ' + context.parsed.y.toLocaleString();
                                }
                            }
                        }
                    }
                }
            });
        }
        
        // Wykresy dla typów postów
        if (indexfixer_stats.post_types) {
            Object.keys(indexfixer_stats.post_types).forEach(post_type => {
                const chartId = 'trend-chart-' + post_type;
                const chartElement = document.getElementById(chartId);
                if (chartElement) {
                    const ctx = chartElement.getContext('2d');
                    const postTypeData = indexfixer_stats.post_types[post_type];
                    
                    new Chart(ctx, {
                        type: 'line',
                data: {
                            labels: indexfixer_stats.dates,
                            datasets: [
                                {
                                    label: 'Zaindeksowane',
                                    data: postTypeData.indexed,
                                    borderColor: '#46b450',
                                    backgroundColor: 'rgba(70, 180, 80, 0.1)',
                                    tension: 0.3,
                                    fill: false
                                },
                                {
                                    label: 'Niezaindeksowane',
                                    data: postTypeData.not_indexed,
                                    borderColor: '#dc3232',
                                    backgroundColor: 'rgba(220, 50, 50, 0.1)',
                                    tension: 0.3,
                                    fill: false
                                },
                                {
                                    label: 'Odkryte',
                                    data: postTypeData.discovered,
                                    borderColor: '#ffb900',
                                    backgroundColor: 'rgba(255, 185, 0, 0.1)',
                                    tension: 0.3,
                                    fill: false
                                }
                            ]
                },
                options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    ticks: {
                                        callback: function(value) {
                                            return value.toLocaleString();
                                        }
                                    }
                                }
                            },
                    plugins: {
                        legend: {
                                    display: true,
                                    position: 'top'
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                            return context.dataset.label + ': ' + context.parsed.y.toLocaleString();
                                        }
                                    }
                                }
                            }
                        }
                    });
                }
            });
        }
    }

    // Inicjalizuj wykresy po załadowaniu strony
    document.addEventListener('DOMContentLoaded', initCharts);
}); 