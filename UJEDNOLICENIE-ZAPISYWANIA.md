# Ujednolicenie Zapisywania Danych URL-ów

## Problem
Funkcja "sprawdź status tego URL" z dashboardu zapisywała dane w innym formacie niż automatyczne sprawdzanie, co powodowało niespójności w bazie danych.

## Przyczyna
Były **3 różne funkcje** sprawdzające URL-e, które zapisywały dane w różnych formatach:

1. **Automatyczne sprawdzanie (cron)** - `indexfixer_check_urls()` w `indexfixer.php`
2. **AJAX główny** - `indexfixer_ajax_check_single_url()` w `indexfixer.php` 
3. **AJAX dashboard** - `ajax_check_single_url()` w `admin/dashboard.php`

## Różnice PRZED poprawką:

### ❌ Funkcja dashboard (`admin/dashboard.php`)
```php
// Zapisywała surowe dane z API
$cached_statuses[$url] = $status; // Surowe dane z Google API
IndexFixer_Database::save_url_status($post_id, $url, $status); // Surowe dane
```

### ❌ Funkcja AJAX główna (`indexfixer.php`)
```php
// Zapisywała przetworzone dane, ale BEZ tabeli bazy danych
IndexFixer_Cache::set_url_status($url, $detailed_status);
// BRAK: IndexFixer_Database::save_url_status()
```

### ✅ Funkcja cron (jedyna poprawna)
```php
// Zapisywała przetworzone dane + tabela bazy danych
IndexFixer_Cache::set_url_status($url, $detailed_status);
IndexFixer_Database::save_url_status($post_id, $url, $detailed_status);
```

## Rozwiązanie

### 1. Ujednolicenie Formatu Danych
Wszystkie funkcje teraz zapisują dane w tym samym formacie:

```php
$detailed_status = array(
    'verdict' => 'PASS|NEUTRAL|FAIL',
    'coverageState' => 'Submitted and indexed|Crawled - currently not indexed|...',
    'robotsTxtState' => 'ALLOWED|DISALLOWED',
    'indexingState' => 'INDEXING_ALLOWED|INDEXING_DISALLOWED',
    'pageFetchState' => 'SUCCESSFUL|SOFT_404|...',
    'lastCrawlTime' => '2025-01-01T12:00:00Z',
    'crawledAs' => 'DESKTOP|MOBILE',
    'referringUrls' => array(...),
    'sitemap' => array(...),
    'simple_status' => 'INDEXED|NOT_INDEXED|PENDING|OTHER' // Dla kompatybilności
);
```

### 2. Ujednolicenie Zapisywania
Wszystkie funkcje teraz wykonują te same operacje:

```php
// 1. Przygotuj ujednolicone dane
$detailed_status = prepare_detailed_status($status);

// 2. Zapisz w cache (transient)
IndexFixer_Cache::set_url_status($url, $detailed_status);

// 3. Znajdź post_id (z fallback)
$post_id = find_post_id_for_url($url);

// 4. Zapisz w tabeli bazy danych
IndexFixer_Database::save_url_status($post_id ?: 0, $url, $detailed_status);

// 5. Zaloguj sukces
IndexFixer_Logger::log("✅ Sprawdzono URL: $url - Verdict: ...", 'success');
```

### 3. Ujednolicenie Logowania
Wszystkie funkcje teraz logują w tym samym formacie:

```php
// PRZED
IndexFixer_Logger::log("Zaktualizowano status URL w tabeli: $url", 'info');

// PO
IndexFixer_Logger::log("✅ Sprawdzono URL: $url - Verdict: {$verdict}, Coverage: {$coverage}", 'success');
```

## Zmiany w Kodzie

### Plik: `indexfixer.php` - funkcja `indexfixer_ajax_check_single_url()`
- ✅ Dodano `simple_status` dla kompatybilności
- ✅ Dodano zapisywanie w tabeli bazy danych
- ✅ Dodano znajdowanie `post_id` z fallback
- ✅ Ujednolicono logowanie

### Plik: `admin/dashboard.php` - funkcja `ajax_check_single_url()`
- ✅ Zmieniono z surowych na przetworzone dane
- ✅ Dodano `simple_status` dla kompatybilności  
- ✅ Dodano znajdowanie `post_id` z fallback
- ✅ Ujednolicono logowanie
- ✅ Dodano zwracanie ujednoliconych danych w JSON

## Korzyści

✅ **Spójność danych** - wszystkie funkcje zapisują dane w tym samym formacie
✅ **Kompletność** - wszystkie funkcje zapisują zarówno w cache jak i tabeli
✅ **Kompatybilność** - zachowano `simple_status` dla starszego kodu
✅ **Lepsze logi** - ujednolicone i bardziej informacyjne komunikaty
✅ **Znajdowanie post_id** - wszystkie funkcje używają tego samego algorytmu

## Testowanie

Po wdrożeniu poprawki:

1. **Sprawdź URL przez dashboard** - kliknij "🔄" przy URL-u
2. **Sprawdź URL przez AJAX** - użyj głównego dashboardu
3. **Sprawdź automatyczne sprawdzanie** - poczekaj na cron
4. **Porównaj dane** - wszystkie powinny mieć ten sam format w bazie

## Potencjalne Efekty Uboczne

⚠️ **Zmiana formatu odpowiedzi AJAX** - dashboard może potrzebować aktualizacji JS
⚠️ **Więcej danych w bazie** - funkcje AJAX teraz też zapisują w tabeli
⚠️ **Więcej logów** - bardziej szczegółowe informacje w logach

Wszystkie zmiany są **backward compatible** - stary kod będzie działał z nowymi danymi. 