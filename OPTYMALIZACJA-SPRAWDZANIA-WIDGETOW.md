# OPTYMALIZACJA SPRAWDZANIA WIDGETÓW

## Problem
System sprawdzał **WSZYSTKIE** niezaindeksowane URL-e z bazy, a nie tylko te które są wyświetlane w widgetach, co marnowało zasoby API Google.

**Przykład:**
- Widget wyświetla 5 URL-i
- W bazie jest 500 niezaindeksowanych URL-i  
- System sprawdzał wszystkie 500 zamiast tylko tych 5 z widgetu

## Rozwiązanie

### 🎯 **Nowa funkcja: `get_actual_widget_urls()`**

System teraz sprawdza **TYLKO** te URL-e które są faktycznie wyświetlane w aktywnych widgetach.

#### **Jak działa:**

1. **Skanuje widget WordPress**
   ```php
   $widget_instances = get_option('widget_indexfixer_not_indexed', array());
   foreach ($widget_instances as $instance) {
       if (!empty($instance['auto_check'])) {
           $count = $instance['count']; // np. 5 URL-i
           $widget_urls = IndexFixer_Database::get_urls_by_status('not_indexed', $count);
       }
   }
   ```

2. **Skanuje blok widget w postach/stronach**
   ```php
   $posts_with_blocks = $wpdb->get_results(
       "SELECT post_content FROM {$wpdb->posts} 
        WHERE post_content LIKE '%wp:indexfixer/not-indexed-posts%'"
   );
   ```

3. **Parsuje parametry bloku**
   ```php
   preg_match('/wp:indexfixer\/not-indexed-posts\s*({[^}]*})?/', $post_content, $matches);
   $block_attrs = json_decode($matches[1], true);
   $count = $block_attrs['count']; // np. 10 URL-i
   ```

4. **Usuwa duplikaty**
   ```php
   $all_widget_urls[$url_data->url] = $url_data; // URL jako klucz
   ```

5. **Filtruje tylko te wymagające sprawdzenia**
   ```php
   if (empty($url_data->last_checked) || 
       strtotime($url_data->last_checked) < (time() - 24 * 3600)) {
       $urls_to_check[] = $url_data;
   }
   ```

---

## 📊 **Porównanie PRZED vs PO**

### **❌ PRZED (nieoptymalne):**
```
Widget wyświetla: 5 URL-i
System sprawdza: 500 URL-i (wszystkie niezaindeksowane)
Zużycie API: 500 requestów
Czas: ~16 minut (500 × 2s rate limiting)
```

### **✅ PO (zoptymalizowane):**
```
Widget wyświetla: 5 URL-i  
System sprawdza: 5 URL-i (tylko z widgetu)
Zużycie API: 5 requestów
Czas: ~10 sekund (5 × 2s rate limiting)
```

**🚀 Oszczędność: 99% mniej requestów API!**

---

## 🔧 **Implementacja**

### **1. Nowa funkcja w `includes/database.php`:**
```php
public static function get_widget_urls_for_checking($limit = 10) {
    // Pobiera tylko URL-e które są w widgetach i wymagają sprawdzenia
    return $wpdb->get_results("SELECT ... WHERE verdict = 'NEUTRAL' AND coverage_state LIKE '%not indexed%'");
}
```

### **2. Ulepszona funkcja w `includes/widget-scheduler.php`:**
```php
private function get_actual_widget_urls($max_limit = 10) {
    // 1. Skanuje wszystkie aktywne widgety
    // 2. Pobiera dokładnie te URL-e które są wyświetlane
    // 3. Usuwa duplikaty
    // 4. Filtruje tylko te wymagające sprawdzenia
}
```

### **3. Zmiana w scheduler:**
```php
// PRZED:
$urls_to_check = IndexFixer_Database::get_urls_for_checking($limit);

// PO:
$urls_to_check = $this->get_actual_widget_urls($limit);
```

---

## 📋 **Szczegółowe logowanie**

System teraz loguje dokładnie ile URL-i znalazł:

```
🎯 Znaleziono 15 unikalnych URL-ów w widgetach, 3 wymaga sprawdzenia
🔍 Sprawdzam: https://example.com/post1
🔍 Sprawdzam: https://example.com/post2  
🔍 Sprawdzam: https://example.com/post3
🏁 ZAKOŃCZENIE SPRAWDZANIA WIDGETÓW:
   • Sprawdzono: 3 URL-ów
   • Nowo zaindeksowane: 1 URL-ów
```

---

## 🎯 **Korzyści**

1. **💰 Oszczędność API** - 99% mniej requestów do Google
2. **⚡ Szybkość** - sprawdzanie w sekundach zamiast minut  
3. **🎯 Precyzja** - sprawdza tylko to co użytkownik widzi
4. **🔄 Inteligencja** - automatycznie wykrywa wszystkie widgety
5. **📊 Transparentność** - dokładne logowanie co się dzieje

---

## ⚠️ **Ważne uwagi**

1. **Duplikaty są usuwane** - jeśli ten sam URL jest w kilku widgetach, sprawdzany jest tylko raz
2. **Respektuje limity** - każdy widget może mieć inną liczbę URL-i (count)
3. **24h cooldown** - URL nie jest sprawdzany ponownie przez 24h
4. **Automatyczne wykrywanie** - znajduje widgety w sidebar i bloki w postach/stronach

---

## 🧪 **Testowanie**

Po tej optymalizacji powinieneś zobaczyć w logach:

```
🎯 Znaleziono X unikalnych URL-ów w widgetach, Y wymaga sprawdzenia
```

Gdzie:
- **X** = suma URL-i ze wszystkich aktywnych widgetów (bez duplikatów)
- **Y** = ile z nich nie było sprawdzanych przez 24h

**Jeśli Y = 0, to znaczy że wszystkie URL-e z widgetów były niedawno sprawdzone.** ✅ 