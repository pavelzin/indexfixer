# IndexFixer - WordPress Plugin

## Opis

IndexFixer to zaawansowana wtyczka WordPress do monitorowania statusu indeksowania URL-ów w Google Search Console. Wtyczka pozwala sprawdzać status indeksowania wszystkich URL-ów na stronie i śledzić postęp indeksowania przez Google.

## 🚀 Nowe funkcje w wersji 1.0.22 

### 🔄 Automatyczne Odnawianie Tokenów OAuth
- **Inteligentne odnawianie**: Automatyczne odświeżanie access tokenów bez udziału użytkownika
- **Wykrywanie wygasłych tokenów**: Automatyczne wykrywanie i obsługa wygasłych refresh tokenów
- **Czyszczenie tokenów**: Automatyczne usuwanie nieważnych tokenów i wymuszanie ponownej autoryzacji
- **Częstszy cron**: Zadania sprawdzające uruchamiane co godzinę zamiast co 6 godzin dla świeżych tokenów

### 🔄 Funkcja Wznowienia Sprawdzania
- **Inteligentne wznowienie**: Sprawdzanie tylko URL-ów bez danych API (nie resetuje wszystkiego)
- **Wykrywanie niekompletnych danych**: Automatyczne znajdowanie URL-ów potrzebujących sprawdzenia
- **AJAX panel**: Przycisk "Wznów Sprawdzanie" w dashboardzie zarządzania
- **Szczegółowe logowanie**: Pełne informacje o procesie wznowienia

### 📊 WordPress Dashboard Widget
- **Widget w dashboardzie**: Miniaturowy przegląd statystyk w głównym dashboardzie WordPress
- **Statystyki na żywo**: Zaindeksowane, niezaindeksowane, odkryte URL-e
- **Szybkie odświeżanie**: Przycisk do uruchomienia sprawdzania z poziomu dashboardu
- **Konfiguracja**: Opcje wyświetlania i auto-odświeżania

### 🎯 Gutenberg Block Widget
- **Block Editor**: Widget dostępny w nowym edytorze Gutenberg
- **Wybór postów**: Ręczny wybór konkretnych postów do wyświetlenia
- **Stylowanie**: Nowoczesny interfejs dopasowany do Gutenberg
- **Responsywność**: Pełne wsparcie dla responsywnych motywów

### 🗄️ Dedykowana Tabela MySQL
- **Kompletna migracja**: Przejście z wp_options na dedykowaną tabelę `wp_indexfixer_urls`
- **Wydajność**: Znacznie szybsze zapytania i operacje na dużych zbiorach danych
- **Większe limity**: Obsługa tysięcy URL-ów bez problemów z wydajnością
- **Narzędzia migracji**: Panel do migracji starych danych z automatyczną konwersją

### 🔧 Panel Zarządzania
- **Statystyki bazy**: Kompletny przegląd danych w tabeli MySQL
- **Narzędzia administratora**: Migracja, czyszczenie cache, debug bazy danych
- **Lista URL-ów**: Top 10 niezaindeksowanych postów z możliwością sprawdzania
- **Operacje masowe**: Łatwe zarządzanie dużymi zbiorami URL-ów

## Główne Funkcje

### ✅ Autoryzacja Google z Auto-Refresh
- Pełna integracja z Google Search Console API
- Bezpieczna autoryzacja OAuth 2.0 z automatycznym odnawianiem tokenów
- Inteligentna obsługa wygasłych refresh tokenów
- Konfiguracja przez interfejs WordPress

### 📈 Monitorowanie URL-ów
- Automatyczne pobieranie wszystkich URL-ów ze strony
- Sprawdzanie statusu indeksowania w Google
- Obsługa do **1000+ URL-ów** (bez limitu dzięki tabeli MySQL)
- Szczegółowe informacje o statusie każdego URL-a

### 📊 Statystyki i Wykresy
- Kompletne statystyki indeksowania
- Wykresy Chart.js pokazujące rozkład statusów
- Wizualne przedstawienie postępu indeksowania
- Statystyki w czasie rzeczywistym

### 🔄 Automatyczne Sprawdzanie
- Harmonogram sprawdzania URL-ów co godzinę (dla świeżych tokenów)
- Inteligentne cache'owanie wyników
- Rate limiting dla API Google (sleep 2s między żądaniami)
- System logowania aktywności

## Instalacja

