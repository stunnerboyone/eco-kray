# 📁 Smartlead Analytics Suite - Список Файлів

## ✅ Створено 11 файлів

### 🎯 Головні скрипти (4 файли)

| Файл | Розмір | Призначення |
|------|--------|-------------|
| `smartlead_dashboard_v2.py` | 24KB | ⭐ **ГОЛОВНИЙ ДАШБОРД** - Розширена версія з усіма features |
| `smartlead_dashboard.py` | 16KB | Базова версія дашборду |
| `smartlead_data_export.py` | 9.8KB | Експорт всіх даних в JSON/CSV |
| `smartlead_config.py` | 5.0KB | **КОНФІГУРАЦІЯ** - Налаштування та API ключ |

### 🚀 Скрипти запуску (3 файли)

| Файл | Розмір | ОС |
|------|--------|-----|
| `run_smartlead.sh` | 1.7KB | Linux/Mac (виконуваний) |
| `run_smartlead.bat` | 1.4KB | Windows |
| `requirements_smartlead.txt` | 64B | Залежності Python |

### 📚 Документація (3 файли)

| Файл | Розмір | Зміст |
|------|--------|-------|
| `SMARTLEAD_README.md` | 18KB | 📖 **ПОВНА ДОКУМЕНТАЦІЯ** - Все про систему |
| `README_SMARTLEAD.md` | 7.4KB | Базова документація |
| `QUICKSTART_SMARTLEAD.md` | 3.0KB | ⚡ **ШВИДКИЙ СТАРТ** - 3 хвилини до запуску |

### ⚙️ Допоміжні файли (1 файл)

| Файл | Розмір | Призначення |
|------|--------|-------------|
| `smartlead_config.template.py` | 5.1KB | Template конфігурації (для git) |

---

## 🎯 Рекомендований порядок використання

### 1️⃣ Перший запуск

```bash
# Крок 1: Прочитайте швидкий старт
cat QUICKSTART_SMARTLEAD.md

# Крок 2: Встановіть залежності
pip install -r requirements_smartlead.txt

# Крок 3: Налаштуйте API ключ
nano smartlead_config.py  # Вставте ваш ключ

# Крок 4: Запустіть дашборд
streamlit run smartlead_dashboard_v2.py
```

### 2️⃣ Щоденне використання

```bash
# Запуск дашборду
streamlit run smartlead_dashboard_v2.py

# АБО використовуйте скрипт
./run_smartlead.sh  # Linux/Mac
# або
run_smartlead.bat   # Windows
```

### 3️⃣ Експорт даних

```bash
# Експорт всіх даних
python smartlead_data_export.py
```

---

## 📊 Можливості Dashboard V2

### Основні функції:
- ✅ Загальна статистика кампаній
- ✅ Воронка конверсії
- ✅ Теплова карта продуктивності
- ✅ Radar chart порівняння
- ✅ Топ кампанії
- ✅ Кореляція метрик
- ✅ Email акаунти моніторинг
- ✅ Детальні ліди
- ✅ Авто-оновлення
- ✅ Експорт в CSV

### 5 вкладок аналітики:
1. 📊 **Огляд** - загальна картина
2. 📈 **Детальна статистика** - топ кампанії
3. 🎯 **Advanced Analytics** - теплові карти та radar
4. ⚖️ **Порівняння** - side-by-side метрики
5. 📋 **Таблиця** - повні дані з фільтрами

---

## 🔑 Налаштування API ключа

### Варіант 1: Безпосередньо в конфігурації (рекомендовано)

```python
# Відкрийте: smartlead_config.py
API_KEY = "ваш_реальний_ключ_тут"
```

### Варіант 2: Через environment variable (для production)

```bash
export SMARTLEAD_API_KEY="ваш_ключ"
```

Потім змініть в `smartlead_config.py`:
```python
import os
API_KEY = os.getenv('SMARTLEAD_API_KEY', 'default_key')
```

---

## 📥 Що експортується

### Data Export Script створює:

```
smartlead_export_YYYYMMDD_HHMMSS/
├── campaigns_raw.json              # Всі кампанії (JSON)
├── campaigns.csv                   # Всі кампанії (CSV)
├── campaign_statistics_raw.json    # Статистика (JSON)
├── campaign_statistics.csv         # Статистика (CSV)
├── all_leads_raw.json             # Всі ліди (JSON)
├── all_leads.csv                  # Всі ліди (CSV)
├── leads_campaign_*.csv           # Ліди по кампаніям
├── email_accounts_raw.json        # Email акаунти (JSON)
├── email_accounts.csv             # Email акаунти (CSV)
├── global_stats_raw.json          # Глобальна статистика
├── export_summary.json            # Підсумок експорту
└── README.md                      # Опис експорту
```

