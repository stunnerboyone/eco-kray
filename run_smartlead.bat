@echo off
REM Smartlead Dashboard Quick Start Script for Windows
REM Автоматичне встановлення та запуск дашборду

echo ======================================
echo 🚀 Smartlead Dashboard Launcher
echo ======================================

REM Перевірка Python
python --version >nul 2>&1
if %errorlevel% neq 0 (
    echo ❌ Python не знайдено. Встановіть Python 3.8+
    pause
    exit /b 1
)

echo ✅ Python знайдено
python --version

REM Встановлення залежностей
echo.
echo 📦 Встановлення залежностей...
pip install -r requirements_smartlead.txt

if %errorlevel% neq 0 (
    echo ❌ Помилка встановлення залежностей
    pause
    exit /b 1
)

echo ✅ Залежності встановлено успішно

REM Запуск дашборду
echo.
echo ======================================
echo 🎯 Запуск Smartlead Dashboard...
echo ======================================
echo.
echo 📊 Дашборд буде доступний за адресою: http://localhost:8501
echo ⏹️  Для зупинки натисніть Ctrl+C
echo.

streamlit run smartlead_dashboard.py

if %errorlevel% neq 0 (
    echo.
    echo ❌ Помилка запуску. Спробуйте:
    echo    python -m streamlit run smartlead_dashboard.py
    pause
)
