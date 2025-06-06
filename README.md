# IndexFixer - WordPress Plugin

## Opis

IndexFixer to zaawansowana wtyczka WordPress do monitorowania statusu indeksowania URL-ów w Google Search Console. Wtyczka pozwala sprawdzać status indeksowania wszystkich URL-ów na stronie i śledzić postęp indeksowania przez Google.

## Nowe funkcje w wersji 1.0.3 🚀

### 🎯 Widget WordPress dla Niezaindeksowanych Postów
- **Widget na stronie**: Wyświetla 5-10 niezaindeksowanych postów na stronie głównej
- **Automatyczne odświeżanie**: Gdy Google zaindeksuje post, automatycznie zniknie z listy  
- **Inteligentne linkowanie**: Pomaga w strategii linkowania wewnętrznego
- **Konfiguracja**: Łatwa konfiguracja przez panel widgetów WordPress

### 🗄️ Własna Tabela Bazy Danych
- **Wydajność**: Przejście z wp_options na dedykowaną tabelę MySQL
- **Większe limity**: Obsługa znacznie większej liczby URL-ów
- **Migracja**: Automatyczna migracja danych z wp_options
- **Kompatybilność wsteczna**: Pełna kompatybilność z istniejącymi danymi

### 🔄 Automatyczne Sprawdzanie
- **Codzienne sprawdzanie**: Widget automatycznie sprawdza URL-e co 24h  
- **Nowe posty**: Automatyczne dodawanie nowych postów do sprawdzania
- **Rate limiting**: Inteligentne opóźnienia dla API Google
- **Logowanie**: Szczegółowe logi wszystkich operacji

### 📊 Ulepszone Statystyki  
- **Nowy dashboard**: Strona zarządzania widgetem z statystykami
- **Szczegółowe metryki**: Liczba sprawdzeń, ostatnie sprawdzenie, zmiany statusu
- **Filtrowanie**: Widok URL-ów według statusu (indexed, not_indexed, discovered)

## Główne Funkcje

### ✅ Autoryzacja Google
- Pełna integracja z Google Search Console API
- Bezpieczna autoryzacja OAuth 2.0
- Konfiguracja przez interfejs WordPress

### 📈 Monitorowanie URL-ów
- Automatyczne pobieranie wszystkich URL-ów ze strony
- Sprawdzanie statusu indeksowania w Google
- Obsługa do **500 URL-ów** (konfigurowalny limit)
- Szczegółowe informacje o statusie każdego URL-a

### 📊 Statystyki i Wykresy
- Kompletne statystyki indeksowania
- Wykresy Chart.js pokazujące rozkład statusów
- Wizualne przedstawienie postępu indeksowania
- Statystyki w czasie rzeczywistym

