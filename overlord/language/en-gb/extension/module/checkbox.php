<?php
// Heading
$_['heading_title']              = 'Checkbox ПРРО (Fiscal Receipts)';

// Text
$_['text_extension']             = 'Extensions';
$_['text_home']                  = 'Home';
$_['text_edit']                  = 'Edit Checkbox ПРРО Module';
$_['text_enabled']               = 'Enabled';
$_['text_disabled']              = 'Disabled';
$_['text_all_statuses']          = '-- All Statuses --';
$_['text_log_empty']             = 'Log is empty.';
$_['text_cashless']              = 'Cashless (CASHLESS) — for online payments';
$_['text_cash']                  = 'Cash (CASH)';
$_['text_success']               = 'Settings saved successfully!';
$_['text_log_cleared']           = 'Log cleared.';
$_['text_test_success']          = 'Connection to Checkbox successful! Authentication passed.';
$_['text_verify']                = 'Verify on ДПС website';
$_['text_no_receipt']            = 'No fiscal receipt found for this order.';

// Entry (form labels)
$_['entry_status']               = 'Module Status';
$_['entry_login']                = 'Cashier Email';
$_['entry_password']             = 'Cashier Password';
$_['entry_cash_register_id']     = 'Cash Register ID (optional)';
$_['entry_payment_type']         = 'Default Payment Type';
$_['entry_trigger_statuses']     = 'Order Statuses to Trigger Fiscalization';

// Help text
$_['help_login']                 = 'Email of the cashier account in the Checkbox personal cabinet.';
$_['help_password']              = 'Password of the cashier account in the Checkbox personal cabinet.';
$_['help_cash_register_id']      = 'UUID of the cash register from Checkbox (if the cashier has multiple registers). Leave empty to use the default register.';
$_['help_payment_type']          = 'For online payments select CASHLESS. For cash on delivery select CASH.';
$_['help_trigger_statuses']      = 'Hold Ctrl to select multiple statuses. A fiscal receipt will be created when an order transitions to one of the selected statuses.';

// Receipt tab labels
$_['label_receipt_id']           = 'Receipt ID (Checkbox)';
$_['label_fiscal_code']          = 'Fiscal Number (ДПС)';
$_['label_status']               = 'Status';
$_['label_total']                = 'Amount';
$_['label_date']                 = 'Fiscal Date';
$_['label_tax_url']              = 'ДПС Verification';

// Tabs
$_['tab_general']                = 'General';
$_['tab_log']                    = 'Log';

// Buttons
$_['button_save']                = 'Save';
$_['button_cancel']              = 'Cancel';
$_['button_test']                = 'Test Connection';
$_['button_clear_log']           = 'Clear Log';

// Errors
$_['error_permission']           = 'Warning: You do not have permission to modify Checkbox module settings!';
$_['error_login']                = 'Please enter the cashier email.';
$_['error_password']             = 'Please enter the cashier password.';
$_['error_credentials']          = 'Email and password are not configured. Please save the settings first.';
$_['error_test_connection']      = 'Connection error to Checkbox';
