# IndexFixer - WordPress Plugin

Wtyczka WordPress do sprawdzania statusu indeksowania URL-i w Google Search Console.

## 🚀 Funkcje

### ✅ Sprawdzanie statusu indeksowania
- **Masowe sprawdzanie** wszystkich URL-i na stronie (posty, strony, produkty)
- **Pojedyncze sprawdzanie** dowolnego URL-a
- **Szczegółowe informacje** z Google Search Console API:
  - Verdict (PASS/NEUTRAL/FAIL)
  - Coverage State (zaindeksowane/nie zaindeksowane)
  - Robots.txt status
  - Indexing state
  - Page fetch state
  - Data ostatniego crawl'a
  - Linki wewnętrzne

### 📊 Dashboard z tabelą
- **Sortowanie** po wszystkich kolumnach
- **Filtrowanie** po statusach i robots.txt
- **Kolorowe kodowanie** statusów
- **Tooltipsy** z dodatkowymi informacjami
- **Responsywny design**

### 🔧 Dodatkowe funkcje
- **Cache** wyników (24h)
- **Automatyczne sprawdzanie** co 6 godzin
- **Eksport do CSV**
- **Szczegółowe logi**
- **Limit URL-ów** (domyślnie 500, konfigurowalny)

## 📋 Wymagania

- WordPress 5.0+
- PHP 7.4+
- Konto Google Cloud Console z włączonym Search Console API
- Autoryzacja OAuth 2.0

## 🛠 Instalacja

1. **Pobierz** najnowszą wersję z [Releases](https://github.com/pavelzin/indexfixer/releases)
2. **Wgraj** ZIP przez WordPress admin lub rozpakuj do `/wp-content/plugins/`
3. **Aktywuj** wtyczkę w panelu WordPress
4. **Skonfiguruj** Google Cloud Console (patrz sekcja Konfiguracja)

## ⚙️ Konfiguracja Google Cloud Console

### 1. Utwórz projekt w Google Cloud Console
1. Przejdź do [Google Cloud Console](https://console.cloud.google.com/)
2. Utwórz nowy projekt lub wybierz istniejący
3. Włącz **Google Search Console API**

### 2. Skonfiguruj OAuth 2.0
1. Przejdź do **APIs & Services > Credentials**
2. Kliknij **Create Credentials > OAuth 2.0 Client IDs**
3. Wybierz **Web application**
4. Dodaj **Authorized redirect URI**:
   ```
   https://twoja-domena.pl/wp-admin/admin.php?page=indexfixer&action=auth_callback
   ```
5. Zapisz **Client ID** i **Client Secret**

### 3. Konfiguracja wtyczki
1. Przejdź do **IndexFixer > Konfiguracja** w WordPress admin
2. Wpisz **Client ID** i **Client Secret**
3. Kliknij **Zaloguj się przez Google**
4. Autoryzuj dostęp do Search Console

## 📖 Użytkowanie

### Sprawdzanie pojedynczego URL-a
1. Przejdź do **IndexFixer** w menu WordPress
2. W sekcji "Sprawdź pojedynczy URL" wpisz adres
3. Kliknij **Sprawdź URL**
4. Zobacz szczegółowe wyniki

### Masowe sprawdzanie
1. Kliknij **Odśwież dane** w dashboardzie
2. Wtyczka sprawdzi wszystkie URL-e (limit 500)
3. Wyniki pojawią się w tabeli

### Filtrowanie i sortowanie
- **Kliknij nagłówek** kolumny aby posortować
- **Użyj filtrów** aby pokazać tylko określone statusy
- **Hover nad wartościami** aby zobaczyć tooltipsy

## 🎨 Kolorowe kodowanie

- 🟢 **Zielone** - PASS, ALLOWED, SUCCESSFUL
- 🔵 **Niebieskie** - NEUTRAL
- 🔴 **Czerwone** - FAIL, DISALLOWED, błędy
- 🔘 **Szare** - brak danych

## 📁 Struktura plików

```
indexfixer/
├── indexfixer.php          # Główny plik wtyczki
├── admin/
│   └── dashboard.php       # Klasa dashboardu
├── includes/
│   ├── auth-handler.php    # Obsługa OAuth
│   ├── gsc-api.php        # API Google Search Console
│   ├── cache.php          # System cache
│   ├── logger.php         # System logowania
│   ├── helpers.php        # Funkcje pomocnicze
│   └── fetch-urls.php     # Pobieranie URL-ów
├── templates/
│   └── dashboard.php      # Szablon dashboardu
├── assets/
│   ├── css/
│   │   └── admin.css      # Style CSS
│   └── js/
│       └── admin.js       # JavaScript
└── uninstall.php          # Czyszczenie przy usuwaniu
```

## 🔧 Filtry WordPress

```php
// Zmień limit sprawdzanych URL-ów
add_filter('indexfixer_url_limit', function($limit) {
    return 50; // Sprawdzaj tylko 50 URL-ów
});
```

## 📝 Changelog

### v1.0.1 (2025-01-06)
- ✅ Dodano sprawdzanie pojedynczego URL-a
- ✅ Poprawiono wyświetlanie szczegółowych statusów
- ✅ Dodano kolorowe kodowanie
- ✅ Ulepszone sortowanie i filtrowanie

### v1.0.0 (2025-01-05)
- 🎉 Pierwsza wersja
- ✅ Integracja z Google Search Console API
- ✅ Masowe sprawdzanie URL-ów
- ✅ Dashboard z tabelą
- ✅ System cache i logowania

## 🤝 Wsparcie

- **Issues**: [GitHub Issues](https://github.com/pavelzin/indexfixer/issues)
- **Dokumentacja**: [Wiki](https://github.com/pavelzin/indexfixer/wiki)
- **Autor**: [Paweł Zinkiewicz](https://bynajmniej.pl)

## 📄 Licencja

GPL v2 or later - patrz [LICENSE](LICENSE) file.

## 🙏 Podziękowania

Wtyczka wykorzystuje:
- Google Search Console API
- WordPress REST API
- jQuery dla interfejsu użytkownika 