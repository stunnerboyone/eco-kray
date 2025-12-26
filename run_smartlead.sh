#!/bin/bash

# Smartlead Dashboard Quick Start Script
# Автоматичне встановлення та запуск дашборду

echo "======================================"
echo "🚀 Smartlead Dashboard Launcher"
echo "======================================"

# Перевірка Python
if ! command -v python3 &> /dev/null; then
    echo "❌ Python 3 не знайдено. Встановіть Python 3.8+"
    exit 1
fi

echo "✅ Python знайдено: $(python3 --version)"

# Перевірка pip
if ! command -v pip3 &> /dev/null; then
    echo "❌ pip3 не знайдено. Встановіть pip"
    exit 1
fi

echo "✅ pip знайдено"

# Встановлення залежностей
echo ""
echo "📦 Встановлення залежностей..."
pip3 install -r requirements_smartlead.txt

if [ $? -eq 0 ]; then
    echo "✅ Залежності встановлено успішно"
else
    echo "❌ Помилка встановлення залежностей"
    exit 1
fi

# Запуск дашборду
echo ""
echo "======================================"
echo "🎯 Запуск Smartlead Dashboard..."
echo "======================================"
echo ""
echo "📊 Дашборд буде доступний за адресою: http://localhost:8501"
echo "⏹️  Для зупинки натисніть Ctrl+C"
echo ""

streamlit run smartlead_dashboard.py

# Якщо streamlit не знайдено
if [ $? -ne 0 ]; then
    echo ""
    echo "❌ Streamlit не знайдено. Спробуйте запустити вручну:"
    echo "   python3 -m streamlit run smartlead_dashboard.py"
fi