---

## 🔒 Безпека

### ВАЖЛИВО: Не коммітьте API ключ!

Додайте в `.gitignore`:
```
# Smartlead
smartlead_config.py
smartlead_export_*/
*.csv
```

Використовуйте template:
```bash
# Для git зберігайте
smartlead_config.template.py  # БЕЗ API ключа

# Локально використовуйте
smartlead_config.py  # З API ключем (в .gitignore)
```

---

## 📊 Метрики та Endpoints

### API Endpoints:
- `GET /campaigns` - Список кампаній
- `GET /campaigns/{id}/analytics` - Статистика кампанії
- `GET /campaigns/{id}/leads` - Ліди кампанії
- `GET /email-accounts` - Email акаунти
- `GET /campaigns/stats` - Глобальна статистика

### Відслідковувані метрики:
- Відправлено (total_leads_contacted)
- Відкриття (total_unique_open) + %
- Кліки (total_unique_click) + %
- Відповіді (total_replied) + %
- Позитивні (total_positive_reply)
- Негативні (total_negative_reply)
- Відписки (total_unsubscribe)
- Bounce (total_bounced)
- **Conversion** (позитивні / відправлено)

---

## 🎨 Візуалізації

1. **🔄 Воронка конверсії** - 5 етапів від sent до positive
2. **🥧 Pie Charts** - Розподіл відповідей
3. **📊 Bar Charts** - Топ кампанії
4. **🎯 Scatter Plots** - Кореляції
5. **🌡️ Heatmaps** - Теплова карта продуктивності
6. **📡 Radar Charts** - Багатовимірне порівняння
7. **📈 Histograms** - Розподіл conversion
8. **📅 Timeline** - Динаміка за часом

---

## 🛠️ Технології

- **Backend:** Python 3.8+
- **Dashboard:** Streamlit 1.29.0
- **Візуалізація:** Plotly 5.18.0
- **Дані:** Pandas 2.1.4
- **HTTP:** Requests 2.31.0

---

## 📖 Документація

### Читайте першим:
1. ⚡ `QUICKSTART_SMARTLEAD.md` - 3 хв до запуску
2. 📖 `SMARTLEAD_README.md` - Повний гайд
3. 🔧 `smartlead_config.py` - Коментарі в коді

### API документація:
- [Smartlead API Docs](https://api.smartlead.ai/reference/welcome)
- [Help Center](https://helpcenter.smartlead.ai)

---

## ⚡ Швидкі команди

```bash
# Перевірка конфігурації
python smartlead_config.py

# Запуск основного дашборду
streamlit run smartlead_dashboard_v2.py

# Експорт даних
python smartlead_data_export.py

# Встановлення залежностей
pip install -r requirements_smartlead.txt

# Linux/Mac швидкий старт
./run_smartlead.sh
```

---

## 🎯 Use Cases

### 1. Щоденний моніторинг
```bash
streamlit run smartlead_dashboard_v2.py
# Увімкнути "Авто-оновлення" в sidebar
```

### 2. Тижневі звіти
```bash
python smartlead_data_export.py
# Відкрити campaign_statistics.csv в Excel
```

### 3. A/B тестування
- Вкладка "Порівняння"
- Виберіть тестові кампанії
- Порівняйте метрики

### 4. Оптимізація
- Вкладка "Advanced Analytics"
- Теплова карта → знайти слабкі місця
- Radar chart → порівняти з top performers

---

## 🎊 Готово до використання!

**Всього 3 кроки:**
1. ✅ Встановіть залежності
2. ✅ Додайте API ключ
3. ✅ Запустіть дашборд

**Починайте з:**
```bash
cat QUICKSTART_SMARTLEAD.md
```

---

## 📞 Підтримка

- 📖 Документація: `SMARTLEAD_README.md`
- ⚡ Швидкий старт: `QUICKSTART_SMARTLEAD.md`
- 🐛 Troubleshooting: розділ в `SMARTLEAD_README.md`
- 🔧 Конфігурація: коментарі в `smartlead_config.py`

---

**🎉 Успішного аналізу кампаній!**

*Створено: Грудень 2025*
*Всього файлів: 11*
*Загальний розмір: ~90KB*
