# 📊 Smartlead Analytics Suite

Повний набір інструментів для аналізу та візуалізації даних з Smartlead.ai

## 📦 Що входить в пакет

### 1. 🎯 Основні скрипти

| Файл | Опис | Призначення |
|------|------|-------------|
| `smartlead_dashboard.py` | Базовий дашборд | Простий dashboard з основними метриками |
| `smartlead_dashboard_v2.py` | **Розширений дашборд** | ⭐ Рекомендований! Повнофункціональний аналітичний дашборд |
| `smartlead_data_export.py` | Експорт даних | Експорт всіх даних в JSON/CSV |
| `smartlead_config.py` | Конфігурація | Центральне налаштування всіх параметрів |

### 2. 🚀 Запуск

| Файл | ОС | Призначення |
|------|------|-------------|
| `run_smartlead.sh` | Linux/Mac | Автоматичне встановлення та запуск |
| `run_smartlead.bat` | Windows | Автоматичне встановлення та запуск |
| `requirements_smartlead.txt` | Всі | Список залежностей Python |

### 3. 📚 Документація

| Файл | Опис |
|------|------|
| `README_SMARTLEAD.md` | Базова документація |
| `SMARTLEAD_README.md` | Цей файл - повний огляд |

---

## 🚀 Швидкий Старт

### Крок 1: Встановлення

**Linux/Mac:**
```bash
chmod +x run_smartlead.sh
./run_smartlead.sh
```

**Windows:**
```batch
run_smartlead.bat
```

**Або вручну:**
```bash
pip install -r requirements_smartlead.txt
```

### Крок 2: Налаштування API ключа

Відкрийте `smartlead_config.py` і вставте ваш API ключ:

```python
API_KEY = "ваш_api_ключ_тут"
```

**Де знайти API ключ:**
1. Увійдіть в Smartlead.ai
2. Settings → API
3. Скопіюйте ключ

### Крок 3: Запуск дашборду

**Рекомендований (v2 - розширена версія):**
```bash
streamlit run smartlead_dashboard_v2.py
```

**Базова версія:**
```bash
streamlit run smartlead_dashboard.py
```

Дашборд відкриється на `http://localhost:8501`

---

## 📊 Можливості Dashboard V2 (Рекомендований)

### ✨ Основні функції

#### 1. **Загальна Статистика**
- Кількість кампаній (всього/активні/призупинені)
- Відсоток активності
- Ключові метрики в реальному часі

#### 2. **Аналітика Кампаній** (5 вкладок)

**📊 Огляд**
- 🔄 Воронка конверсії (Sent → Open → Click → Reply → Positive)
- 🥧 Розподіл відповідей (позитивні/негативні/відписки)
- 📅 Timeline динаміки відправок

**📈 Детальна статистика**
- 🏆 Топ-5 кампаній по відкриттям
- 🏆 Топ-5 кампаній по відповідям
- 🎯 Scatter plot кореляції (Відкриття vs Відповіді)

**🎯 Advanced Analytics**
- 🌡️ Теплова карта продуктивності
- 📡 Radar chart порівняння залучення
- 📊 Розподіл за статусами
- 📈 Histogram розподілу конверсії

**⚖️ Порівняння**
- Мульти-вибір кампаній
- Порівняння метрик side-by-side
- Детальна таблиця порівняння

**📋 Таблиця**
- Повна таблиця з усіма метриками
- Фільтри (статус, мін. відправлено)
- Сортування
- Gradient підсвічування
- 📥 Експорт в CSV

#### 3. **Email Акаунти**
- Статус кожного акаунту
- Warmup статус
- Денна кількість відправлень
- Використання ліміту (%)
- Середнє використання

#### 4. **Детальні Ліди** (опціонально)
- Перегляд лідів по кампанії
- Інформація: email, ім'я, компанія, статус
- Статистика по статусам лідів
- Експорт лідів в CSV

### 🎛️ Налаштування (Sidebar)

