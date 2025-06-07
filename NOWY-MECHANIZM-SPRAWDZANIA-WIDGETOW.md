# NOWY MECHANIZM SPRAWDZANIA WIDGETÓW

## Problem
Stary mechanizm sprawdzania widgetów miał następujące problemy:
1. **Ciągłe planowanie/usuwanie** - funkcja `maybe_schedule_check()` była wywoływana przy każdym ładowaniu strony
2. **Brak rzeczywistego mechanizmu** - funkcja `daily_url_check()` istniała ale nie działała poprawnie
3. **Konflikt między widgetami** - widget WordPress i blok widget używały tego samego hook
4. **Brak możliwości testowania** - nie było możliwości przetestowania z krótszym interwałem

## Rozwiązanie

### 🔧 **Nowy plik: `includes/widget-scheduler.php`**

**Ujednolicony mechanizm planowania** z następującymi funkcjami:

#### **Singleton Pattern**
- Jedna instancja zarządzająca całym mechanizmem
- Brak konfliktów między różnymi widgetami

#### **Inteligentne planowanie**
- Sprawdzanie harmonogramu tylko **raz dziennie** (nie przy każdym ładowaniu strony)
- Automatyczne wykrywanie aktywnych widgetów
- Automatyczne wyłączanie gdy brak aktywnych widgetów

#### **Tryb testowy**
- **Produkcyjny:** sprawdzanie co 24 godziny
- **Testowy:** sprawdzanie co 10 minut
- Łatwe przełączanie między trybami

#### **Szczegółowe logowanie**
- Wszystkie działania zapisywane w logach
- Informacje o zaindeksowanych URL-ach
- Status każdego sprawdzanego URL-a

---

## 🎯 **Główne funkcje**

### **1. Automatyczne wykrywanie aktywnych widgetów**
```php
private function are_widgets_active() {
    // Sprawdź widget WordPress
    $widget_instances = get_option('widget_indexfixer_not_indexed', array());
    
    // Sprawdź blok widget w postach/stronach
    $block_usage = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} 
                                   WHERE post_content LIKE '%wp:indexfixer/not-indexed-posts%'");
    
    return ($widget_active || $block_usage > 0);
}
```

### **2. Tryb testowy**
```php
// Włączenie trybu testowego (10 minut)
IndexFixer_Widget_Scheduler::enable_test_mode();

// Wyłączenie trybu testowego (24 godziny)
IndexFixer_Widget_Scheduler::disable_test_mode();

// Sprawdzenie trybu
$is_test = IndexFixer_Widget_Scheduler::is_test_mode();
```

### **3. Ręczne uruchomienie**
```php
// Ręczne sprawdzanie (dla testów)
IndexFixer_Widget_Scheduler::run_manual_check();
```

### **4. Status harmonogramu**
```php
$status = IndexFixer_Widget_Scheduler::get_schedule_status();
// Zwraca:
// - scheduled: czy jest zaplanowane
// - next_run: kiedy następne uruchomienie
// - test_mode: czy tryb testowy
// - interval: interwał sprawdzania
```

---

## 🖥️ **Interfejs użytkownika**

### **Lokalizacja:** `IndexFixer → Zarządzanie`

#### **Funkcje dostępne:**
1. **🧪 Tryb testowy**
   - Włącz/wyłącz sprawdzanie co 10 minut
   - Idealny do testowania mechanizmu

2. **⚡ Ręczne sprawdzanie**
   - Uruchom sprawdzanie natychmiast
   - Niezależnie od harmonogramu

3. **📊 Status harmonogramu**
   - Aktualny status planowania
   - Czas następnego sprawdzania
   - Aktywny tryb (testowy/produkcyjny)

4. **🔄 Odświeżanie statusu**
   - Aktualne informacje o harmonogramie

---

## 🔄 **Jak działa sprawdzanie**

### **1. Wykrywanie URL-i do sprawdzenia**
```php
$urls_to_check = IndexFixer_Database::get_urls_for_checking($limit);
```
- Pobiera URL-e które nie były sprawdzane przez 24h
- Skupia się na `unknown`, `not indexed`, `discovered`

### **2. Sprawdzanie przez API**
```php
$status = $gsc_api->check_url_status($url_data->url);
```
- Używa Google Search Console API
- Rate limiting (2 sekundy między requestami)

### **3. Zapisywanie wyników**
```php
IndexFixer_Database::save_url_status($post_id, $url, $detailed_status);
```
- Ujednolicony format danych
- Zgodny z resztą systemu

### **4. Logowanie rezultatów**
```php
IndexFixer_Logger::log("🎉 ZAINDEKSOWANO: {$url}", 'success');
IndexFixer_Logger::log("⏳ Nadal nie zaindeksowane: {$url}", 'info');
```

---

## 📋 **Usunięte problemy**

### **❌ PRZED (stary mechanizm):**
- `maybe_schedule_check()` wywoływana przy każdym ładowaniu strony
- Ciągłe logowanie "Zaplanowano/Usunięto" w logach
- Brak rzeczywistego sprawdzania URL-i
- Konflikt między widget a blok widget
- Brak możliwości testowania

### **✅ PO (nowy mechanizm):**
- Sprawdzanie harmonogramu **tylko raz dziennie**
- Czyste logi z rzeczywistymi rezultatami
- Faktyczne sprawdzanie URL-i przez API
- Ujednolicony mechanizm dla wszystkich widgetów
- Tryb testowy (10 minut) do szybkiego testowania

---

## 🧪 **Instrukcja testowania**

### **1. Włącz tryb testowy**
```
IndexFixer → Zarządzanie → Włącz tryb testowy (10 min)
```

### **2. Sprawdź logi**
```
IndexFixer → Dashboard → Logi
```
Szukaj wpisów:
- `🧪 TRYB TESTOWY WŁĄCZONY`
- `🤖 ROZPOCZĘCIE AUTOMATYCZNEGO SPRAWDZANIA WIDGETÓW`
- `🎉 ZAINDEKSOWANO: [URL]`

### **3. Ręczne sprawdzanie**
```
IndexFixer → Zarządzanie → Uruchom sprawdzanie teraz
```

### **4. Wyłącz tryb testowy**
```
IndexFixer → Zarządzanie → Wyłącz tryb testowy (24h)
```

---

## 🔧 **Pliki zmodyfikowane**

1. **`includes/widget-scheduler.php`** - NOWY plik z całym mechanizmem
2. **`includes/widget.php`** - usunięto stary mechanizm planowania
3. **`includes/block-widget.php`** - usunięto stary mechanizm planowania
4. **`admin/dashboard.php`** - dodano AJAX funkcje zarządzania
5. **`templates/widget-settings.php`** - dodano interfejs użytkownika
6. **`indexfixer.php`** - dodano include nowego pliku

---

## ⚠️ **Ważne uwagi**

1. **Stary hook `indexfixer_widget_daily_check` został zastąpiony** przez `indexfixer_widget_check`
2. **Tryb testowy jest zapisywany w bazie** - przetrwa restart serwera
3. **Automatyczne wyłączanie** - jeśli nie ma aktywnych widgetów, sprawdzanie się wyłącza
4. **Rate limiting** - 2 sekundy między requestami do API Google 