#!/usr/bin/env python3
"""
Smartlead Dashboard - Installation Checker
Перевіряє чи все готово для запуску
"""

import sys
import os

def check_python_version():
    """Перевірка версії Python"""
    version = sys.version_info
    print(f"🐍 Python: {version.major}.{version.minor}.{version.micro}")

    if version.major >= 3 and version.minor >= 8:
        print("   ✅ Версія Python підходить (3.8+)")
        return True
    else:
        print("   ❌ Потрібен Python 3.8 або новіший")
        return False

def check_modules():
    """Перевірка встановлених модулів"""
    required_modules = {
        'requests': '2.31.0',
        'pandas': '2.1.4',
        'streamlit': '1.29.0',
        'plotly': '5.18.0'
    }

    all_ok = True
    print("\n📦 Модулі Python:")

    for module_name, required_version in required_modules.items():
        try:
            module = __import__(module_name)
            version = getattr(module, '__version__', 'unknown')
            print(f"   ✅ {module_name}: {version}")
        except ImportError:
            print(f"   ❌ {module_name}: НЕ ВСТАНОВЛЕНО")
            all_ok = False

    if not all_ok:
        print("\n   💡 Запустіть: pip install -r requirements_smartlead.txt")

    return all_ok

def check_config_file():
    """Перевірка конфігураційного файлу"""
    print("\n⚙️ Конфігурація:")

    if os.path.exists('smartlead_config.py'):
        print("   ✅ smartlead_config.py існує")

        try:
            import smartlead_config
            api_key = smartlead_config.API_KEY

            if api_key == "YOUR_API_KEY_HERE":
                print("   ⚠️ API_KEY не налаштовано!")
                print("      Відкрийте smartlead_config.py та додайте ваш ключ")
                return False
            elif len(api_key) < 20:
                print("   ⚠️ API_KEY виглядає некоректним (занадто короткий)")
                return False
            else:
                print(f"   ✅ API_KEY налаштовано ({api_key[:10]}...)")
                return True

        except Exception as e:
            print(f"   ❌ Помилка читання конфігурації: {e}")
            return False
    else:
        print("   ❌ smartlead_config.py не знайдено")
        print("      Скопіюйте smartlead_config.template.py як smartlead_config.py")
        return False

def check_files():
    """Перевірка наявності файлів"""
    print("\n📁 Файли:")

    required_files = [
        'smartlead_dashboard_v2.py',
        'smartlead_dashboard.py',
        'smartlead_data_export.py',
        'requirements_smartlead.txt',
        'QUICKSTART_SMARTLEAD.md',
        'SMARTLEAD_README.md'
    ]

    all_ok = True
    for filename in required_files:
        if os.path.exists(filename):
            size = os.path.getsize(filename)
            print(f"   ✅ {filename} ({size} bytes)")
        else:
            print(f"   ❌ {filename} НЕ ЗНАЙДЕНО")
            all_ok = False

    return all_ok

def test_api_connection():
    """Тест підключення до API"""
    print("\n🌐 Тест API підключення:")

    try:
        import requests
        import smartlead_config

        url = f"{smartlead_config.BASE_URL}/campaigns"
        headers = smartlead_config.get_headers()

        print("   🔄 Підключення до Smartlead API...")

        response = requests.get(url, headers=headers, timeout=10)

        if response.status_code == 200:
            data = response.json()
            print(f"   ✅ API підключення успішне!")
            if isinstance(data, list):
                print(f"   📊 Знайдено {len(data)} кампаній")
            return True
        elif response.status_code == 401 or response.status_code == 403:
            print(f"   ❌ Помилка автентифікації (код {response.status_code})")
            print("      Перевірте API ключ")
            return False
        else:
            print(f"   ⚠️ API повернув код {response.status_code}")
            return False

    except requests.exceptions.Timeout:
        print("   ⏱️ Timeout - перевірте інтернет з'єднання")
        return False
    except Exception as e:
        print(f"   ❌ Помилка: {e}")
        return False

def main():
    """Головна функція"""
    print("=" * 60)
    print("🔍 SMARTLEAD DASHBOARD - INSTALLATION CHECK")
    print("=" * 60)

    results = {
        'Python Version': check_python_version(),
        'Python Modules': check_modules(),
        'Files': check_files(),
        'Configuration': check_config_file(),
    }

    # Тест API тільки якщо все інше ОК
    if all(results.values()):
        results['API Connection'] = test_api_connection()

    print("\n" + "=" * 60)
    print("📋 ПІДСУМОК:")
    print("=" * 60)

    for check, status in results.items():
        icon = "✅" if status else "❌"
        print(f"{icon} {check}")

    all_ok = all(results.values())

    print("=" * 60)

    if all_ok:
        print("🎉 ВСЕ ГОТОВО!")
        print("\n🚀 Запустіть дашборд:")
        print("   streamlit run smartlead_dashboard_v2.py")
    else:
        print("⚠️ ПОТРІБНІ ДОДАТКОВІ НАЛАШТУВАННЯ")
        print("\n📖 Дивіться:")
        print("   QUICKSTART_SMARTLEAD.md - швидкий старт")
        print("   SMARTLEAD_README.md - повна документація")

    print("=" * 60)

    return all_ok

if __name__ == "__main__":
    success = main()
    sys.exit(0 if success else 1)
