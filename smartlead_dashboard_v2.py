import requests
import pandas as pd
import streamlit as st
import plotly.express as px
import plotly.graph_objects as go
from datetime import datetime, timedelta
import time
from smartlead_config import *

st.set_page_config(page_title=PAGE_TITLE, layout=LAYOUT, page_icon=PAGE_ICON)

# --- API ФУНКЦІЇ ---
def api_request(endpoint, method="GET", params=None, data=None):
    """Універсальна функція для API запитів"""
    url = f"{BASE_URL}/{endpoint}"
    headers = get_headers()

    try:
        if method == "GET":
            response = requests.get(url, headers=headers, params=params, timeout=API_TIMEOUT)
        elif method == "POST":
            response = requests.post(url, headers=headers, json=data, timeout=API_TIMEOUT)

        if DEBUG_MODE:
            st.sidebar.caption(f"🔍 {method} {endpoint}: {response.status_code}")

        if response.status_code == 200:
            return response.json()
        elif response.status_code == 429:
            st.warning("⏸️ Rate limit досягнуто, зачекайте...")
            time.sleep(5)
            return api_request(endpoint, method, params, data)
        else:
            st.error(f"Помилка {response.status_code} для {endpoint}")
            return None
    except requests.exceptions.Timeout:
        st.error(f"⏱️ Timeout для {endpoint}")
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

def get_campaign_leads(campaign_id, offset=0, limit=None):
    """Отримати ліди кампанії"""
    if limit is None:
        limit = MAX_LEADS_PER_CAMPAIGN
    return api_request(f"campaigns/{campaign_id}/leads", params={"offset": offset, "limit": limit})

def get_email_accounts():
    """Отримати всі email акаунти"""
    return api_request("email-accounts")

def get_global_stats():
    """Отримати глобальну статистику"""
    return api_request("campaigns/stats")

# --- ВІЗУАЛІЗАЦІЯ ---
def create_funnel_chart(df):
    """Створити воронку конверсії"""
    funnel_data = {
        'Етап': ['Відправлено', 'Відкрито', 'Кліки', 'Відповіді', 'Позитивні'],
        'Кількість': [
            df['Відправлено'].sum(),
            df['Відкриття'].sum(),
            df['Кліки'].sum(),
            df['Відповіді'].sum(),
            df['Позитивні'].sum()
        ]
    }
    fig = px.funnel(funnel_data, x='Кількість', y='Етап', title='🔄 Воронка конверсії')
    fig.update_traces(textinfo='value+percent previous')
    return fig

def create_performance_heatmap(df):
    """Створити теплову карту продуктивності"""
    metrics = ['% Відкриттів', '% Кліків', '% Відповідей']
    top_campaigns = df.nlargest(10, 'Відправлено')

    fig = go.Figure(data=go.Heatmap(
        z=top_campaigns[metrics].values.T,
        x=top_campaigns['Назва кампанії'],
        y=metrics,
        colorscale='RdYlGn',
        text=top_campaigns[metrics].values.T,
        texttemplate='%{text:.1f}%',
        textfont={"size": 10},
    ))

    fig.update_layout(
        title='🌡️ Теплова карта продуктивності (Топ-10)',
        xaxis_title='Кампанія',
        yaxis_title='Метрика',
        height=400
    )

    return fig

def create_timeline_chart(df):
    """Створити timeline графік"""
    if 'Створена' in df.columns:
        try:
            df_timeline = df.copy()
            df_timeline['Дата'] = pd.to_datetime(df_timeline['Створена'])
            df_timeline = df_timeline.sort_values('Дата')
            df_timeline['累積Відправлено'] = df_timeline['Відправлено'].cumsum()

            fig = go.Figure()
            fig.add_trace(go.Scatter(
                x=df_timeline['Дата'],
                y=df_timeline['累積Відправлено'],
                mode='lines+markers',
                name='Кумулятивно відправлено',
                fill='tozeroy'
            ))

            fig.update_layout(
                title='📅 Динаміка відправок за часом',
                xaxis_title='Дата',
                yaxis_title='Кумулятивна кількість',
                hovermode='x unified'
            )

            return fig
        except:
            return None
    return None