- ⏱️ Авто-оновлення (кожні 5 хв)
- 👥 Показати/сховати ліди
- 📅 Вибір періоду аналізу
- 🔄 Кнопка ручного оновлення
- 📥 Швидкий доступ до експорту
- 🔍 Debug режим

### 📊 Метрики що відслідковуються

| Метрика | Опис |
|---------|------|
| Відправлено | Кількість надісланих emails |
| Відкриття | Унікальні відкриття |
| % Відкриттів | Open rate |
| Кліки | Унікальні кліки |
| % Кліків | Click rate |
| Відповіді | Кількість відповідей |
| % Відповідей | Reply rate |
| Позитивні | Позитивні відповіді |
| Негативні | Негативні відповіді |
| Відписки | Unsubscribe |
| Bounce | Недоставлені |
| Conversion | % позитивних від відправлених |

---

## 📥 Експорт Даних

### Використання Data Export Script

```bash
python smartlead_data_export.py
```

**Що експортується:**

1. **Кампанії**
   - `campaigns_raw.json` - Сирі дані
   - `campaigns.csv` - CSV формат
   - `campaign_statistics_raw.json` - Статистика JSON
   - `campaign_statistics.csv` - Статистика CSV

2. **Ліди**
   - `all_leads_raw.json` - Всі ліди JSON
   - `all_leads.csv` - Всі ліди CSV
   - `leads_campaign_[ID]_[NAME].csv` - По кампаніям

3. **Email акаунти**
   - `email_accounts_raw.json`
   - `email_accounts.csv`

4. **Статистика**
   - `global_stats_raw.json`
   - `export_summary.json`

**Структура експорту:**
```
smartlead_export_YYYYMMDD_HHMMSS/
├── campaigns_raw.json
├── campaigns.csv
├── campaign_statistics_raw.json
├── campaign_statistics.csv
├── all_leads_raw.json
├── all_leads.csv
├── leads_campaign_123_Campaign_Name.csv
├── email_accounts_raw.json
├── email_accounts.csv
├── global_stats_raw.json
├── export_summary.json
└── README.md
```

---

## ⚙️ Конфігурація (smartlead_config.py)

### Основні налаштування:

```python
# API
API_KEY = "ваш_ключ"
BASE_URL = "https://server.smartlead.ai/api/v1"
API_TIMEOUT = 30  # секунди

# Dashboard
PAGE_TITLE = "Smartlead Advanced Dashboard"
PAGE_ICON = "📊"
LAYOUT = "wide"  # або "centered"

# Дані
MAX_LEADS_PER_CAMPAIGN = 100
REQUEST_DELAY = 0.5  # затримка між запитами

# Авто-оновлення
AUTO_REFRESH_INTERVAL = 300  # 5 хвилин
AUTO_REFRESH_BY_DEFAULT = False

# Features
SHOW_EMAIL_ACCOUNTS = True
SHOW_LEADS_BY_DEFAULT = False
DEBUG_MODE = False

# Візуалізація
TOP_CAMPAIGNS_COUNT = 5
COLOR_SCHEMES = {
    "positive": "#00CC96",
    "negative": "#EF553B",
    "neutral": "#FFA15A",
    "primary": "#636EFA",
}
```

### Валідація:

```bash
python smartlead_config.py
```

Перевірить чи коректно налаштовано конфігурацію.

---

## 🔧 API Endpoints

### Використовувані endpoints:

```
GET /campaigns                    - Всі кампанії
GET /campaigns/{id}/analytics     - Статистика кампанії
GET /campaigns/{id}/leads         - Ліди кампанії
GET /email-accounts               - Email акаунти
GET /campaigns/stats              - Глобальна статистика
```

### Rate Limiting:

Скрипти автоматично обробляють:
- 429 (Too Many Requests) - автоматичне повторення
- Затримка між запитами (REQUEST_DELAY)
- Timeout обробка

---

## 💡 Приклади використання

### 1. Аналіз ефективності кампаній

```bash
streamlit run smartlead_dashboard_v2.py
```

