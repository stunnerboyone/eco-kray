import requests
import pandas as pd
import streamlit as st
import plotly.express as px
import plotly.graph_objects as go
from datetime import datetime, timedelta
import time

# --- КОНФІГУРАЦІЯ ---
RAW_API_KEY = "782e6baa-a185-4779-9a05-799e4c21a65d_zduejn7"
API_KEY = "".join(c for c in RAW_API_KEY if ord(c) < 128).strip()
BASE_URL = "https://server.smartlead.ai/api/v1"

st.set_page_config(page_title="Smartlead Advanced Dashboard", layout="wide", page_icon="📊")

# --- API ФУНКЦІЇ ---
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
            st.error(f"Помилка {response.status_code} для {endpoint}: {response.text}")
            return None
    except Exception as e:
        st.error(f"Помилка з'єднання до {endpoint}: {e}")
        return None

def get_campaigns():
    """Отримати всі кампанії"""
    return api_request("campaigns")

def get_campaign_stats(campaign_id):
    """Отримати статистику кампанії"""
    return api_request(f"campaigns/{campaign_id}/analytics")

def get_campaign_leads(campaign_id, offset=0, limit=100):
    """Отримати ліди кампанії"""
    return api_request(f"campaigns/{campaign_id}/leads", params={"offset": offset, "limit": limit})

def get_email_accounts():
    """Отримати всі email акаунти"""
    return api_request("email-accounts")

def get_global_stats():
    """Отримати глобальну статистику"""
    return api_request("campaigns/stats")

