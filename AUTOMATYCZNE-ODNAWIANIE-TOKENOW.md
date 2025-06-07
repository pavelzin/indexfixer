# Automatyczne Odnawianie Tokenów Google - IndexFixer v1.0.30

## Problem
Tokeny Google Search Console wygasają co godzinę, a WordPress cron nie działa regularnie (tylko gdy ktoś wejdzie na stronę). Użytkownicy musieli ręcznie odnawiać autoryzację.

## Rozwiązanie
**Proaktywne odnawianie tokenów przed każdym requestem do Google API.**

### Jak działa:
1. **Przed każdym sprawdzeniem URL-a** system sprawdza czy token wygasa w ciągu **30 minut**
2. Jeśli tak - **automatycznie odnawia token** używając `refresh_token`
3. Dopiero potem wykonuje właściwy request do Google Search Console
4. **Nie wymaga crona** - działa przy każdym użyciu API

### Implementacja:

#### Nowa funkcja `ensure_fresh_token()` w `includes/gsc-api.php`:
```php
private function ensure_fresh_token() {
    // Sprawdź czas wygaśnięcia tokenu
    $token_expires_at = get_option('indexfixer_gsc_token_expires_at', 0);
    $current_time = time();
    $time_until_expiry = $token_expires_at - $current_time;
    
    // Jeśli token wygasł lub wygasa w ciągu 30 minut - odnów go
    if ($time_until_expiry <= 1800) { // 30 minut = 1800 sekund
        $refresh_result = $this->auth_handler->refresh_access_token();
        if (!$refresh_result) {
            return false;
        }
        return true;
    }
    
    return true; // Token jest świeży
}
```

#### Modyfikacja `check_url_status()`:
```php
public function check_url_status($url) {
    // Przeładuj tokeny z bazy
    $this->auth_handler->reload_tokens_from_database();
    
    // NOWE: Sprawdź i odnów token PRZED każdym requestem
    if (!$this->ensure_fresh_token()) {
        return array('error' => 'Brak autoryzacji - token wygasł i nie udało się go odnowić');
    }
    
    // Wykonaj właściwy request do Google API
    // ...
}
```

### Zalety:
- ✅ **Automatyczne** - nie wymaga interwencji użytkownika
- ✅ **Proaktywne** - odnawia 30 minut przed wygaśnięciem
- ✅ **Niezawodne** - działa bez crona WordPress
- ✅ **Bezpieczne** - sprawdza przed każdym requestem
- ✅ **Kompatybilne** - działa z istniejącym kodem

### Logi:
System loguje wszystkie operacje:
```
🕐 Token wygasa za 25 minut (2025-06-07 13:16:11)
🔄 Token wygasa za mniej niż 30 minut - proaktywnie odnawiam
✅ Token został pomyślnie odnowiony
✅ Token jest świeży, przechodze dalej...
```

### Dla użytkowników:
- **Nie trzeba** ręcznie odnawiać autoryzacji
- **Nie trzeba** konfigurować crona
- **Działa automatycznie** w tle
- **Przezroczyste** dla użytkownika końcowego

## Pliki zmodyfikowane:
- `includes/gsc-api.php` - dodano `ensure_fresh_token()`
- `indexfixer.php` - wersja 1.0.30

## Testowanie:
1. Ustaw token z krótkim czasem wygaśnięcia
2. Sprawdź URL przez widget/dashboard
3. Sprawdź logi - powinno być automatyczne odnawianie
4. Potwierdź że nowy token ma poprawną datę wygaśnięcia 