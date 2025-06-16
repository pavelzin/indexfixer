# Optymalizacja sprawdzania tokenów - IndexFixer v1.3.12

## Problem
Użytkownik zauważył, że plugin ciągle sprawdza token przed każdym API call, co powodowało nadmierną ilość logów:

```
2025-06-16 16:46:01 Przeładowano tokeny z bazy danych
2025-06-16 16:46:01 🕐 Token wygasa za 44 minut (2025-06-16 15:30:00)
2025-06-16 16:46:01 ✅ Token jest świeży (wygasa za więcej niż 30 minut)
2025-06-16 16:46:01 ✅ Token jest świeży, przechodze dalej...
```

To się powtarzało **przy każdym z 610 URL-ów** = 2440 dodatkowych linii w logach!

## Analiza problemu

### Dlaczego plugin sprawdzał token za każdym razem?

1. **Tokeny Google wygasają co godzinę** - standard OAuth 2.0
2. **WordPress cron nie jest niezawodny** - uruchamia się tylko gdy ktoś wejdzie na stronę
3. **Masowe sprawdzanie** - przy 610 URL-ach proces może trwać godziny
4. **Bezpieczeństwo** - lepiej sprawdzić token niż dostać błąd 401

### Dlaczego cron co 30 minut to nie wystarcza?

WordPress cron to **pseudo-cron** - nie uruchamia się automatycznie:
- Wymaga odwiedzin na stronie
- Przy długich procesach (nocne sprawdzanie) może nie działać
- Nie gwarantuje wykonania o określonej godzinie

## Rozwiązanie - Optymalizacja sesyjna

Zamiast sprawdzać token przed **każdym** URL-em, sprawdzamy go **raz na sesję**:

### Przed optymalizacją:
```php
public function check_url_status($url) {
    // PRZED KAŻDYM URL:
    $this->auth_handler->reload_tokens_from_database(); // LOG
    if (!$this->ensure_fresh_token()) { // LOG + sprawdzenie
        return error;
    }
    // Wykonaj API call
}
```

### Po optymalizacji:
```php
public function check_url_status($url) {
    // TYLKO RAZ NA SESJĘ (lub co 30 minut):
    static $token_checked_this_session = false;
    static $session_start_time = null;
    
    if (!$token_checked_this_session || (time() - $session_start_time > 1800)) {
        $session_start_time = time();
        $this->auth_handler->reload_tokens_from_database(); // LOG raz
        if (!$this->ensure_fresh_token()) { // LOG + sprawdzenie raz
            return error;
        }
        $token_checked_this_session = true;
    }
    // Wykonaj API call
}
```

### Dodatkowe optymalizacje w `auth-handler.php`:

Zmniejszono logowanie `reload_tokens_from_database()` z każdego wywołania na co 50:

```php
public function reload_tokens_from_database() {
    static $reload_call_counter = 0;
    $reload_call_counter++;
    
    if ($reload_call_counter === 1 || $reload_call_counter % 50 === 0) {
        IndexFixer_Logger::log("Przeładowano tokeny z bazy danych (wywołanie #$reload_call_counter)", 'info');
    }
}
```

## Efekty optymalizacji

### Przed (przy 610 URL-ach):
- **2440 linii** zbędnych logów o sprawdzaniu tokenu
- Token sprawdzany **610 razy**
- Logi zagracone powtarzalnymi informacjami

### Po (przy 610 URL-ach):
- **~20 linii** logów o sprawdzaniu tokenu (raz na sesję + odnowienia)
- Token sprawdzany **1-2 razy** (raz na początek + ewentualne odnowienie)
- Logi czytelne i zawierają tylko istotne informacje

### Bezpieczeństwo zachowane:
- ✅ Token nadal sprawdzany proaktywnie (30 min przed wygaśnięciem)  
- ✅ Automatyczne odnowienie jeśli potrzeba
- ✅ Re-sprawdzenie co 30 minut w długich sesjach
- ✅ Cron backup co 30 minut nadal działa

## Potencjalne efekty uboczne

### Pozytywne:
- 📈 **Znacznie czytelniejsze logi** - łatwiej znaleźć problemy
- ⚡ **Nieznacznie szybsze działanie** - mniej operacji bazodanowych
- 🔍 **Łatwiejszy debugging** - mniej szumu w logach

### Minimalne ryzyko:
- 🤔 **Teoretyczne**: Jeśli token wygaśnie w trakcie sesji (bardzo mało prawdopodobne)
- 🛡️ **Zabezpieczenie**: Re-sprawdzenie co 30 minut w długich sesjach
- 🔄 **Fallback**: Cron nadal odnawia tokeny co 30 minut

## Instalacja

1. Wgraj `indexfixer-1.3.12-token-optimization.zip`
2. Aktywuj plugin
3. Sprawdź logi - powinno być znacznie mniej "gadatliwe"

---

**Wniosek**: Sprawdzanie tokenu przed każdym API call **miało sens bezpieczeństwa**, ale powodowało nadmierną ilość logów. Optymalizacja sesyjna zachowuje bezpieczeństwo, ale drastycznie redukuje szum w logach. 