# --- ДАШБОРД ---
def main():
    st.title("📊 Smartlead Advanced Analytics Dashboard")
    st.markdown("---")

    # Sidebar для фільтрів
    with st.sidebar:
        st.header("⚙️ Налаштування")
        auto_refresh = st.checkbox("Авто-оновлення (кожні 5 хв)", value=False)
        show_detailed_leads = st.checkbox("Показати детальні ліди", value=False)
        date_range = st.date_input(
            "Період аналізу",
            value=(datetime.now() - timedelta(days=30), datetime.now()),
            max_value=datetime.now()
        )

    # 1. ЗАГАЛЬНА СТАТИСТИКА
    st.header("📈 Загальна Статистика")

    with st.spinner('🔄 Завантаження глобальних даних...'):
        global_stats = get_global_stats()
        campaigns = get_campaigns()

    if not campaigns or not isinstance(campaigns, list):
        st.warning("⚠️ Дані кампаній не отримано")
        return

    # Метрики верхнього рівня
    col1, col2, col3, col4 = st.columns(4)

    total_campaigns = len(campaigns)
    active_campaigns = len([c for c in campaigns if c.get('status') == 'ACTIVE'])

    col1.metric("🎯 Всього кампаній", total_campaigns)
    col2.metric("✅ Активних", active_campaigns)
    col3.metric("⏸️ Призупинених", total_campaigns - active_campaigns)

    st.markdown("---")

    # 2. ДЕТАЛЬНА СТАТИСТИКА ПО КАМПАНІЯХ
    st.header("📊 Аналітика Кампаній")

    campaign_data = []
    progress_bar = st.progress(0)
    status_text = st.empty()

    for i, camp in enumerate(campaigns):
        status_text.text(f'Обробка кампанії {i+1}/{len(campaigns)}: {camp.get("name", "N/A")}')

        c_id = camp.get('id')
        stats = get_campaign_stats(c_id)

        if stats:
            campaign_data.append({
                "ID": c_id,
                "Назва кампанії": camp.get('name', 'N/A'),
                "Статус": camp.get('status', 'N/A'),
                "Створена": camp.get('created_at', 'N/A'),
                "Відправлено": stats.get('total_leads_contacted', 0),
                "Відкриття": stats.get('total_unique_open', 0),
                "% Відкриттів": round(stats.get('open_rate', 0), 2),
                "Кліки": stats.get('total_unique_click', 0),
                "% Кліків": round(stats.get('click_rate', 0), 2),
                "Відповіді": stats.get('total_replied', 0),
                "% Відповідей": round(stats.get('reply_rate', 0), 2),
                "Позитивні": stats.get('total_positive_reply', 0),
                "Негативні": stats.get('total_negative_reply', 0),
                "Відписки": stats.get('total_unsubscribe', 0),
                "Bounce": stats.get('total_bounced', 0)
            })

        progress_bar.progress((i + 1) / len(campaigns))
        time.sleep(0.1)  # Уникнення rate limit

    status_text.text('✅ Завантаження завершено!')
    time.sleep(0.5)
    status_text.empty()
    progress_bar.empty()

    if not campaign_data:
        st.warning("Немає даних для відображення")
        return

    df_campaigns = pd.DataFrame(campaign_data)

    # Ключові метрики
    col1, col2, col3, col4, col5 = st.columns(5)
    col1.metric("📧 Всього відправлено", f"{df_campaigns['Відправлено'].sum():,}")
    col2.metric("👀 Всього відкриттів", f"{df_campaigns['Відкриття'].sum():,}")
    col3.metric("💬 Всього відповідей", f"{df_campaigns['Відповіді'].sum():,}")
    col4.metric("✅ Позитивних", f"{df_campaigns['Позитивні'].sum():,}")
    col5.metric("❌ Відписок", f"{df_campaigns['Відписки'].sum():,}")

    st.markdown("---")

    # Візуалізації
    tab1, tab2, tab3, tab4 = st.tabs(["📊 Огляд", "📈 Детальна статистика", "🎯 Порівняння", "📋 Таблиця"])

    with tab1:
        col1, col2 = st.columns(2)

        with col1:
            # Воронка конверсії
            st.subheader("🔄 Воронка конверсії")
            funnel_data = {
                'Етап': ['Відправлено', 'Відкрито', 'Кліки', 'Відповіді'],
                'Кількість': [
                    df_campaigns['Відправлено'].sum(),
                    df_campaigns['Відкриття'].sum(),
                    df_campaigns['Кліки'].sum(),
                    df_campaigns['Відповіді'].sum()
                ]
            }
            fig_funnel = px.funnel(funnel_data, x='Кількість', y='Етап',
                                  title='Воронка залучення')
            st.plotly_chart(fig_funnel, use_container_width=True)

        with col2:
            # Розподіл відповідей
            st.subheader("📊 Розподіл відповідей")
            response_data = pd.DataFrame({
                'Тип': ['Позитивні', 'Негативні', 'Відписки'],
                'Кількість': [
                    df_campaigns['Позитивні'].sum(),
                    df_campaigns['Негативні'].sum(),
                    df_campaigns['Відписки'].sum()
                ]
            })
            fig_pie = px.pie(response_data, values='Кількість', names='Тип',
                            color_discrete_sequence=['#00CC96', '#EF553B', '#FFA15A'])
            st.plotly_chart(fig_pie, use_container_width=True)

    with tab2:
        # Детальна статистика по кампаніях
        st.subheader("📈 Показники за кампаніями")

        # Топ кампанії по відкриттям
        col1, col2 = st.columns(2)
        with col1:
            st.markdown("**🏆 Топ-5 по % відкриттів**")
            top_open = df_campaigns.nlargest(5, '% Відкриттів')[['Назва кампанії', '% Відкриттів']]
            fig_top_open = px.bar(top_open, x='% Відкриттів', y='Назва кампанії',
                                 orientation='h', color='% Відкриттів',
                                 color_continuous_scale='Blues')
            st.plotly_chart(fig_top_open, use_container_width=True)

        with col2:
            st.markdown("**🏆 Топ-5 по % відповідей**")
            top_reply = df_campaigns.nlargest(5, '% Відповідей')[['Назва кампанії', '% Відповідей']]
            fig_top_reply = px.bar(top_reply, x='% Відповідей', y='Назва кампанії',
                                  orientation='h', color='% Відповідей',
                                  color_continuous_scale='Greens')
            st.plotly_chart(fig_top_reply, use_container_width=True)

        # Scatter plot
        st.subheader("🎯 Кореляція: Відкриття vs Відповіді")
        fig_scatter = px.scatter(df_campaigns, x='% Відкриттів', y='% Відповідей',
                               size='Відправлено', color='Статус',
                               hover_data=['Назва кампанії'],
                               title='Залежність відповідей від відкриттів')
        st.plotly_chart(fig_scatter, use_container_width=True)

    with tab3:
        # Порівняння кампаній
        st.subheader("⚖️ Порівняльний аналіз")

        selected_campaigns = st.multiselect(
            "Виберіть кампанії для порівняння:",
            options=df_campaigns['Назва кампанії'].tolist(),
            default=df_campaigns['Назва кампанії'].head(3).tolist()
        )

        if selected_campaigns:
            comparison_df = df_campaigns[df_campaigns['Назва кампанії'].isin(selected_campaigns)]

            metrics_to_compare = ['% Відкриттів', '% Кліків', '% Відповідей']

            fig_comparison = go.Figure()
            for metric in metrics_to_compare:
                fig_comparison.add_trace(go.Bar(
                    name=metric,
                    x=comparison_df['Назва кампанії'],
                    y=comparison_df[metric]
                ))

            fig_comparison.update_layout(barmode='group',
                                        title='Порівняння ключових метрик',
                                        xaxis_title='Кампанія',
                                        yaxis_title='Відсоток (%)')
            st.plotly_chart(fig_comparison, use_container_width=True)

    with tab4:
        st.subheader("📋 Повна таблиця даних")

        # Фільтри
        col1, col2 = st.columns(2)
        with col1:
            status_filter = st.multiselect(
                "Фільтр за статусом:",
                options=df_campaigns['Статус'].unique().tolist(),
                default=df_campaigns['Статус'].unique().tolist()
            )

        filtered_df = df_campaigns[df_campaigns['Статус'].isin(status_filter)]

        st.dataframe(
            filtered_df.style.background_gradient(subset=['% Відкриттів', '% Відповідей'],
                                                 cmap='RdYlGn'),
            use_container_width=True,
            height=400
        )

        # Експорт в CSV
        csv = filtered_df.to_csv(index=False).encode('utf-8')
        st.download_button(
            label="📥 Завантажити CSV",
            data=csv,
            file_name=f'smartlead_campaigns_{datetime.now().strftime("%Y%m%d_%H%M%S")}.csv',
            mime='text/csv'
        )

    st.markdown("---")

    # 3. EMAIL АКАУНТИ
    st.header("📧 Email Акаунти")

    with st.spinner('Завантаження email акаунтів...'):
        email_accounts = get_email_accounts()

    if email_accounts and isinstance(email_accounts, list):
        ea_data = []
        for acc in email_accounts:
            ea_data.append({
                "Email": acc.get('from_email', 'N/A'),
                "Статус": acc.get('status', 'N/A'),
                "Warmup": "✅" if acc.get('warmup_enabled') else "❌",
                "Відправлено сьогодні": acc.get('daily_sent_count', 0),
                "Ліміт відправки": acc.get('max_email_per_day', 0),
            })

        if ea_data:
            df_emails = pd.DataFrame(ea_data)

            col1, col2, col3 = st.columns(3)
            col1.metric("📧 Всього акаунтів", len(df_emails))
            col2.metric("✅ З Warmup", len(df_emails[df_emails['Warmup'] == '✅']))
            col3.metric("📤 Відправлено сьогодні", df_emails['Відправлено сьогодні'].sum())

            st.dataframe(df_emails, use_container_width=True)

    # 4. ДЕТАЛЬНА ІНФОРМАЦІЯ ПО ЛІДАМ (опціонально)
    if show_detailed_leads:
        st.markdown("---")
        st.header("👥 Детальна інформація по лідам")

        campaign_select = st.selectbox(
            "Виберіть кампанію для перегляду лідів:",
            options=df_campaigns['Назва кампанії'].tolist()
        )

        if campaign_select:
            campaign_id = df_campaigns[df_campaigns['Назва кампанії'] == campaign_select]['ID'].values[0]

            with st.spinner(f'Завантаження лідів для "{campaign_select}"...'):
                leads = get_campaign_leads(campaign_id, limit=100)

            if leads and isinstance(leads, list):
                leads_data = []
                for lead in leads:
                    leads_data.append({
                        "Email": lead.get('email', 'N/A'),
                        "Ім'я": lead.get('first_name', 'N/A'),
                        "Прізвище": lead.get('last_name', 'N/A'),
                        "Компанія": lead.get('company_name', 'N/A'),
                        "Статус": lead.get('lead_status', 'N/A'),
                        "Додано": lead.get('created_at', 'N/A')
                    })

                if leads_data:
                    df_leads = pd.DataFrame(leads_data)
                    st.dataframe(df_leads, use_container_width=True)
                    st.info(f"📊 Показано {len(df_leads)} лідів (максимум 100)")

    # Footer
    st.markdown("---")
    st.caption(f"🕐 Останнє оновлення: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")

    # Auto-refresh
    if auto_refresh:
        time.sleep(300)  # 5 хвилин
        st.rerun()

if __name__ == "__main__":
    main()
