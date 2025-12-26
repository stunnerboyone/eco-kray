# 🎯 ПОЧНІТЬ ЗВІДСИ!

## Вітаємо в Smartlead Analytics Suite! 🎉

**Ви отримали повний набір інструментів для аналізу Smartlead кампаній.**

---

## ⚡ 3-хвилинний Старт

### Крок 1: Встановіть залежності

**Linux/Mac:**
```bash
./run_smartlead.sh
```

**Windows:**
```
Подвійний клік → run_smartlead.bat
```

**Вручну:**
```bash
pip install -r requirements_smartlead.txt
```

---

### Крок 2: Налаштуйте API ключ

1. Відкрийте файл: **`smartlead_config.py`**
2. Знайдіть рядок:
   ```python
   API_KEY = "YOUR_API_KEY_HERE"
   ```
3. Замініть на ваш ключ з Smartlead.ai

**💡 Де взяти ключ:**
- Smartlead.ai → Settings → API → Copy Key

---

### Крок 3: Перевірте установку

```bash
python check_installation.py
```

Має показати всі ✅

---

### Крок 4: Запустіть!

```bash
streamlit run smartlead_dashboard_v2.py
```

Відкриється → `http://localhost:8501`

---

## 🎊 Готово!

Тепер ви можете:
- ✅ Переглядати статистику кампаній
- ✅ Аналізувати воронку конверсії
- ✅ Порівнювати результати
- ✅ Експортувати дані
- ✅ Моніторити email акаунти

---

## 📚 Що далі читати?

### Залежно від вашого рівня:

#### 🟢 Новачок
→ Читайте: **`QUICKSTART_SMARTLEAD.md`**
- Детальний швидкий старт
- Пояснення кожного кроку
- Troubleshooting

#### 🟡 Досвідчений
→ Читайте: **`SMARTLEAD_README.md`**
- Повна документація
- Всі можливості
- Advanced features
- Best practices

#### 🔵 Експерт
→ Читайте: **`PROJECT_STRUCTURE.md`**
- Архітектура проекту
- API flow
- Кастомізація
- Розширення функціоналу

---

## 📂 Навігація по файлам

| Файл | Для чого |
|------|----------|
| **START_HERE.md** | 👈 Цей файл - початок |
| **QUICKSTART_SMARTLEAD.md** | ⚡ Швидкий старт (детально) |
| **SMARTLEAD_README.md** | 📖 Повний мануал |
| **SMARTLEAD_FILES_SUMMARY.md** | 📋 Список всіх файлів |
| **PROJECT_STRUCTURE.md** | 🏗️ Структура проекту |
| **smartlead_config.py** | ⚙️ Ваші налаштування |
| **check_installation.py** | 🔍 Перевірка установки |

---

## 🎯 Які скрипти використовувати?

### Для Візуалізації:
```
🌟 smartlead_dashboard_v2.py  ← РЕКОМЕНДОВАНО
   (розширена версія з усіма features)

📊 smartlead_dashboard.py
   (базова версія)
```

### Для Експорту Даних:
```
📥 smartlead_data_export.py
   (експорт у JSON/CSV)
```

### Для Налаштувань:
```
⚙️ smartlead_config.py
   (API ключ та параметри)
```

---

## 🔥 Популярні Команди

```bash
# Запуск основного дашборду (РЕКОМЕНДОВАНО)
streamlit run smartlead_dashboard_v2.py

# Перевірка що все ОК
python check_installation.py

# Експорт всіх даних
python smartlead_data_export.py

# Валідація конфігурації
python smartlead_config.py
```

---

## 💡 Швидкі Поради

### ✨ Кращі практики:

1. **Перший запуск:**
   - Використовуйте `check_installation.py` перед стартом
   - Перевірте що API ключ працює

2. **Щоденна робота:**
   - Запускайте `smartlead_dashboard_v2.py`
   - Увімкніть авто-оновлення в sidebar

3. **Звіти:**
   - Експортуйте дані з вкладки "Таблиця"
   - Або використовуйте `smartlead_data_export.py`

4. **Безпека:**
   - НЕ коммітьте `smartlead_config.py` в git
   - Використовуйте `.gitignore_smartlead`

---

## 🎨 Що є в Dashboard V2?

### 5 Вкладок:

```
📊 Огляд
   • Воронка конверсії
   • Розподіл відповідей
   • Timeline динаміки

📈 Детальна статистика
   • Топ-5 кампаній
   • Scatter plots
   • Кореляції

🎯 Advanced Analytics
   • Теплова карта
   • Radar chart
   • Розподіл conversion

⚖️ Порівняння
   • Multi-select кампаній
   • Side-by-side графіки
   • Детальні таблиці

📋 Таблиця
   • Всі дані
   • Фільтри і сортування
   • CSV експорт
```

---

## ❓ Проблеми?

### Швидкі рішення:

| Проблема | Рішення |
|----------|---------|
| ❌ Module not found | `pip install -r requirements_smartlead.txt` |
| ❌ API key error | Перевірте ключ в `smartlead_config.py` |
| ❌ No data | Перевірте інтернет + API key |
| ⏱️ Timeout | Збільште `API_TIMEOUT` в config |
| 🐌 Повільно | Зменшіть `MAX_LEADS_PER_CAMPAIGN` |

### Детальний troubleshooting:
→ `SMARTLEAD_README.md` → розділ "Troubleshooting"

---

## 🚀 Готові почати?

### Ваш чекліст:

- [ ] ✅ Python 3.8+ встановлено
- [ ] ✅ Залежності встановлено
- [ ] ✅ API ключ додано в `smartlead_config.py`
- [ ] ✅ `check_installation.py` показав всі ✅
- [ ] ✅ Dashboard запустився

### Якщо всі ✅:

```bash
streamlit run smartlead_dashboard_v2.py
```

---

## 📞 Потрібна допомога?

### Читайте документацію:

1. **QUICKSTART_SMARTLEAD.md** - швидкий старт
2. **SMARTLEAD_README.md** - повний мануал
3. **PROJECT_STRUCTURE.md** - структура проекту

### Корисні посилання:

- [Smartlead API Docs](https://api.smartlead.ai/reference/welcome)
- [Streamlit Docs](https://docs.streamlit.io/)
- [Plotly Docs](https://plotly.com/python/)

---

## 🎁 Бонус: Automation

### Щоденний експорт (Linux/Mac):

```bash
# Додайте в crontab
0 9 * * * cd /path/to/scripts && python smartlead_data_export.py
```

### Постійний моніторинг:

1. Запустіть dashboard
2. Увімкніть "Авто-оновлення" в sidebar
3. Тримайте відкритим в окремій вкладці

---

## 🎊 Успіхів!

**Ви готові до професійного аналізу Smartlead кампаній!**

### Наступний крок:
```bash
python check_installation.py
```

Якщо все ✅ → запускайте dashboard! 🚀

---

**📧 Створено для максимальної ефективності аналізу**

*Версія 2.0 | Грудень 2025*

---

## 🗺️ Швидка Карта

```
START_HERE.md (ВИ ТУТ)
    │
    ├─→ Швидкий старт → QUICKSTART_SMARTLEAD.md
    │
    ├─→ Повний мануал → SMARTLEAD_README.md
    │
    ├─→ Структура → PROJECT_STRUCTURE.md
    │
    └─→ Файли → SMARTLEAD_FILES_SUMMARY.md
```

**💡 Порада:** Збережіть цей файл у закладки - він ваш навігатор!