### 🔄 Automatyczne Sprawdzanie
- Harmonogram sprawdzania URL-ów co 6 godzin
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
   https://twoja-domena.pl/wp-admin/admin.php?page=indexfixer&action=auth_callback
   ```

### Krok 3: Konfiguracja w WordPress
1. Skopiuj **Client ID** i **Client Secret**
2. Wklej je w ustawieniach IndexFixer
3. Kliknij **Autoryzuj z Google**
4. Zaloguj się do konta Google powiązanego z Search Console

## Widget Niezaindeksowanych Postów 🎯

### Instalacja Widget
1. **Przejdź do**: Wygląd → Widgety (lub Wygląd → Edytor motywów → Widgety)
2. **Znajdź widget**: "IndexFixer - Niezaindeksowane posty"  
3. **Przeciągnij** do wybranego obszaru (np. boczny panel)
4. **Skonfiguruj**:
   - Ustaw tytuł (np. "Posty do zalinkowania")
   - Wybierz liczbę postów (5-10 optymalnie)
   - Włącz automatyczne sprawdzanie co 24h

### Jak Działa Widget
- **Linkowanie wewnętrzne**: Widget pokazuje niezaindeksowane posty, które warto linkować wewnętrznie
- **Automatyczne czyszczenie**: Gdy Google zaindeksuje post, automatycznie zniknie z listy
- **Inteligentne odświeżanie**: Nowe posty są automatycznie dodawane do sprawdzania
- **Codzienne sprawdzanie**: URL-e sprawdzane automatycznie co 24h w tle

## Zarządzanie Bazą Danych

### Migracja z wp_options
- **Automatyczna**: Dane migrują się automatycznie przy pierwszym użyciu
- **Ręczna**: Panel "Widget" → "Migracja Danych" → "Uruchom migrację"
- **Bezpieczna**: Stare dane pozostają jako backup w wp_options

### Narzędzia Administracyjne
- **Statystyki bazy**: Podgląd liczby URL-ów według statusów
- **Lista postów**: Top 10 niezaindeksowanych postów z szczegółami
- **Czyszczenie cache**: Możliwość wyczyszczenia starych danych wp_options

## Użycie

### Dashboard Główny
- **Przegląd wszystkich URL-ów** na stronie
- **Statystyki indeksowania** z wykresami
- **Sprawdzanie pojedynczych URL-ów** przyciskiem 🔄
- **Export do CSV** wszystkich danych

### Panel Widget
- **Statystyki bazy danych** - kompletny przegląd
- **Instrukcje konfiguracji** widget WordPress  
- **Narzędzia migracji** i zarządzania
- **Lista niezaindeksowanych** z możliwością sprawdzania

### Automatyczne Funkcje
- **Nowe posty**: Automatycznie dodawane do sprawdzania przy publikacji
- **Codzienne sprawdzanie**: Widget sprawdza 10 najstarszych URL-ów co 24h
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

## Limity i Wydajność

- **Limit URL-ów**: 500 (zdefiniowany przez stałą `INDEXFIXER_URL_LIMIT`)
- **Rate limiting**: 2 sekundy między żądaniami API
- **Cache**: Inteligentne cache'owanie zapobiega duplikatom
- **Automatyczne sprawdzanie**: Co 6 godzin dla wszystkich, co 24h dla widget
- **Timeout**: Zabezpieczenie przed zbyt długimi procesami

## Logowanie

Plugin loguje wszystkie działania:
- Autoryzację Google
- Sprawdzanie URL-ów  
- Błędy API
- Migrację danych
- Działania widget

Logi dostępne w dashboardzie wtyczki.

## Wymagania Systemowe

- **WordPress**: 5.0 lub nowszy
- **PHP**: 7.4 lub nowszy
- **MySQL**: 5.6 lub nowszy
- **cURL**: Wymagany dla połączeń API
- **Google Search Console**: Skonfigurowane dla domeny

## Wersje

### 1.0.7 (Aktualna)
- 🔓 **Odblokowanie procesu** - dodano narzędzie do odblokowania zablokowanego procesu sprawdzania
- 🛠️ **Ulepszone clear-cache.php** - rozszerzony skrypt z interfejsem do odblokowania procesu
- 🔧 **AJAX unlock** - przycisk odblokowania w panelu zarządzania
- 📋 **Lepsze logowanie** - automatyczne logowanie odblokowania procesu

### 1.0.6
- 🐛 **Poprawka zapisu bazy** - naprawiono zapisywanie URL-ów do tabeli (zawsze zapisuje, nawet bez post_id)
- 📊 **Lepsze ładowanie danych** - ulepszona metoda `get_cached_urls()` z pełnymi danymi z tabeli  
- 🔍 **Debug tabeli bazy** - dodano narzędzie debug do sprawdzania stanu tabeli indexfixer_urls
- 🔧 **Pojedyncze sprawdzanie** - poprawiono AJAX sprawdzanie pojedynczych URL-ów

### 1.0.5
- 🎨 **Widget frontend** - usunięto wyświetlanie daty sprawdzenia z widgetu (czytelniejszy UI)
- 📊 **Dashboard** - dodano kolumnę "Ostatnie sprawdzenie API" z dokładną datą i czasem  
- ⏰ **Automatyczne sprawdzanie** - ulepszono logikę tickera w widgetcie (lepsze planowanie harmonogramu)
- 🔧 **Inteligentny harmonogram** - automatyczne włączanie/wyłączanie sprawdzania na podstawie ustawień widgetu

### 1.0.4
- 🐛 **Poprawka widgetu** - naprawiono logikę pobierania niezaindeksowanych postów
- ✅ **Lepsze mapowanie statusów** - widget teraz prawidłowo identyfikuje niezaindeksowane URL-e na podstawie kolumn `verdict` i `coverage_state`
- 🔧 **Usprawnienia bazy danych** - ulepszona metoda `get_urls_by_status()` dla poprawnego filtrowania

### 1.0.3
- ✅ Widget WordPress dla niezaindeksowanych postów
- ✅ Własna tabela bazy danych zamiast wp_options  
- ✅ Automatyczne sprawdzanie URL-ów co 24h przez widget
- ✅ Panel zarządzania widgetem i bazą danych
- ✅ Automatyczne dodawanie nowych postów do sprawdzania
- ✅ Migracja danych z wp_options z pełną kompatybilnością wsteczną

### 1.0.2  
- ✅ Statystyki i wykresy Chart.js
- ✅ Inline styling, zero dependencji CSS
- ✅ Konfigurowalny limit URL-ów jako stała
- ✅ Rate limiting fix (sleep 2s)

### 1.0.1
- ✅ Autoryzacja Google OAuth 2.0
- ✅ Sprawdzanie statusu indeksowania  
- ✅ Dashboard z przyciskami sprawdzania
- ✅ Export CSV
- ✅ System logowania

## GitHub i Wsparcie

- **Repozytorium**: [https://github.com/pavelzin/indexfixer](https://github.com/pavelzin/indexfixer)
- **Issues**: Zgłoś błędy i sugestie w GitHub Issues
- **Dokumentacja**: Pełna dokumentacja w README
- **Autor**: [Pawel Zinkiewicz](https://bynajmniej.pl)

## Licencja

MIT License - możesz swobodnie używać, modyfikować i dystrybuować tę wtyczkę. 