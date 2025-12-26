"""
Smartlead Data Export Script
Експортує ВСІ можливі дані з Smartlead API у JSON та CSV формати
"""

import requests
import json
import pandas as pd
from datetime import datetime
import os
import time

# --- КОНФІГУРАЦІЯ ---
RAW_API_KEY = "782e6baa-a185-4779-9a05-799e4c21a65d_zduejn7"
API_KEY = "".join(c for c in RAW_API_KEY if ord(c) < 128).strip()
BASE_URL = "https://server.smartlead.ai/api/v1"

# Папка для експорту
EXPORT_DIR = f"smartlead_export_{datetime.now().strftime('%Y%m%d_%H%M%S')}"

def api_request(endpoint, method="GET", params=None, data=None):
    """Універсальна функція для API запитів"""
    url = f"{BASE_URL}/{endpoint}"
    headers = {
        "api-key": API_KEY,
        "Accept": "application/json",
        "Content-Type": "application/json"
    }

    try:
        if method == "GET":
            response = requests.get(url, headers=headers, params=params, timeout=30)
        elif method == "POST":
            response = requests.post(url, headers=headers, json=data, timeout=30)

        if response.status_code == 200:
            return response.json()
        else:
            print(f"❌ Помилка {response.status_code} для {endpoint}: {response.text}")
            return None
    except Exception as e:
        print(f"❌ Помилка з'єднання до {endpoint}: {e}")
        return None

def save_json(data, filename):
    """Зберегти дані в JSON"""
    filepath = os.path.join(EXPORT_DIR, filename)
    with open(filepath, 'w', encoding='utf-8') as f:
        json.dump(data, f, ensure_ascii=False, indent=2)
    print(f"✅ Збережено: {filepath}")

def save_csv(data, filename):
    """Зберегти дані в CSV"""
    if data and isinstance(data, list) and len(data) > 0:
        filepath = os.path.join(EXPORT_DIR, filename)
        df = pd.DataFrame(data)
        df.to_csv(filepath, index=False, encoding='utf-8')
        print(f"✅ Збережено: {filepath}")

def export_campaigns():
    """Експорт всіх кампаній"""
    print("\n📊 Експорт кампаній...")
    campaigns = api_request("campaigns")

    if campaigns:
        save_json(campaigns, "campaigns_raw.json")
        save_csv(campaigns, "campaigns.csv")

        # Експорт статистики для кожної кампанії
        print("\n📈 Експорт статистики кампаній...")
        all_stats = []

        for i, camp in enumerate(campaigns):
            c_id = camp.get('id')
            c_name = camp.get('name', 'N/A')
            print(f"  [{i+1}/{len(campaigns)}] {c_name}...")

            stats = api_request(f"campaigns/{c_id}/analytics")
            if stats:
                stats['campaign_id'] = c_id
                stats['campaign_name'] = c_name
                all_stats.append(stats)

            time.sleep(0.5)  # Rate limiting

        if all_stats:
            save_json(all_stats, "campaign_statistics_raw.json")
            save_csv(all_stats, "campaign_statistics.csv")

        return campaigns
    return []

def export_leads(campaigns):
    """Експорт лідів для всіх кампаній"""
    print("\n👥 Експорт лідів...")

    all_leads = []
    leads_by_campaign = {}

    for i, camp in enumerate(campaigns):
        c_id = camp.get('id')
        c_name = camp.get('name', 'N/A')
        print(f"  [{i+1}/{len(campaigns)}] {c_name}...")

        # Отримуємо ліди частинами (pagination)
        offset = 0
        limit = 100
        campaign_leads = []

        while True:
            leads = api_request(f"campaigns/{c_id}/leads", params={"offset": offset, "limit": limit})

            if not leads or not isinstance(leads, list) or len(leads) == 0:
                break

            for lead in leads:
                lead['campaign_id'] = c_id
                lead['campaign_name'] = c_name
                campaign_leads.append(lead)
                all_leads.append(lead)

            if len(leads) < limit:
                break

            offset += limit
            time.sleep(0.3)

        if campaign_leads:
            leads_by_campaign[c_id] = campaign_leads
            # Зберігаємо ліди кожної кампанії окремо
            safe_name = "".join(c for c in c_name if c.isalnum() or c in (' ', '_')).strip()
            save_csv(campaign_leads, f"leads_campaign_{c_id}_{safe_name}.csv")

        time.sleep(0.5)

    if all_leads:
        save_json(all_leads, "all_leads_raw.json")
        save_csv(all_leads, "all_leads.csv")
        print(f"  📊 Всього лідів експортовано: {len(all_leads)}")

