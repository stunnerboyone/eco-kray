"""
Конфігураційний файл для Smartlead Dashboard - TEMPLATE
Скопіюйте цей файл як smartlead_config.py та додайте ваш API ключ
"""

# ============================================
# API НАЛАШТУВАННЯ
# ============================================

# Ваш Smartlead API ключ
# Отримайте його в: Settings → API
# ВАЖЛИВО: Скопіюйте цей файл як smartlead_config.py та вставте ваш реальний ключ
API_KEY = "YOUR_API_KEY_HERE"

# Base URL для API (зазвичай не потрібно міняти)
BASE_URL = "https://server.smartlead.ai/api/v1"

# ============================================
# НАЛАШТУВАННЯ ДАШБОРДУ
# ============================================

# Назва сторінки
PAGE_TITLE = "Smartlead Advanced Dashboard"

# Іконка сторінки
PAGE_ICON = "📊"

# Layout режим: "wide" або "centered"
LAYOUT = "wide"

# ============================================
# НАЛАШТУВАННЯ ДАНИХ
# ============================================

# Максимальна кількість лідів для завантаження на кампанію
MAX_LEADS_PER_CAMPAIGN = 100

# Timeout для API запитів (секунди)
API_TIMEOUT = 30

# Затримка між запитами (секунди) - для уникнення rate limiting
REQUEST_DELAY = 0.5

# ============================================
# НАЛАШТУВАННЯ АВТО-ОНОВЛЕННЯ
# ============================================

# Інтервал авто-оновлення (секунди)
# 300 = 5 хвилин, 600 = 10 хвилин
AUTO_REFRESH_INTERVAL = 300

# ============================================
# НАЛАШТУВАННЯ ЕКСПОРТУ
# ============================================

# Формат дати для назв файлів експорту
EXPORT_DATE_FORMAT = "%Y%m%d_%H%M%S"

# Префікс для папок експорту
EXPORT_DIR_PREFIX = "smartlead_export"

# ============================================
# НАЛАШТУВАННЯ ВІЗУАЛІЗАЦІЇ
# ============================================

# Колірна схема для графіків
COLOR_SCHEMES = {
    "positive": "#00CC96",  # Зелений
    "negative": "#EF553B",  # Червоний
    "neutral": "#FFA15A",   # Помаранчевий
    "primary": "#636EFA",   # Синій
}

# Кількість топ кампаній для відображення
TOP_CAMPAIGNS_COUNT = 5

# ============================================
# FEATURES FLAGS
# ============================================

# Показувати розділ email акаунтів
SHOW_EMAIL_ACCOUNTS = True

# Показувати детальні ліди за замовчуванням
SHOW_LEADS_BY_DEFAULT = False

# Увімкнути авто-оновлення за замовчуванням
AUTO_REFRESH_BY_DEFAULT = False

# Показувати debug інформацію
DEBUG_MODE = False

# ============================================
# ДОДАТКОВІ API ENDPOINTS
# ============================================

# Додаткові endpoints для експорту (якщо доступні)
ADDITIONAL_ENDPOINTS = [
    "clients",
    "tags",
    "webhooks",
]

# ============================================
# ВАЛІДАЦІЯ
# ============================================

def validate_config():
    """Перевірка конфігурації"""
    errors = []

    if not API_KEY or API_KEY == "YOUR_API_KEY_HERE":
        errors.append("❌ API_KEY не налаштовано! Додайте ваш ключ в smartlead_config.py")

    if len(API_KEY) < 20:
        errors.append("⚠️ API_KEY виглядає некоректним (занадто короткий)")

    if MAX_LEADS_PER_CAMPAIGN > 1000:
        errors.append("⚠️ MAX_LEADS_PER_CAMPAIGN занадто великий, може бути повільно")

    if API_TIMEOUT < 10:
        errors.append("⚠️ API_TIMEOUT занадто малий, можливі помилки timeout")

    return errors

# ============================================
# HELPER ФУНКЦІЇ
# ============================================

def get_clean_api_key():
    """Отримати очищений API ключ"""
    return "".join(c for c in API_KEY if ord(c) < 128).strip()

def get_headers():
    """Отримати headers для API запитів"""
    return {
        "api-key": get_clean_api_key(),
        "Accept": "application/json",
        "Content-Type": "application/json"
    }

# Перевірка при імпорті
if __name__ == "__main__":
    print("🔍 Перевірка конфігурації...")
    errors = validate_config()

    if errors:
        print("\n⚠️ Знайдено проблеми:")
        for error in errors:
            print(f"  {error}")
    else:
        print("✅ Конфігурація коректна!")
        print(f"   API Key: {get_clean_api_key()[:10]}...")
        print(f"   Base URL: {BASE_URL}")
