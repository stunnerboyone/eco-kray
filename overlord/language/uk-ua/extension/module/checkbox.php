<?php
// Heading
$_['heading_title']              = 'Checkbox ПРРО';

// Text
$_['text_extension']             = 'Розширення';
$_['text_home']                  = 'Головна';
$_['text_edit']                  = 'Налаштування модуля Checkbox ПРРО';
$_['text_enabled']               = 'Увімкнено';
$_['text_disabled']              = 'Вимкнено';
$_['text_all_statuses']          = '-- Всі статуси --';
$_['text_log_empty']             = 'Лог порожній.';
$_['text_cashless']              = 'Безготівковий (CASHLESS) — для онлайн-оплат';
$_['text_cash']                  = 'Готівка (CASH)';
$_['text_success']               = 'Налаштування збережено!';
$_['text_log_cleared']           = 'Лог очищено.';
$_['text_test_success']          = 'Підключення до Checkbox успішне! Авторизація пройшла.';
$_['text_verify']                = 'Перевірити на сайті ДПС';
$_['text_no_receipt']            = 'Фіскальний чек для цього замовлення відсутній.';

// Entry (form labels)
$_['entry_status']               = 'Статус модуля';
$_['entry_login']                = 'Email касира';
$_['entry_password']             = 'Пароль касира';
$_['entry_cash_register_id']     = 'ID каси (необов\'язково)';
$_['entry_payment_type']         = 'Тип оплати за замовчуванням';
$_['entry_trigger_statuses']     = 'Статуси замовлення для фіскалізації';

// Help text
$_['help_login']                 = 'Email акаунту касира в особистому кабінеті Checkbox.';
$_['help_password']              = 'Пароль касира в особистому кабінеті Checkbox.';
$_['help_cash_register_id']      = 'UUID каси з Checkbox (якщо у касира є кілька кас). Залиште порожнім для використання каси за замовчуванням.';
$_['help_payment_type']          = 'Для інтернет-оплат оберіть CASHLESS. Для оплати при отриманні — CASH.';
$_['help_trigger_statuses']      = 'Утримуйте Ctrl для вибору кількох статусів. Фіскальний чек буде створено коли замовлення перейде в один з обраних статусів.';

// Receipt tab labels
$_['label_receipt_id']           = 'ID чеку (Checkbox)';
$_['label_fiscal_code']          = 'Фіскальний номер ДПС';
$_['label_status']               = 'Статус';
$_['label_total']                = 'Сума';
$_['label_date']                 = 'Дата фіскалізації';
$_['label_tax_url']              = 'Перевірка ДПС';

// Tabs
$_['tab_general']                = 'Загальні';
$_['tab_log']                    = 'Лог';

// Buttons
$_['button_save']                = 'Зберегти';
$_['button_cancel']              = 'Скасувати';
$_['button_test']                = 'Перевірити з\'єднання';
$_['button_clear_log']           = 'Очистити лог';

// Errors
$_['error_permission']           = 'Недостатньо прав для зміни налаштувань модуля Checkbox!';
$_['error_login']                = 'Будь ласка, введіть email касира.';
$_['error_password']             = 'Будь ласка, введіть пароль касира.';
$_['error_credentials']          = 'Email і пароль не налаштовані. Спочатку збережіть налаштування.';
$_['error_test_connection']      = 'Помилка підключення до Checkbox';