def export_email_accounts():
    """Експорт email акаунтів"""
    print("\n📧 Експорт email акаунтів...")
    accounts = api_request("email-accounts")

    if accounts:
        save_json(accounts, "email_accounts_raw.json")
        save_csv(accounts, "email_accounts.csv")
        print(f"  📊 Всього акаунтів: {len(accounts)}")

def export_global_stats():
    """Експорт глобальної статистики"""
    print("\n🌍 Експорт глобальної статистики...")
    stats = api_request("campaigns/stats")

    if stats:
        save_json(stats, "global_stats_raw.json")

def export_additional_endpoints():
    """Експорт додаткових ендпоінтів"""
    print("\n🔍 Експорт додаткових даних...")

    # Спробуємо отримати різні типи даних
    endpoints = [
        ("clients", "clients.json"),
        ("tags", "tags.json"),
        ("webhooks", "webhooks.json"),
    ]

    for endpoint, filename in endpoints:
        print(f"  Пробую {endpoint}...")
        data = api_request(endpoint)
        if data:
            save_json(data, filename)

def create_summary_report(campaigns):
    """Створити підсумковий звіт"""
    print("\n📋 Створення підсумкового звіту...")

    summary = {
        "export_date": datetime.now().isoformat(),
        "total_campaigns": len(campaigns),
        "active_campaigns": len([c for c in campaigns if c.get('status') == 'ACTIVE']),
        "api_key_used": API_KEY[:10] + "...",
        "export_directory": EXPORT_DIR
    }

    save_json(summary, "export_summary.json")

    # Створити README для експорту
    readme_content = f"""# Smartlead Data Export

## Інформація про експорт

- **Дата експорту:** {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}
- **Всього кампаній:** {len(campaigns)}
- **Активних кампаній:** {len([c for c in campaigns if c.get('status') == 'ACTIVE'])}

## Структура файлів

### Кампанії
- `campaigns_raw.json` - Сирі дані всіх кампаній (JSON)
- `campaigns.csv` - Список кампаній (CSV)
- `campaign_statistics_raw.json` - Статистика всіх кампаній (JSON)
- `campaign_statistics.csv` - Статистика всіх кампаній (CSV)

### Ліди
- `all_leads_raw.json` - Всі ліди (JSON)
- `all_leads.csv` - Всі ліди (CSV)
- `leads_campaign_[ID]_[NAME].csv` - Ліди по кожній кампанії окремо

### Email акаунти
- `email_accounts_raw.json` - Всі email акаунти (JSON)
- `email_accounts.csv` - Email акаунти (CSV)

### Статистика
- `global_stats_raw.json` - Глобальна статистика
- `export_summary.json` - Підсумок експорту

## Використання

Ці дані можна використовувати для:
- Глибокого аналізу в Excel/Google Sheets
- Імпорту в CRM системи
- Створення custom звітів
- Machine learning аналізу
- Backup даних

## Формати

- **JSON** - Повна структура даних для програмного використання
- **CSV** - Табличний формат для аналізу в Excel/Google Sheets
"""

    readme_path = os.path.join(EXPORT_DIR, "README.md")
    with open(readme_path, 'w', encoding='utf-8') as f:
        f.write(readme_content)

    print(f"✅ Створено README: {readme_path}")

def main():
    """Головна функція експорту"""
    print("=" * 60)
    print("🚀 SMARTLEAD DATA EXPORT TOOL")
    print("=" * 60)
    print(f"📅 Початок експорту: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")

    # Створити директорію для експорту
    os.makedirs(EXPORT_DIR, exist_ok=True)
    print(f"📁 Директорія експорту: {EXPORT_DIR}")

    try:
        # 1. Експорт кампаній
        campaigns = export_campaigns()

        # 2. Експорт лідів
        if campaigns:
            export_leads(campaigns)

        # 3. Експорт email акаунтів
        export_email_accounts()

        # 4. Експорт глобальної статистики
        export_global_stats()

        # 5. Додаткові ендпоінти
        export_additional_endpoints()

        # 6. Створити підсумковий звіт
        create_summary_report(campaigns)

        print("\n" + "=" * 60)
        print("✅ ЕКСПОРТ ЗАВЕРШЕНО УСПІШНО!")
        print("=" * 60)
        print(f"📁 Всі файли збережено в: {EXPORT_DIR}")
        print(f"📊 Час завершення: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")

    except Exception as e:
        print(f"\n❌ КРИТИЧНА ПОМИЛКА: {e}")
        import traceback
        traceback.print_exc()

if __name__ == "__main__":
    main()