1. Відкрийте вкладку "Advanced Analytics"
2. Перегляньте теплову карту
3. Знайдіть кампанії з низькою конверсією
4. Порівняйте з топ-performers в Radar Chart

### 2. Експорт для Excel аналізу

```bash
python smartlead_data_export.py
```

Відкрийте `campaign_statistics.csv` в Excel:
- Створіть pivot tables
- Побудуйте власні графіки
- Поділіться з командою

### 3. Моніторинг email акаунтів

```bash
streamlit run smartlead_dashboard_v2.py
```

1. Прокрутіть до "Email Акаунти"
2. Перевірте статус Warmup
3. Відслідкуйте використання лімітів
4. Ідентифікуйте проблемні акаунти

### 4. Детальний аналіз лідів

```bash
streamlit run smartlead_dashboard_v2.py
```

1. Увімкніть "Показати ліди" в sidebar
2. Виберіть кампанію
3. Перегляньте статуси лідів
4. Експортуйте для CRM

### 5. Порівняння A/B тестів

1. Відкрийте вкладку "Порівняння"
2. Виберіть кампанії A та B
3. Порівняйте метрики
4. Експортуйте результати

---

## 🎯 Best Practices

### Performance

1. **Для великої кількості кампаній (>50):**
   - Збільште `REQUEST_DELAY` до 1 секунди
   - Вимкніть авто-оновлення
   - Використовуйте фільтри в таблиці

2. **Для кращої швидкості:**
   - Не показуйте ліди за замовчуванням
   - Використовуйте data export для bulk аналізу
   - Кешуйте дані локально

### Security

1. **Захист API ключа:**
   - Не коммітьте `smartlead_config.py` в git
   - Додайте в `.gitignore`
   - Використовуйте env variables для CI/CD

2. **Обмеження доступу:**
   - Запускайте dashboard локально
   - Не відкривайте порт 8501 назовні
   - Використовуйте VPN для віддаленого доступу

### Monitoring

1. **Регулярний експорт:**
   ```bash
   # Cron job (щодня о 9:00)
   0 9 * * * cd /path/to/scripts && python smartlead_data_export.py
   ```

2. **Автоматичні звіти:**
   - Експортуйте CSV щодня
   - Зберігайте історичні дані
   - Створюйте week-over-week порівняння

---

## 🐛 Troubleshooting

### Проблема: "Помилка 401/403"

**Рішення:**
```python
# Перевірте API ключ в smartlead_config.py
python smartlead_config.py  # Запустіть валідацію
```

### Проблема: "Rate limit досягнуто"

**Рішення:**
```python
# Збільште затримку в smartlead_config.py
REQUEST_DELAY = 1.0  # було 0.5
```

### Проблема: "Дані не завантажуються"

**Чеклист:**
1. ✅ API ключ правильний?
2. ✅ Інтернет з'єднання активне?
3. ✅ Smartlead.ai доступний?
4. ✅ Є активні кампанії?

```bash
# Увімкніть debug режим
DEBUG_MODE = True  # в smartlead_config.py
```

### Проблема: "Streamlit не знайдено"

**Рішення:**
```bash
pip install streamlit
# Або
python -m pip install -r requirements_smartlead.txt
```

### Проблема: "Повільна завантаження"

**Рішення:**
1. Зменшіть `MAX_LEADS_PER_CAMPAIGN`
2. Відфільтруйте неактивні кампанії
3. Використовуйте data export для bulk операцій

---

## 📊 Візуалізації - Детальний Огляд

### 🔄 Воронка конверсії
- Показує шлях від відправки до позитивної відповіді
- Автоматичний розрахунок conversion на кожному етапі
- Візуальне виявлення вузьких місць

### 🌡️ Теплова карта
- Топ-10 кампаній
- 3 ключові метрики
- Колір-кодування (червоний→жовтий→зелений)
- Швидка ідентифікація проблем