1. **Pobierz wtyczkę** z repozytorium GitHub
2. **Zainstaluj** w WordPress (panel administracyjny → Wtyczki → Dodaj nową → Wgraj wtyczkę)
3. **Aktywuj** wtyczkę
4. **Przejdź** do IndexFixer w menu WordPress
5. **Skonfiguruj** autoryzację Google Search Console

## Konfiguracja Google Search Console API

### Krok 1: Utwórz projekt w Google Cloud Console
1. Przejdź do [Google Cloud Console](https://console.cloud.google.com/)
2. Utwórz nowy projekt lub wybierz istniejący
3. Włącz **Google Search Console API**

### Krok 2: Utwórz dane uwierzytelniające OAuth 2.0
1. Przejdź do **Dane uwierzytelniające** → **Utwórz dane uwierzytelniające** → **Identyfikator klienta OAuth**
2. Wybierz **Aplikacja internetowa**
3. Dodaj **Autoryzowane identyfikatory URI przekierowania**:
   ```
   https://twoja-domena.pl/wp-admin/admin.php?page=indexfixer
   ```

### Krok 3: Konfiguracja w WordPress
1. Skopiuj **Client ID** i **Client Secret**
2. Wklej je w ustawieniach IndexFixer
3. Kliknij **Autoryzuj z Google**
4. Zaloguj się do konta Google powiązanego z Search Console

## Widget Niezaindeksowanych Postów 🎯

### Klasyczny Widget WordPress
1. **Przejdź do**: Wygląd → Widgety
2. **Znajdź widget**: "IndexFixer - Niezaindeksowane posty"  
3. **Przeciągnij** do wybranego obszaru (np. boczny panel)
4. **Skonfiguruj**:
   - Ustaw tytuł (np. "Posty do zalinkowania")
   - Wybierz liczbę postów (5-10 optymalnie)
   - Włącz automatyczne sprawdzanie co 24h

### Gutenberg Block Widget
1. **W edytorze postów/stron**: Dodaj nowy blok → "IndexFixer Posts"
2. **Wybierz posty**: Ręcznie wybierz konkretne posty do wyświetlenia
3. **Konfiguruj**: Ustaw tytuł i opcje wyświetlania
4. **Zapisz**: Blok automatycznie wyświetli wybrane posty

### Dashboard Widget WordPress
1. **Automatycznie dodany** do głównego dashboardu WordPress
2. **Miniaturowe statystyki**: Szybki przegląd statusów indeksowania
3. **Przycisk odświeżania**: Uruchomienie sprawdzania z poziomu dashboardu
4. **Konfiguracja**: Opcje w ustawieniach dashboardu

## Zarządzanie Bazą Danych

### Automatyczna Migracja z wp_options
- **Automatyczna**: Dane migrują się automatycznie przy pierwszym użyciu v1.0.22
- **Ręczna**: Panel "Widget" → "Migracja Danych" → "Uruchom migrację"
- **Bezpieczna**: Stare dane pozostają jako backup w wp_options
- **Kompletna**: Migracja transientów i starych formatów danych

### Narzędzia Administracyjne v1.0.22
- **Statystyki bazy**: Podgląd liczby URL-ów według statusów z tabeli MySQL
- **Lista postów**: Top 10 niezaindeksowanych postów z szczegółami
- **Debug bazy danych**: Narzędzie do sprawdzania stanu tabeli `wp_indexfixer_urls`
- **Czyszczenie cache**: Możliwość wyczyszczenia starych danych wp_options
- **Wznów sprawdzanie**: Inteligentne wznowienie tylko dla URL-ów bez danych

## Użycie

### Dashboard Główny
- **Przegląd wszystkich URL-ów** na stronie
- **Statystyki indeksowania** z wykresami Chart.js
- **Sprawdzanie pojedynczych URL-ów** przyciskiem 🔄
- **Export do CSV** wszystkich danych
- **Wznów sprawdzanie** - dla przerwanych procesów

### Panel Zarządzania
- **Statystyki bazy danych** - kompletny przegląd tabeli MySQL
- **Instrukcje konfiguracji** widget WordPress i Gutenberg
- **Narzędzia migracji** i zarządzania danymi
- **Lista niezaindeksowanych** z możliwością sprawdzania
- **Debug i maintenance** tools

### Dashboard WordPress
- **Miniaturowy widget** z podstawowymi statystykami
- **Szybkie odświeżanie** bez wchodzenia w główny panel
- **Auto-refresh** opcjonalnie co 5 minut
- **Konfiguracja** w ustawieniach dashboardu

### Automatyczne Funkcje v1.0.22
- **Nowe posty**: Automatycznie dodawane do sprawdzania przy publikacji
- **Sprawdzanie co godzinę**: Zadania cron uruchamiane częściej dla świeżych tokenów
- **Auto-refresh tokenów**: Automatyczne odnawianie bez udziału użytkownika
- **Rate limiting**: Automatyczne opóźnienia 2s między żądaniami API
- **Inteligentne cache**: Pomijanie już sprawdzonych URL-ów

## Statystyki

Plugin zbiera następujące metryki:

### Statusy Indeksowania
- **Indexed**: URL jest zaindeksowany w Google
- **Not Indexed**: URL nie jest zaindeksowany  
- **Discovered**: URL został odkryty ale nie zaindeksowany
- **Excluded**: URL wykluczony (robots.txt, 404, etc.)

### Verdict Google
- **Pass**: Strona przeszła walidację
- **Neutral**: Status neutralny  
- **Fail**: Problemy ze stroną

### Robots.txt
- **Allowed**: Dostęp dozwolony
- **Disallowed**: Dostęp zablokowany

## Limity i Wydajność v1.0.22

- **Limit URL-ów**: Praktycznie bez ograniczeń (tabela MySQL)
- **Rate limiting**: 2 sekundy między żądaniami API
- **Cache**: Inteligentne cache'owanie zapobiega duplikatom
- **Automatyczne sprawdzanie**: Co godzinę dla tokenów, co 24h dla widget
- **Timeout**: Zabezpieczenie przed zbyt długimi procesami
- **Wydajność bazy**: Optymalizowane zapytania MySQL z indeksami

## Logowanie

Plugin loguje wszystkie działania:
- Autoryzację Google i odnawianie tokenów
- Sprawdzanie URL-ów  
- Błędy API i problemy z autoryzacją
- Migrację danych
- Działania widget i dashboard
- Operacje wznowienia sprawdzania

Logi dostępne w dashboardzie wtyczki z filtrowaniem według poziomu.

## Wymagania Systemowe

- **WordPress**: 5.0 lub nowszy
- **PHP**: 7.4 lub nowszy
- **MySQL**: 5.6 lub nowszy (z obsługą CREATE TABLE)
- **cURL**: Wymagany dla połączeń API
- **Google Search Console**: Skonfigurowane dla domeny

## Wersje

### 1.0.22 (Aktualna) 🚀
- 🔄 **Automatyczne odnawianie tokenów OAuth** - inteligentna obsługa wygasłych tokenów
- ⏰ **Cron co godzinę** - częstsze zadania dla świeżych tokenów (zamiast co 6h)
- 🔄 **Wznów sprawdzanie** - inteligentne wznowienie tylko URL-ów bez danych
- 📊 **Dashboard Widget WordPress** - miniaturowe statystyki w głównym dashboardzie
- 🎯 **Gutenberg Block Widget** - nowoczesny widget dla Block Editor
- 🗄️ **Kompletna migracja MySQL** - dedykowana tabela zamiast wp_options
- 🔧 **Panel zarządzania** - narzędzia administracyjne i debug
- 📋 **Szczegółowe logowanie** - rozszerzone logi OAuth i API

### 1.0.7-1.0.21
- Różne poprawki autoryzacji, zapisywania danych i funkcji wznowienia
- Ewolucja od podstawowych funkcji do zaawansowanego systemu

### 1.0.6
- 🐛 **Poprawka zapisu bazy** - naprawiono zapisywanie URL-ów do tabeli
- 📊 **Lepsze ładowanie danych** - ulepszona metoda `get_cached_urls()`
- 🔍 **Debug tabeli bazy** - narzędzie do sprawdzania stanu tabeli
- 🔧 **Pojedyncze sprawdzanie** - poprawiono AJAX sprawdzanie URL-ów

### 1.0.3-1.0.5
- 🎯 **Widget WordPress** - podstawowy widget niezaindeksowanych postów
- 🗄️ **Tabela bazy danych** - początkowa implementacja tabeli MySQL
- 🔄 **Automatyczne sprawdzanie** - zadania cron i widget
- 📊 **Statystyki** - ulepszone dashboard i metryki

## Wsparcie

- **GitHub Issues**: https://github.com/pavelzin/indexfixer/issues
- **Dokumentacja**: https://github.com/pavelzin/indexfixer
- **Email**: [wsparcie przez GitHub]

## Licencja

MIT License - szczegóły w pliku LICENSE w repozytorium. 