def create_engagement_radar(df):
    """Створити radar chart для топ кампаній"""
    top_n = min(5, len(df))
    top_campaigns = df.nlargest(top_n, 'Відправлено')

    fig = go.Figure()

    for idx, row in top_campaigns.iterrows():
        fig.add_trace(go.Scatterpolar(
            r=[
                row['% Відкриттів'],
                row['% Кліків'],
                row['% Відповідей'],
                (row['Позитивні'] / max(row['Відповіді'], 1)) * 100,
                100 - (row['Відписки'] / max(row['Відправлено'], 1)) * 100
            ],
            theta=['Відкриття', 'Кліки', 'Відповіді', 'Позитивність', 'Утримання'],
            fill='toself',
            name=row['Назва кампанії'][:30]
        ))

    fig.update_layout(
        polar=dict(radialaxis=dict(visible=True, range=[0, 100])),
        showlegend=True,
        title='🎯 Radar Chart: Порівняння залучення'
    )

    return fig

# --- DASHBOARD ---
def main():
    # Валідація конфігурації
    config_errors = validate_config()
    if config_errors:
        st.error("⚠️ Помилки конфігурації:")
        for error in config_errors:
            st.write(error)
        st.stop()

    st.title(f"{PAGE_ICON} {PAGE_TITLE}")
    st.markdown("---")

    # Sidebar
    with st.sidebar:
        st.header("⚙️ Налаштування")

        # Фільтри
        auto_refresh = st.checkbox("Авто-оновлення", value=AUTO_REFRESH_BY_DEFAULT)
        if auto_refresh:
            st.info(f"⏱️ Оновлення кожні {AUTO_REFRESH_INTERVAL//60} хв")

        show_detailed_leads = st.checkbox("Показати ліди", value=SHOW_LEADS_BY_DEFAULT)

        date_range = st.date_input(
            "Період аналізу",
            value=(datetime.now() - timedelta(days=30), datetime.now()),
            max_value=datetime.now()
        )

        st.markdown("---")

        # Швидкі дії
        st.subheader("🚀 Швидкі дії")
        if st.button("🔄 Оновити дані"):
            st.rerun()

        if st.button("📥 Експорт всіх даних"):
            st.info("💡 Запустіть: python smartlead_data_export.py")

        st.markdown("---")

        # Інфо
        st.caption(f"🔑 API: {get_clean_api_key()[:10]}...")
        st.caption(f"🕐 {datetime.now().strftime('%H:%M:%S')}")

        if DEBUG_MODE:
            st.markdown("---")
            st.warning("🔍 DEBUG MODE")

    # 1. ЗАГАЛЬНА СТАТИСТИКА
    st.header("📈 Загальна Статистика")

    with st.spinner('🔄 Завантаження даних...'):
        campaigns = get_campaigns()

    if not campaigns or not isinstance(campaigns, list):
        st.warning("⚠️ Дані кампаній не отримано")
        st.info("💡 Перевірте API ключ в smartlead_config.py")
        st.stop()

    # Метрики
    col1, col2, col3, col4 = st.columns(4)
    total_campaigns = len(campaigns)
    active_campaigns = len([c for c in campaigns if c.get('status') == 'ACTIVE'])
    paused_campaigns = total_campaigns - active_campaigns

    col1.metric("🎯 Всього кампаній", total_campaigns)
    col2.metric("✅ Активних", active_campaigns)
    col3.metric("⏸️ Призупинених", paused_campaigns)
    col4.metric("📊 Активність", f"{(active_campaigns/max(total_campaigns,1)*100):.0f}%")

    st.markdown("---")

    # 2. ЗБІР СТАТИСТИКИ
    st.header("📊 Аналітика Кампаній")

    campaign_data = []
    progress_bar = st.progress(0)
    status_text = st.empty()

    for i, camp in enumerate(campaigns):
        status_text.text(f'⏳ Обробка {i+1}/{len(campaigns)}: {camp.get("name", "N/A")}')

        c_id = camp.get('id')
        stats = get_campaign_stats(c_id)

        if stats:
            total_sent = stats.get('total_leads_contacted', 0)
            total_replies = stats.get('total_replied', 0)

            campaign_data.append({
                "ID": c_id,
                "Назва кампанії": camp.get('name', 'N/A'),
                "Статус": camp.get('status', 'N/A'),
                "Створена": camp.get('created_at', 'N/A'),
                "Відправлено": total_sent,
                "Відкриття": stats.get('total_unique_open', 0),
                "% Відкриттів": round(stats.get('open_rate', 0), 2),
                "Кліки": stats.get('total_unique_click', 0),
                "% Кліків": round(stats.get('click_rate', 0), 2),
                "Відповіді": total_replies,
                "% Відповідей": round(stats.get('reply_rate', 0), 2),
                "Позитивні": stats.get('total_positive_reply', 0),
                "Негативні": stats.get('total_negative_reply', 0),
                "Відписки": stats.get('total_unsubscribe', 0),
                "Bounce": stats.get('total_bounced', 0),
                "Conversion": round((stats.get('total_positive_reply', 0) / max(total_sent, 1)) * 100, 2)
            })

        progress_bar.progress((i + 1) / len(campaigns))
        time.sleep(REQUEST_DELAY)

    status_text.text('✅ Завершено!')
    time.sleep(0.3)
    status_text.empty()
    progress_bar.empty()

    if not campaign_data:
        st.warning("Немає даних")
        st.stop()

    df = pd.DataFrame(campaign_data)

    # 3. КЛЮЧОВІ МЕТРИКИ
    col1, col2, col3, col4, col5, col6 = st.columns(6)

    total_sent = df['Відправлено'].sum()
    total_opened = df['Відкриття'].sum()
    total_clicked = df['Кліки'].sum()
    total_replied = df['Відповіді'].sum()
    total_positive = df['Позитивні'].sum()
    total_unsubscribed = df['Відписки'].sum()

    col1.metric("📧 Відправлено", f"{total_sent:,}")
    col2.metric("👀 Відкриття", f"{total_opened:,}", f"{(total_opened/max(total_sent,1)*100):.1f}%")
    col3.metric("🖱️ Кліки", f"{total_clicked:,}", f"{(total_clicked/max(total_sent,1)*100):.1f}%")
    col4.metric("💬 Відповіді", f"{total_replied:,}", f"{(total_replied/max(total_sent,1)*100):.1f}%")
    col5.metric("✅ Позитивні", f"{total_positive:,}", f"{(total_positive/max(total_replied,1)*100):.1f}%")
    col6.metric("❌ Відписки", f"{total_unsubscribed:,}", f"{(total_unsubscribed/max(total_sent,1)*100):.1f}%")

    st.markdown("---")

    # 4. ВІЗУАЛІЗАЦІЇ
    tab1, tab2, tab3, tab4, tab5 = st.tabs([
        "📊 Огляд",
        "📈 Детальна статистика",
        "🎯 Advanced Analytics",
        "⚖️ Порівняння",
        "📋 Таблиця"
    ])

    with tab1:
        col1, col2 = st.columns(2)

        with col1:
            st.plotly_chart(create_funnel_chart(df), use_container_width=True)

        with col2:
            response_data = pd.DataFrame({
                'Тип': ['Позитивні', 'Негативні', 'Відписки', 'Інші'],
                'Кількість': [
                    df['Позитивні'].sum(),
                    df['Негативні'].sum(),
                    df['Відписки'].sum(),
                    df['Відповіді'].sum() - df['Позитивні'].sum() - df['Негативні'].sum()
                ]
            })

            fig_pie = px.pie(response_data, values='Кількість', names='Тип',
                            title='📊 Розподіл відповідей',
                            color_discrete_map={
                                'Позитивні': COLOR_SCHEMES['positive'],
                                'Негативні': COLOR_SCHEMES['negative'],
                                'Відписки': COLOR_SCHEMES['neutral'],
                                'Інші': COLOR_SCHEMES['primary']
                            })
            st.plotly_chart(fig_pie, use_container_width=True)

        # Timeline
        timeline_fig = create_timeline_chart(df)
        if timeline_fig:
            st.plotly_chart(timeline_fig, use_container_width=True)

    with tab2:
        col1, col2 = st.columns(2)

        with col1:
            st.markdown(f"**🏆 Топ-{TOP_CAMPAIGNS_COUNT} по % відкриттів**")
            top_open = df.nlargest(TOP_CAMPAIGNS_COUNT, '% Відкриттів')[['Назва кампанії', '% Відкриттів', 'Відправлено']]
            fig = px.bar(top_open, x='% Відкриттів', y='Назва кампанії',
                        orientation='h', color='% Відкриттів',
                        color_continuous_scale='Blues',
                        hover_data=['Відправлено'])
            st.plotly_chart(fig, use_container_width=True)

        with col2:
            st.markdown(f"**🏆 Топ-{TOP_CAMPAIGNS_COUNT} по % відповідей**")
            top_reply = df.nlargest(TOP_CAMPAIGNS_COUNT, '% Відповідей')[['Назва кампанії', '% Відповідей', 'Відправлено']]
            fig = px.bar(top_reply, x='% Відповідей', y='Назва кампанії',
                        orientation='h', color='% Відповідей',
                        color_continuous_scale='Greens',
                        hover_data=['Відправлено'])
            st.plotly_chart(fig, use_container_width=True)

        # Scatter
        st.subheader("🎯 Кореляція: Відкриття vs Відповіді")
        fig_scatter = px.scatter(df, x='% Відкриттів', y='% Відповідей',
                               size='Відправлено', color='Статус',
                               hover_data=['Назва кампанії', 'Conversion'],
                               title='Залежність відповідей від відкриттів')
        st.plotly_chart(fig_scatter, use_container_width=True)

    with tab3:
        st.subheader("🔥 Advanced Analytics")

        # Heatmap
        st.plotly_chart(create_performance_heatmap(df), use_container_width=True)

        # Radar
        st.plotly_chart(create_engagement_radar(df), use_container_width=True)

        # Статистика по статусам
        col1, col2 = st.columns(2)

        with col1:
            st.markdown("**📊 Розподіл за статусами**")
            status_df = df.groupby('Статус').agg({
                'Відправлено': 'sum',
                'Відповіді': 'sum',
                'Позитивні': 'sum'
            }).reset_index()

            fig = px.bar(status_df, x='Статус', y=['Відправлено', 'Відповіді', 'Позитивні'],
                        barmode='group', title='Метрики за статусами')
            st.plotly_chart(fig, use_container_width=True)

        with col2:
            st.markdown("**🎯 Conversion Rate Distribution**")
            fig_hist = px.histogram(df, x='Conversion',
                                   title='Розподіл конверсії',
                                   nbins=20,
                                   labels={'Conversion': 'Conversion %'})
            st.plotly_chart(fig_hist, use_container_width=True)

    with tab4:
        st.subheader("⚖️ Порівняльний аналіз")

        selected = st.multiselect(
            "Виберіть кампанії:",
            options=df['Назва кампанії'].tolist(),
            default=df.nlargest(3, 'Відправлено')['Назва кампанії'].tolist()
        )

        if selected:
            comparison_df = df[df['Назва кампанії'].isin(selected)]

            metrics = ['% Відкриттів', '% Кліків', '% Відповідей', 'Conversion']

            fig = go.Figure()
            for metric in metrics:
                fig.add_trace(go.Bar(
                    name=metric,
                    x=comparison_df['Назва кампанії'],
                    y=comparison_df[metric]
                ))

            fig.update_layout(barmode='group',
                            title='Порівняння ключових метрик',
                            xaxis_title='Кампанія',
                            yaxis_title='Відсоток (%)')
            st.plotly_chart(fig, use_container_width=True)

            # Детальна таблиця порівняння
            st.dataframe(
                comparison_df[['Назва кампанії', 'Статус', 'Відправлено',
                              '% Відкриттів', '% Кліків', '% Відповідей',
                              'Позитивні', 'Conversion']],
                use_container_width=True
            )

    with tab5:
        st.subheader("📋 Повна таблиця даних")

        # Фільтри
        col1, col2, col3 = st.columns(3)

        with col1:
            status_filter = st.multiselect(
                "Статус:",
                options=df['Статус'].unique().tolist(),
                default=df['Статус'].unique().tolist()
            )

        with col2:
            min_sent = st.number_input("Мін. відправлено:", min_value=0, value=0)

        with col3:
            sort_by = st.selectbox("Сортувати за:", options=[
                'Відправлено', '% Відкриттів', '% Відповідей', 'Conversion', 'Позитивні'
            ])

        filtered_df = df[
            (df['Статус'].isin(status_filter)) &
            (df['Відправлено'] >= min_sent)
        ].sort_values(sort_by, ascending=False)

        st.dataframe(
            filtered_df.style.background_gradient(
                subset=['% Відкриттів', '% Відповідей', 'Conversion'],
                cmap='RdYlGn'
            ),
            use_container_width=True,
            height=400
        )

        # Експорт
        csv = filtered_df.to_csv(index=False).encode('utf-8')
        st.download_button(
            label="📥 Завантажити CSV",
            data=csv,
            file_name=f'smartlead_{datetime.now().strftime(EXPORT_DATE_FORMAT)}.csv',
            mime='text/csv'
        )

    # 5. EMAIL ACCOUNTS
    if SHOW_EMAIL_ACCOUNTS:
        st.markdown("---")
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
                    "Відправлено": acc.get('daily_sent_count', 0),
                    "Ліміт": acc.get('max_email_per_day', 0),
                    "Використання": f"{(acc.get('daily_sent_count', 0) / max(acc.get('max_email_per_day', 1), 1) * 100):.0f}%"
                })

            if ea_data:
                df_emails = pd.DataFrame(ea_data)

                col1, col2, col3, col4 = st.columns(4)
                col1.metric("📧 Всього", len(df_emails))
                col2.metric("✅ Warmup", len(df_emails[df_emails['Warmup'] == '✅']))
                col3.metric("📤 Відправлено", df_emails['Відправлено'].sum())
                col4.metric("📊 Avg використання", f"{df_emails['Використання'].str.rstrip('%').astype(float).mean():.0f}%")

                st.dataframe(df_emails, use_container_width=True)

    # 6. LEADS (опціонально)
    if show_detailed_leads:
        st.markdown("---")
        st.header("👥 Детальна інформація по лідам")

        campaign_select = st.selectbox(
            "Виберіть кампанію:",
            options=df['Назва кампанії'].tolist()
        )

        if campaign_select:
            campaign_id = df[df['Назва кампанії'] == campaign_select]['ID'].values[0]

            with st.spinner(f'Завантаження лідів...'):
                leads = get_campaign_leads(campaign_id)

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

                    # Статистика по лідам
                    col1, col2, col3 = st.columns(3)
                    col1.metric("👥 Всього лідів", len(df_leads))
                    status_counts = df_leads['Статус'].value_counts()
                    if len(status_counts) > 0:
                        col2.metric("📊 Топ статус", status_counts.index[0])
                        col3.metric("Кількість", status_counts.values[0])

                    st.dataframe(df_leads, use_container_width=True)

                    # Експорт лідів
                    csv_leads = df_leads.to_csv(index=False).encode('utf-8')
                    st.download_button(
                        label="📥 Завантажити ліди (CSV)",
                        data=csv_leads,
                        file_name=f'leads_{campaign_select}_{datetime.now().strftime(EXPORT_DATE_FORMAT)}.csv',
                        mime='text/csv'
                    )

    # Footer
    st.markdown("---")
    col1, col2, col3 = st.columns(3)
    col1.caption(f"🕐 Оновлено: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
    col2.caption(f"📊 Кампаній: {len(df)}")
    col3.caption(f"📧 Всього відправлено: {total_sent:,}")

    # Auto-refresh
    if auto_refresh:
        time.sleep(AUTO_REFRESH_INTERVAL)
        st.rerun()

if __name__ == "__main__":
    main()
