# 🎯 IndexFixer v1.1.5 - Dashboard URL-ów z Widgetów

## 🆕 **Nowe funkcje**

### 📊 **Sekcja "URL-e wyświetlane w widgetach"**
- **Lokalizacja:** Na górze dashboardu IndexFixer (zaraz po statystykach)
- **Funkcja:** Pokazuje dokładnie te URL-e które są aktualnie wyświetlane w Twoich widgetach
- **Automatyczne wykrywanie:** Skanuje wszystkie aktywne widgety WordPress i bloki Gutenberg
- **Informacje:** URL, tytuł, status indeksowania, źródło widgetu, ostatnie sprawdzenie

### 🎨 **Subtelny design widgetów**
- **Usunięto:** Czerwone obramowania i szare tła z widgetów
- **Dodano:** Subtelne separatory między URL-ami
- **Neutralny:** Widget używa domyślnych kolorów motywu WordPress
- **Czytelny:** Lepsze odstępy i typografia

### 🔧 **Poprawki tokenów OAuth**
- **Naprawiono:** Błędne wyświetlanie czasu wygaśnięcia tokenów (UTC vs lokalny)
- **Dodano:** Szczegółowe logowanie czasów wygaśnięcia
- **Ulepszono:** Mechanizm proaktywnego odnawiania tokenów

## 📋 **Szczegóły sekcji URL-ów z widgetów**

### **Kolumny tabeli:**
1. **URL** - z linkiem do otworzenia w nowej karcie + przycisk sprawdzania 🔄
2. **Tytuł** - tytuł postu/strony  
3. **Status** - wizualny status z kolorami:
   - ✅ **Zaindeksowane** (zielony)
   - ❌ **Nie zaindeksowane** (czerwony)
   - 🔍 **Odkryte** (żółty)
   - ❓ **Inne** (szary)
4. **Coverage State** - szczegółowy status z Google Search Console
5. **Źródło widgetu** - "WordPress Widget" lub "Blok w: [nazwa postu]"
6. **Ostatnie sprawdzenie** - data i godzina ostatniego sprawdzenia

### **Automatyczne wykrywanie:**
- **WordPress Widgets:** Z opcją "Automatycznie sprawdzaj URL-e co 24h"
- **Bloki Gutenberg:** W postach/stronach z blokiem "IndexFixer - Niezaindeksowane posty"
- **Usuwanie duplikatów:** URL występujący w kilku widgetach pokazuje się raz
- **Warunek wyświetlania:** Sekcja pojawia się tylko gdy są aktywne widgety

## 🔧 **Zmiany techniczne**

### **Nowe funkcje:**
- `IndexFixer_Widget_Scheduler::get_all_widget_urls()` - pobiera URL-e z widgetów
- Logowanie czasu wygaśnięcia tokenów w GMT i czasie lokalnym
- Debug informacje w HTML (jako komentarze)

### **Aktualizacje:**
- Wersja wtyczki: 1.1.2 → 1.1.5
- Ulepszone style CSS dla sekcji widgetów
- Responsywny design dla urządzeń mobilnych

## 📦 **Instalacja**

1. **Pobierz:** `IndexFixer.zip` (71 KB)
2. **Zainstaluj:** WordPress Admin → Wtyczki → Dodaj nową → Wgraj wtyczkę
3. **Aktywuj:** Kliknij "Aktywuj wtyczkę"
4. **Skonfiguruj:** Dodaj widget IndexFixer z włączonym auto-sprawdzaniem

## 🎯 **Jak korzystać z nowej sekcji**

### **Aby sekcja się pojawiła:**
1. Idź do **Wygląd → Widgety**
2. Dodaj widget **"IndexFixer - Niezaindeksowane posty"** do sidebara
3. W ustawieniach widgetu zaznacz: ✅ **"Automatycznie sprawdzaj URL-e co 24h"**
4. Zapisz widget

### **Alternatywnie - użyj bloku:**
1. Edytuj dowolny post/stronę
2. Dodaj blok **"IndexFixer - Niezaindeksowane posty"**
3. W ustawieniach bloku włącz **"Automatyczne sprawdzanie co 24h"**

## 💡 **Korzyści**

- **Przejrzystość:** Widzisz dokładnie co jest w widgetach
- **Monitoring:** Śledzisz postęp indeksowania URL-ów promowanych w widgetach
- **Efektywność:** Sprawdzanie pojedynczych URL-ów jednym klikiem
- **Automatyzacja:** System automatycznie czysty widgety z zaindeksowanych URL-ów

## 🔄 **Migracja z poprzednich wersji**

IndexFixer v1.1.5 jest w pełni kompatybilny z poprzednimi wersjami:
- ✅ Zachowuje wszystkie dane i ustawienia
- ✅ Automatycznie aktualizuje bazę danych
- ✅ Kontynuuje istniejące harmonogramy cronów
- ✅ Kompatybilny z istniejącymi widgetami

## 📈 **System cronów (4 aktywne)**

1. **`indexfixer_check_urls_event`** - główne sprawdzanie URL-ów (24h)
2. **`indexfixer_auto_refresh_tokens`** - odnawianie tokenów Google (30 min)
3. **`indexfixer_daily_stats_save`** - zapisywanie statystyk dziennych (24h o 2:00)
4. **`indexfixer_widget_check`** - sprawdzanie widgetów (10 min/24h)

---

## 📝 **Changelog**

### **v1.1.5** (2025-01-08)
- ➕ **DODANO:** Sekcja URL-ów z widgetów na dashboardzie
- ➕ **DODANO:** Funkcja `get_all_widget_urls()` do pobierania URL-ów z widgetów
- 🎨 **ZMIENIONO:** Subtelny design widgetów (bez czerwonych obramowań)
- 🔧 **NAPRAWIONO:** Wyświetlanie czasu wygaśnięcia tokenów (UTC + lokalny)
- 📱 **ULEPSZONO:** Responsywny design dla urządzeń mobilnych

### **v1.1.2** (2025-01-07)
- ➕ **DODANO:** Cron zapisywania statystyk dziennych
- 🔧 **NAPRAWIONO:** Problem ze starym cronem widgetów
- 🔧 **DODANO:** Czyszczenie nieaktywnych cronów przy aktywacji

---

**🚀 IndexFixer v1.1.5 - Pełna kontrola nad indeksowaniem z przejrzystym interfejsem!** 