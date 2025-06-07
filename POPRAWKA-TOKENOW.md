# Poprawka: Proaktywne Odnawianie Tokenów OAuth

## Problem
Mimo posiadania refresh token, autoryzacja Google Search Console wygasała po około godzinie, powodując błąd "⚠️ Brak autoryzacji Google Search Console".

## Przyczyna
System czekał aż access token wygaśnie całkowicie, zamiast odnawiać go proaktywnie przed wygaśnięciem. Dodatkowo brak było mechanizmu zapisywania czasu wygaśnięcia tokenu.

## Rozwiązanie

### 1. Proaktywne Odnawianie (5 minut przed wygaśnięciem)
```php
// PRZED: Sprawdzanie tokenu przez API za każdym razem
$response = wp_remote_get('https://www.googleapis.com/oauth2/v1/tokeninfo?access_token=' . $this->access_token);

// PO: Sprawdzanie czasu wygaśnięcia lokalnie
$token_expires_at = get_option('indexfixer_gsc_token_expires_at', 0);
$expires_soon = ($token_expires_at > 0) && ($token_expires_at - time() < 300); // 5 minut

if ($expires_soon) {
    return $this->refresh_access_token(); // Odnów proaktywnie
}
```

### 2. Zapisywanie Czasu Wygaśnięcia
```php
// Przy pierwszej autoryzacji i każdym odnawianiu
$expires_at = time() + 3600; // 1 godzina domyślnie
if (isset($body['expires_in'])) {
    $expires_at = time() + intval($body['expires_in']);
}
update_option('indexfixer_gsc_token_expires_at', $expires_at);
```

### 3. Informacje o Tokenie w Dashboard Widget
```php
$token_info = $auth_handler->get_token_expiry_info();

if ($token_info['expires_soon']) {
    echo "⏰ Token wygasa za {$token_info['expires_in_minutes']} minut";
} else {
    echo "🔑 Token ważny do " . date('H:i', $token_info['expires_at']);
}
```

## Zmiany w Kodzie

### Plik: `includes/auth-handler.php`

1. **Metoda `is_authorized_with_refresh()`** - dodano proaktywne sprawdzanie
2. **Metoda `refresh_access_token()`** - zapisywanie nowego czasu wygaśnięcia
3. **Metoda `handle_auth_callback()`** - zapisywanie czasu przy pierwszej autoryzacji
4. **Nowa metoda `get_token_expiry_info()`** - informacje o tokenie

### Plik: `includes/dashboard-widget.php`

1. **Wyświetlanie statusu tokenu** - informacje o czasie wygaśnięcia w widget

## Korzyści

✅ **Eliminuje utratę autoryzacji** - tokeny odnawiane przed wygaśnięciem
✅ **Zmniejsza obciążenie API** - mniej wywołań do Google tokeninfo
✅ **Lepsze UX** - użytkownik widzi status tokenu w dashboardzie
✅ **Automatyczne działanie** - brak potrzeby ręcznej interwencji

## Jak Działa

1. **Sprawdzanie co godzinę** (cron) - system sprawdza czy token wygasa wkrótce
2. **5 minut przed wygaśnięciem** - automatyczne odnawianie przez refresh token
3. **Zapisywanie nowego czasu** - każdy nowy token ma zapisany czas wygaśnięcia
4. **Fallback do API** - jeśli brak czasu wygaśnięcia, sprawdź przez Google API (tylko raz)

## Testowanie

Po wdrożeniu poprawki:
1. Sprawdź dashboard widget - powinien pokazywać czas wygaśnięcia tokenu
2. Poczekaj na automatyczne odnawianie (logi w IndexFixer)
3. Sprawdź czy po godzinie autoryzacja nadal działa

## Potencjalne Efekty Uboczne

⚠️ **Nowa opcja w bazie** - `indexfixer_gsc_token_expires_at`
⚠️ **Częstsze odnawianie** - tokeny odnawiane co ~55 minut zamiast po wygaśnięciu
⚠️ **Dodatkowe logi** - więcej informacji o stanie tokenów w logach

Wszystkie zmiany są **backward compatible** - stary kod będzie działał, ale bez proaktywnego odnawiania. 