### 📡 Radar Chart
- Порівняння до 5 кампаній одночасно
- 5 вимірів: відкриття, кліки, відповіді, позитивність, утримання
- Візуальне порівняння балансу метрик

### 📊 Scatter Plot
- Кореляція відкриттів та відповідей
- Розмір бульбашки = кількість відправлень
- Колір = статус кампанії
- Виявлення аномалій

---

## 🔗 Корисні Посилання

### Документація Smartlead API
- [API Reference](https://api.smartlead.ai/reference/welcome)
- [Full Documentation](https://helpcenter.smartlead.ai/en/articles/125-full-api-documentation)
- [Automate Reporting](https://helpcenter.smartlead.ai/en/articles/184-automate-reporting-using-smartlead-api)

### Python Libraries
- [Streamlit Docs](https://docs.streamlit.io/)
- [Plotly Docs](https://plotly.com/python/)
- [Pandas Docs](https://pandas.pydata.org/docs/)

---

## 📋 Вимоги

### Python
- Python 3.8+
- pip

### Залежності
```
requests==2.31.0
pandas==2.1.4
streamlit==1.29.0
plotly==5.18.0
```

### ОС
- ✅ Linux
- ✅ macOS
- ✅ Windows 10/11

---

## 🎓 FAQ

**Q: Чи можу я використовувати кілька API ключів?**
A: Так, створіть окремі копії `smartlead_config.py` та вкажіть при запуску.

**Q: Скільки даних зберігається локально?**
A: Дані не зберігаються між сесіями. Використовуйте data export для збереження.

**Q: Чи можу я запустити на сервері?**
A: Так, але налаштуйте правильний host/port:
```bash
streamlit run smartlead_dashboard_v2.py --server.port 8501 --server.address 0.0.0.0
```

**Q: Чи підтримується real-time оновлення?**
A: Так, увімкніть "Авто-оновлення" в sidebar (оновлення кожні 5 хв).

**Q: Можу я змінити інтервал оновлення?**
A: Так, змініть `AUTO_REFRESH_INTERVAL` в `smartlead_config.py`.

**Q: Чи можу я додати власні метрики?**
A: Так, додайте їх в функції `get_campaign_stats()` та оновіть DataFrame.

---

## 📞 Підтримка

### При проблемах з скриптами:
- Перевірте [Troubleshooting](#-troubleshooting)
- Увімкніть DEBUG_MODE
- Перевірте інтернет з'єднання

### При проблемах з API:
- [Smartlead Help Center](https://helpcenter.smartlead.ai)
- Перевірте статус API в Smartlead Settings

---

## 📝 Changelog

### Version 2.0 (Поточна)
- ✨ Додано Advanced Analytics
- 🌡️ Теплова карта продуктивності
- 📡 Radar chart
- 🎯 Conversion tracking
- ⚙️ Централізована конфігурація
- 🔍 Debug режим
- 📊 Покращені візуалізації

### Version 1.0
- 📊 Базовий dashboard
- 📈 Основні метрики
- 📥 CSV експорт

---

## 📄 Ліцензія

Для внутрішнього використання. Дотримуйтесь умов Smartlead.ai API.

---

## ✅ Quick Checklist

Перед першим запуском:

- [ ] Python 3.8+ встановлено
- [ ] Залежності встановлено (`pip install -r requirements_smartlead.txt`)
- [ ] API ключ додано в `smartlead_config.py`
- [ ] Конфігурація валідована (`python smartlead_config.py`)
- [ ] Dashboard запущено (`streamlit run smartlead_dashboard_v2.py`)
- [ ] Дашборд відкрився в браузері
- [ ] Дані завантажилися успішно

---

**🎉 Готово! Ви можете почати аналізувати ваші Smartlead кампанії!**

**💡 Порада:** Почніть з вкладки "Огляд" щоб побачити загальну картину, потім перейдіть до "Advanced Analytics" для глибокого аналізу.

---

*Останнє оновлення: Грудень 2025*
*Створено для максимальної ефективності аналізу Smartlead campaigns*
