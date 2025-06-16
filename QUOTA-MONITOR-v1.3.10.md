# IndexFixer v1.3.10 - Monitor Limitów API Google Search Console

## 🎯 Cel

Implementacja systemu monitorowania limitów API Google Search Console zgodnie z oficjalną dokumentacją Google, aby zapobiec przekroczeniu limitów i zapewnić stabilne działanie wtyczki.

## 📊 Limity Google Search Console API

Zgodnie z oficjalną dokumentacją Google (https://developers.google.com/webmaster-tools/limits):

### URL Inspection API
- **Dzienny limit:** 2000 requestów na dzień (QPD - Queries Per Day)
- **Minutowy limit:** 600 requestów na minutę (QPM - Queries Per Minute)
- **Projekt limit:** 10,000,000 QPD i 15,000 QPM

### Progi ostrzeżeń
- **Ostrzeżenie:** 80% limitu (1600 requestów/dzień)
- **Krytyczne:** 95% limitu (1900 requestów/dzień)
- **Przekroczenie:** 100% limitu (2000 requestów/dzień)

## 🔧 Implementacja

### 1. Nowa klasa `IndexFixer_Quota_Monitor`

**Plik:** `includes/quota-monitor.php`

**Funkcje:**
- `record_api_request()` - Rejestruje wykonany request
- `can_make_request()` - Sprawdza czy można wykonać request
- `get_usage_stats()` - Pobiera statystyki wykorzystania
- `estimate_quota_exhaustion()` - Szacuje czas wyczerpania limitu
- `send_quota_notification()` - Wysyła powiadomienia email

### 2. Integracja z GSC API

**Plik:** `includes/gsc-api.php`

**Zmiany:**
```php
// Przed każdym requestem
$quota_monitor = IndexFixer_Quota_Monitor::get_instance();
if (!$quota_monitor->can_make_request()) {
    return array('error' => 'Przekroczono dzienny limit API');
}

// Po pomyślnym requeście
$quota_stats = $quota_monitor->record_api_request();
```

### 3. Integracja z Widget Scheduler

**Plik:** `includes/widget-scheduler.php`

**Zmiany:**
```php
// W pętli sprawdzania URL-ów
if (!$quota_monitor->can_make_request()) {
    IndexFixer_Logger::log('🚫 Przerwano sprawdzanie - przekroczono limity API');
    break;
}
```

### 4. Dashboard z monitoringiem

**Plik:** `templates/tabs/overview.php`

**Nowa sekcja:**
- Status wykorzystania API w czasie rzeczywistym
- Prognoza wyczerpania limitu
- Historia ostatnich 7 dni
- Ostrzeżenia i alerty

## 📈 Funkcje Monitoringu

### Śledzenie w czasie rzeczywistym
- Licznik requestów dziennych i minutowych
- Procentowe wykorzystanie limitów
- Status: OK / Ostrzeżenie / Krytyczne / Przekroczony

### Powiadomienia email
- Automatyczne powiadomienia przy 80% i 95% limitu
- Ograniczenie do 1 powiadomienia na 4 godziny
- Szczegółowe informacje o wykorzystaniu

### Historia wykorzystania
- Zapisywanie statystyk dziennych
- Wykres trendów z ostatnich 7 dni
- Analiza wzorców wykorzystania

### Prognozowanie
- Szacowany czas wyczerpania limitu
- Bazuje na aktualnym tempie wykorzystania
- Uwzględnia historię z ostatnich 3 dni

## 🔄 Automatyczne zarządzanie

### Codzienne resetowanie
- **Automatyczny reset statystyk o północy** (00:01) w **strefie czasowej Pacific Time (PST/PDT)**
- **WAŻNE:** Google resetuje limity API o północy czasu pacyficznego, nie lokalnego!
- Strefa czasowa: `America/Los_Angeles` (PST/PDT)
- Zapisywanie historii poprzedniego dnia
- Cron job: `indexfixer_daily_quota_reset`
- **Zgodnie z dokumentacją Google:** Limity resetują się codziennie o północy Pacific Time

### Inteligentne przerywanie
- Automatyczne zatrzymanie sprawdzania przy przekroczeniu limitu
- Kontynuacja następnego dnia po resecie
- Zachowanie kolejności sprawdzania

## 📊 Interfejs użytkownika

### Dashboard - sekcja na górze
```
🟢 Monitor Limitów API Google Search Console
Status: ✅ OK | Dzisiaj: 45/2000 (2.3%) | Pozostało: 1955 requestów | Ta minuta: 0/600
Prognoza: Limit nie zostanie przekroczony dzisiaj
```

### Kolory statusów
- **🟢 Zielony (OK):** < 80% limitu
- **🟡 Żółty (Ostrzeżenie):** 80-95% limitu  
- **🔴 Czerwony (Krytyczne):** 95-100% limitu
- **🚫 Szary (Przekroczony):** > 100% limitu

### Historia w tabeli
| Data | Requestów | % Limitu |
|------|-----------|----------|
| 15.01.2025 | 1,234 | 61.7% |
| 14.01.2025 | 1,890 | 94.5% |
| 13.01.2025 | 2,000 | 100.0% |

## 🚨 Obsługa błędów

### Przekroczenie limitu dziennego
```php
if ($daily_count >= 2000) {
    IndexFixer_Logger::log('🚫 LIMIT DZIENNY PRZEKROCZONY: ' . $daily_count . '/2000', 'error');
    return false;
}
```

### Przekroczenie limitu minutowego
```php
if ($minute_count >= 600) {
    IndexFixer_Logger::log('🚫 LIMIT MINUTOWY PRZEKROCZONY: ' . $minute_count . '/600', 'error');
    return false;
}
```

### Logowanie szczegółowe
```
📊 API Request zarejestrowany: 45/2000 dzisiaj, 1/600 w tej minucie
🟡 OSTRZEŻENIE: Wykorzystano 82.5% dziennego limitu API (1650/2000)
🔴 KRYTYCZNE: Wykorzystano 96.2% dziennego limitu API (1924/2000)
🚫 LIMIT DZIENNY PRZEKROCZONY: 2000/2000
```

## 📧 Powiadomienia email

### Szablon powiadomienia
```
Temat: [Nazwa strony] IndexFixer - Ostrzeżenie o limitach API

Witaj,

IndexFixer na stronie [nazwa] osiągnął krytyczny próg wykorzystania API Google Search Console.

Szczegóły:
- Wykorzystano: 1924/2000 requestów (🔴 KRYTYCZNY)
- Procent wykorzystania: 96.2%
- Okres: dzienny
- Czas: 2025-01-15 14:30:15

Jeśli limit zostanie przekroczony, sprawdzanie URL-ów zostanie wstrzymane do następnego dnia.

Możesz sprawdzić szczegóły w panelu administracyjnym IndexFixer.

Pozdrawiam,
IndexFixer
```

## 🔧 Konfiguracja

### Stałe konfiguracyjne
```php
const DAILY_LIMIT = 2000;          // 2000 QPD
const MINUTE_LIMIT = 600;          // 600 QPM  
const WARNING_THRESHOLD = 0.8;     // 80% limitu
const CRITICAL_THRESHOLD = 0.95;   // 95% limitu
```

### ⏰ Strefa czasowa
**KRYTYCZNE:** System używa strefy czasowej Pacific Time (PST/PDT) zgodnie z Google API!

- **Google API:** Resetuje limity o północy czasu pacyficznego (PST/PDT)
- **Strefa czasowa:** `America/Los_Angeles` (Pacific Time)
- **Reset o północy:** 00:01 PST/PDT (nie lokalnego czasu!)
- **Logi:** Zawierają informację o Pacific Time
- **Dokumentacja:** https://developers.google.com/webmaster-tools/limits

**Przykłady czasu resetu (00:01 PST/PDT):**
- **Kalifornia (PST/PDT):** 00:01 (lokalny czas)
- **Polska (CET/CEST):** 09:01/10:01 (9-10 godzin później)
- **UTC:** 08:01/07:01 (8-7 godzin później)
- **New York (EST/EDT):** 03:01/04:01 (3-4 godziny później)

**Implementacja:**
```php
$pacific_timezone = new DateTimeZone('America/Los_Angeles');
$now_pacific = new DateTime('now', $pacific_timezone);
echo $now_pacific->format('Y-m-d H:i:s T'); // np. "2025-01-15 14:30:15 PST"
```

### Transients i opcje
- `indexfixer_api_requests_YYYY-MM-DD` - licznik dzienny
- `indexfixer_api_requests_minute_YYYY-MM-DD-HH:MM` - licznik minutowy
- `indexfixer_quota_history_YYYY-MM-DD` - historia dzienna
- `indexfixer_quota_notification_LEVEL_YYYY-MM-DD-HH` - powiadomienia

## 📝 Logi

### Przykładowe wpisy
```
2025-01-15 14:30:15 [INFO] 📊 API Request zarejestrowany: 45/2000 dzisiaj (pozostało: 1955)
2025-01-15 16:45:22 [WARNING] 🟡 OSTRZEŻENIE: Wykorzystano 82.5% dziennego limitu API (1650/2000)
2025-01-15 18:20:33 [ERROR] 🔴 KRYTYCZNE: Wykorzystano 96.2% dziennego limitu API (1924/2000)
2025-01-15 19:15:44 [INFO] 📧 Wysłano powiadomienie email o critical przekroczeniu limitu do: admin@example.com
2025-01-15 20:05:55 [ERROR] 🚫 LIMIT DZIENNY PRZEKROCZONY: 2000/2000
2025-01-15 20:06:01 [ERROR] 🚫 Request anulowany - przekroczono limity API Google
2025-01-15 20:06:02 [ERROR] 🚫 Przerwano sprawdzanie - przekroczono limity API Google
2025-01-15 23:59:58 [INFO] ⏰ Zaplanowano codzienne resetowanie limitów API na 2025-01-16 00:01:00 PST (strefa czasowa Pacific: America/Los_Angeles)
2025-01-15 23:59:59 [INFO] 📋 WAŻNE: Google Search Console API resetuje limity o północy czasu pacyficznego (PST/PDT)
2025-01-16 00:01:00 [INFO] 🔄 Reset dziennych statystyk API - nowy dzień rozpoczęty (2025-01-16 00:01:00 PST, Pacific Time: America/Los_Angeles)
2025-01-16 00:01:01 [INFO] 📊 Zapisano statystyki z 2025-01-15: 2000 requestów (Pacific Time: America/Los_Angeles)
2025-01-16 00:01:02 [INFO] 📋 Zgodnie z dokumentacją Google: limity API resetują się o północy czasu pacyficznego
```

## 🎯 Korzyści

### Dla użytkowników
- **Stabilność:** Brak przerw w działaniu z powodu przekroczenia limitów
- **Transparentność:** Pełna widoczność wykorzystania API
- **Proaktywność:** Ostrzeżenia przed problemami

### Dla administratorów
- **Monitoring:** Szczegółowe statystyki i trendy
- **Powiadomienia:** Automatyczne alerty email
- **Diagnostyka:** Łatwe debugowanie problemów z API

### Dla Google
- **Zgodność:** Pełne przestrzeganie limitów API
- **Optymalizacja:** Efektywne wykorzystanie zasobów
- **Partnerstwo:** Odpowiedzialne korzystanie z API

## 🚀 Wdrożenie

### Wymagania
- WordPress 5.0+
- PHP 7.4+
- Aktywne konto Google Search Console
- Skonfigurowane OAuth credentials

### Instalacja
1. Aktualizacja do wersji 1.3.10
2. Automatyczna aktywacja monitora limitów
3. Konfiguracja powiadomień email (opcjonalna)

### Testowanie
- Sprawdzenie dashboardu z monitoringiem
- Weryfikacja logów API
- Test powiadomień email

## 📋 Checklist wdrożenia

- [x] Implementacja klasy `IndexFixer_Quota_Monitor`
- [x] Integracja z `IndexFixer_GSC_API`
- [x] Integracja z `IndexFixer_Widget_Scheduler`
- [x] Dashboard z monitoringiem w czasie rzeczywistym
- [x] System powiadomień email
- [x] Automatyczne resetowanie statystyk
- [x] Historia wykorzystania (7 dni)
- [x] Prognozowanie wyczerpania limitu
- [x] Szczegółowe logowanie
- [x] Dokumentacja użytkownika

## 🔮 Przyszłe rozszerzenia

### Planowane funkcje
- **Inteligentne planowanie:** Optymalizacja czasów sprawdzania
- **Priorytetyzacja:** Ważniejsze URL-e sprawdzane w pierwszej kolejności
- **Analityka:** Raporty wykorzystania API
- **API własne:** Endpoint do sprawdzania statusu limitów

### Możliwe ulepszenia
- **Predykcja:** ML do przewidywania wykorzystania
- **Optymalizacja:** Dynamiczne dostosowanie częstotliwości
- **Integracja:** Webhook do zewnętrznych systemów monitoringu

---

**Wersja:** 1.3.10  
**Data:** 15 stycznia 2025  
**Autor:** Pawel Zinkiewicz  
**Status:** ✅ Zaimplementowane 