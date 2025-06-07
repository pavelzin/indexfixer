# POPRAWKA STATYSTYK SPRAWDZONYCH URL-I

## Problem
Dashboard pokazywał 100% sprawdzonych URL-i, mimo że w bazie było 86 URL-i które miały tylko techniczne wpisy (bez faktycznego sprawdzenia przez API). Te URL-e miały wypełnione tylko pola `last_status_change`, `check_count`, `created_at`, ale nie miały danych z API (`last_checked`, `verdict`, `coverage_state` były NULL).

## Rozwiązanie
Poprawiono logikę liczenia "sprawdzonych" URL-i we wszystkich miejscach - teraz URL jest uznawany za sprawdzony tylko jeśli ma wypełnione pole `last_checked` (faktycznie sprawdzony przez API).

## Wprowadzone zmiany

### 1. `includes/dashboard-widget.php` - funkcja `calculate_statistics()`
**PRZED:**
```php
if ($status_data !== false) {
    $stats['checked']++;
```

**PO:**
```php
// POPRAWKA: URL jest sprawdzony tylko jeśli ma wypełnione last_checked (faktycznie sprawdzony przez API)
if ($status_data !== false && !empty($status_data['lastChecked'])) {
    $stats['checked']++;
```

### 2. `admin/dashboard.php` - logika liczenia w `render_page()`
**PRZED:**
```php
if ($status_data !== false) {
    $stats['checked']++;
```

**PO:**
```php
// POPRAWKA: URL jest sprawdzony tylko jeśli ma wypełnione last_checked (faktycznie sprawdzony przez API)
if ($status_data !== false && !empty($status_data['lastChecked'])) {
    $stats['checked']++;
```

### 3. `includes/database.php` - funkcja `get_statistics()`
**PRZED:**
```php
if ($stat->status !== 'unknown') {
    $result['checked'] += $stat->count;
}
```

**PO:**
```php
// POPRAWKA: URL jest sprawdzony tylko jeśli ma wypełnione last_checked (faktycznie sprawdzony przez API)
if ($stat->status !== 'unknown' && !empty($stat->last_checked)) {
    $result['checked'] += $stat->count;
}
```

### 4. `admin/dashboard.php` - funkcja `get_unchecked_urls()`
**PRZED:**
```php
if (!$db_status || 
    (is_array($db_status) && isset($db_status['status']) && $db_status['status'] === 'unknown') ||
    (is_array($db_status) && isset($db_status['verdict']) && $db_status['verdict'] === 'unknown')) {
    $unchecked[] = $url_data;
}
```

**PO:**
```php
// POPRAWKA: URL jest niesprawdzony jeśli nie ma danych w tabeli LUB nie ma wypełnionego last_checked
if (!$db_status || 
    empty($db_status['lastChecked']) ||
    (is_array($db_status) && isset($db_status['verdict']) && $db_status['verdict'] === 'unknown')) {
    $unchecked[] = $url_data;
}
```

## Efekt poprawek

### Przed poprawkami:
- Dashboard pokazywał 100% sprawdzonych URL-i
- W bazie było 86 URL-i z tylko technicznymi wpisami (bez danych z API)
- Statystyki były nieprawdziwe

### Po poprawkach:
- Dashboard pokazuje rzeczywistą liczbę sprawdzonych URL-i
- URL-e bez `last_checked` nie są liczone jako sprawdzone
- Funkcja "Wznów sprawdzanie URL-i niesprawdzonych" znajdzie te 86 URL-i i sprawdzi je przez API
- Po sprawdzeniu statystyki będą zgodne z rzeczywistością

## Jak używać

1. **Zainstaluj poprawioną wersję wtyczki**
2. **Sprawdź dashboard** - procent sprawdzonych URL-i może się zmniejszyć (ale będzie prawdziwy)
3. **Użyj "Wznów sprawdzanie URL-i niesprawdzonych"** w menu zarządzania
4. **Poczekaj na sprawdzenie** - te 86 URL-i zostaną faktycznie sprawdzone przez API
5. **Dashboard pokaże prawdziwe statystyki** - URL-e będą teraz poprawnie liczone

## Wersja
IndexFixer v1.0.26 - STATS-CHECKED-FIX

## Data
7 czerwca 2025 