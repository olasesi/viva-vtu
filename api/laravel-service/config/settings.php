<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Application Settings Schema
    |--------------------------------------------------------------------------
    |
    | Every settings group exposes a set of typed fields. Each field defines a
    | label, a cast type (string|boolean|integer|decimal|enum|array), a default
    | value and the validation rules enforced when the group is updated.
    |
    */

    'groups' => [

        'business' => [
            'label' => 'Business',
            'icon' => 'store',
            'fields' => [
                'business_name' => [
                    'label' => 'Business Name',
                    'type' => 'string',
                    'default' => 'Viva VTU',
                    'rules' => 'required|string|max:255',
                ],
                'tagline' => [
                    'label' => 'Tagline',
                    'type' => 'string',
                    'default' => null,
                    'rules' => 'nullable|string|max:255',
                ],
                'address' => [
                    'label' => 'Address',
                    'type' => 'string',
                    'default' => null,
                    'rules' => 'nullable|string|max:500',
                ],
                'phone' => [
                    'label' => 'Phone',
                    'type' => 'string',
                    'default' => null,
                    'rules' => 'nullable|string|max:30',
                ],
                'email' => [
                    'label' => 'Email',
                    'type' => 'string',
                    'default' => null,
                    'rules' => 'nullable|email|max:255',
                ],
                'currency' => [
                    'label' => 'Currency',
                    'type' => 'string',
                    'default' => 'NGN',
                    'rules' => 'required|string|max:10',
                ],
                'logo' => [
                    'label' => 'Logo',
                    'type' => 'string',
                    'default' => null,
                    'rules' => 'nullable|string|max:500',
                ],
            ],
        ],

        'tax' => [
            'label' => 'Tax',
            'icon' => 'percent',
            'fields' => [
                'enable_tax' => [
                    'label' => 'Enable Tax',
                    'type' => 'boolean',
                    'default' => false,
                    'rules' => 'boolean',
                ],
                'tax_name' => [
                    'label' => 'Tax Name',
                    'type' => 'string',
                    'default' => 'VAT',
                    'rules' => 'required|string|max:100',
                ],
                'tax_rate' => [
                    'label' => 'Tax Rate (%)',
                    'type' => 'decimal',
                    'default' => 7.5,
                    'rules' => 'required|numeric|min:0|max:100',
                ],
                'tax_type' => [
                    'label' => 'Tax Type',
                    'type' => 'enum',
                    'default' => 'inclusive',
                    'options' => ['inclusive', 'exclusive'],
                    'rules' => 'required|in:inclusive,exclusive',
                ],
            ],
        ],

        'product' => [
            'label' => 'Product',
            'icon' => 'package',
            'fields' => [
                'enable_stock_management' => [
                    'label' => 'Enable Stock Management',
                    'type' => 'boolean',
                    'default' => true,
                    'rules' => 'boolean',
                ],
                'sku_prefix' => [
                    'label' => 'SKU Prefix',
                    'type' => 'string',
                    'default' => 'PRD',
                    'rules' => 'nullable|string|max:20',
                ],
                'low_stock_threshold' => [
                    'label' => 'Low Stock Threshold',
                    'type' => 'integer',
                    'default' => 10,
                    'rules' => 'integer|min:0',
                ],
                'default_profit_margin' => [
                    'label' => 'Default Profit Margin (%)',
                    'type' => 'decimal',
                    'default' => 30.00,
                    'rules' => 'numeric|min:0|max:10000',
                ],
            ],
        ],

        'contact' => [
            'label' => 'Contact',
            'icon' => 'users',
            'fields' => [
                'default_customer_group' => [
                    'label' => 'Default Customer Group',
                    'type' => 'string',
                    'default' => null,
                    'rules' => 'nullable|string|max:100',
                ],
                'default_supplier_group' => [
                    'label' => 'Default Supplier Group',
                    'type' => 'string',
                    'default' => null,
                    'rules' => 'nullable|string|max:100',
                ],
                'enable_customer_credit' => [
                    'label' => 'Allow Customer Credit',
                    'type' => 'boolean',
                    'default' => false,
                    'rules' => 'boolean',
                ],
                'contact_format' => [
                    'label' => 'Contact Number Format',
                    'type' => 'string',
                    'default' => 'international',
                    'options' => ['local', 'international'],
                    'rules' => 'in:local,international',
                ],
            ],
        ],

        'sale' => [
            'label' => 'Sale',
            'icon' => 'shopping-cart',
            'fields' => [
                'enable_sales' => [
                    'label' => 'Enable Sales',
                    'type' => 'boolean',
                    'default' => true,
                    'rules' => 'boolean',
                ],
                'enable_sale_return' => [
                    'label' => 'Enable Sales Return',
                    'type' => 'boolean',
                    'default' => true,
                    'rules' => 'boolean',
                ],
                'enable_payment_due' => [
                    'label' => 'Enable Payment Due',
                    'type' => 'boolean',
                    'default' => false,
                    'rules' => 'boolean',
                ],
                'due_days' => [
                    'label' => 'Default Due Days',
                    'type' => 'integer',
                    'default' => 30,
                    'rules' => 'integer|min:0',
                ],
            ],
        ],

        'pos' => [
            'label' => 'POS',
            'icon' => 'credit-card',
            'fields' => [
                'pos_theme_color' => [
                    'label' => 'Theme Color',
                    'type' => 'string',
                    'default' => '#2563eb',
                    'rules' => 'required|string|max:20',
                ],
                'default_datatable_page_entries' => [
                    'label' => 'Datatable Page Entries',
                    'type' => 'integer',
                    'default' => 25,
                    'rules' => 'required|integer|min:5|max:500',
                ],
                'show_help_text' => [
                    'label' => 'Show Help Text',
                    'type' => 'boolean',
                    'default' => true,
                    'rules' => 'boolean',
                ],
                'enable_cashier' => [
                    'label' => 'Enable Cashier Mode',
                    'type' => 'boolean',
                    'default' => false,
                    'rules' => 'boolean',
                ],
                'enable_bulk_edit' => [
                    'label' => 'Enable Bulk Edit',
                    'type' => 'boolean',
                    'default' => false,
                    'rules' => 'boolean',
                ],
            ],
        ],

        'display_screen' => [
            'label' => 'Display Screen',
            'icon' => 'monitor',
            'fields' => [
                'enable_customer_display' => [
                    'label' => 'Enable Customer Display Screen',
                    'type' => 'boolean',
                    'default' => false,
                    'rules' => 'boolean',
                ],
                'heading_text' => [
                    'label' => 'Display Screen Heading',
                    'type' => 'string',
                    'default' => 'Welcome',
                    'rules' => 'nullable|string|max:255',
                ],
                'carousel_image_1' => ['label' => 'Carousel Image 1', 'type' => 'string', 'default' => null, 'rules' => 'nullable|string|max:500'],
                'carousel_image_2' => ['label' => 'Carousel Image 2', 'type' => 'string', 'default' => null, 'rules' => 'nullable|string|max:500'],
                'carousel_image_3' => ['label' => 'Carousel Image 3', 'type' => 'string', 'default' => null, 'rules' => 'nullable|string|max:500'],
                'carousel_image_4' => ['label' => 'Carousel Image 4', 'type' => 'string', 'default' => null, 'rules' => 'nullable|string|max:500'],
                'carousel_image_5' => ['label' => 'Carousel Image 5', 'type' => 'string', 'default' => null, 'rules' => 'nullable|string|max:500'],
                'carousel_image_6' => ['label' => 'Carousel Image 6', 'type' => 'string', 'default' => null, 'rules' => 'nullable|string|max:500'],
                'carousel_image_7' => ['label' => 'Carousel Image 7', 'type' => 'string', 'default' => null, 'rules' => 'nullable|string|max:500'],
                'carousel_image_8' => ['label' => 'Carousel Image 8', 'type' => 'string', 'default' => null, 'rules' => 'nullable|string|max:500'],
                'carousel_image_9' => ['label' => 'Carousel Image 9', 'type' => 'string', 'default' => null, 'rules' => 'nullable|string|max:500'],
                'carousel_image_10' => ['label' => 'Carousel Image 10', 'type' => 'string', 'default' => null, 'rules' => 'nullable|string|max:500'],
            ],
        ],

        'purchases' => [
            'label' => 'Purchases',
            'icon' => 'truck',
            'fields' => [
                'enable_purchases' => [
                    'label' => 'Enable Purchases',
                    'type' => 'boolean',
                    'default' => true,
                    'rules' => 'boolean',
                ],
                'enable_purchase_order' => [
                    'label' => 'Enable Purchase Orders',
                    'type' => 'boolean',
                    'default' => true,
                    'rules' => 'boolean',
                ],
                'enable_stock_control' => [
                    'label' => 'Enable Stock Control',
                    'type' => 'boolean',
                    'default' => true,
                    'rules' => 'boolean',
                ],
                'low_stock_warning' => [
                    'label' => 'Low Stock Warning',
                    'type' => 'boolean',
                    'default' => true,
                    'rules' => 'boolean',
                ],
            ],
        ],

        'payment' => [
            'label' => 'Payment',
            'icon' => 'banknote',
            'fields' => [
                'enable_cash' => [
                    'label' => 'Enable Cash Payments',
                    'type' => 'boolean',
                    'default' => true,
                    'rules' => 'boolean',
                ],
                'enable_paystack' => [
                    'label' => 'Enable Paystack',
                    'type' => 'boolean',
                    'default' => false,
                    'rules' => 'boolean',
                ],
                'paystack_public_key' => [
                    'label' => 'Paystack Public Key',
                    'type' => 'string',
                    'default' => null,
                    'rules' => 'nullable|string|max:255',
                ],
                'paystack_secret_key' => [
                    'label' => 'Paystack Secret Key',
                    'type' => 'string',
                    'default' => null,
                    'sensitive' => true,
                    'rules' => 'nullable|string|max:255',
                ],
                'enable_flutterwave' => [
                    'label' => 'Enable Flutterwave',
                    'type' => 'boolean',
                    'default' => false,
                    'rules' => 'boolean',
                ],
                'flutterwave_public_key' => [
                    'label' => 'Flutterwave Public Key',
                    'type' => 'string',
                    'default' => null,
                    'rules' => 'nullable|string|max:255',
                ],
                'flutterwave_secret_key' => [
                    'label' => 'Flutterwave Secret Key',
                    'type' => 'string',
                    'default' => null,
                    'sensitive' => true,
                    'rules' => 'nullable|string|max:255',
                ],
            ],
        ],

        'dashboard' => [
            'label' => 'Dashboard',
            'icon' => 'layout-dashboard',
            'fields' => [
                'default_datatable_page_entries' => [
                    'label' => 'Datatable Page Entries',
                    'type' => 'integer',
                    'default' => 25,
                    'rules' => 'required|integer|min:5|max:500',
                ],
                'show_help_text' => [
                    'label' => 'Show Help Text',
                    'type' => 'boolean',
                    'default' => true,
                    'rules' => 'boolean',
                ],
                'show_top_selling_items' => [
                    'label' => 'Show Top Selling Items',
                    'type' => 'boolean',
                    'default' => true,
                    'rules' => 'boolean',
                ],
            ],
        ],

        'system' => [
            'label' => 'System',
            'icon' => 'settings',
            'fields' => [
                'theme_color' => [
                    'label' => 'Theme Color',
                    'type' => 'string',
                    'default' => '#2563eb',
                    'rules' => 'required|string|max:20',
                ],
                'default_datatable_page_entries' => [
                    'label' => 'Datatable Page Entries',
                    'type' => 'integer',
                    'default' => 25,
                    'rules' => 'required|integer|min:5|max:500',
                ],
                'show_help_text' => [
                    'label' => 'Show Help Text',
                    'type' => 'boolean',
                    'default' => true,
                    'rules' => 'boolean',
                ],
                'timezone' => [
                    'label' => 'Timezone',
                    'type' => 'string',
                    'default' => 'Africa/Lagos',
                    'rules' => 'required|string|timezone',
                ],
                'date_format' => [
                    'label' => 'Date Format',
                    'type' => 'string',
                    'default' => 'd-m-Y',
                    'rules' => 'required|string|max:30',
                ],
                'enable_2fa' => [
                    'label' => '2-Step Authentication',
                    'type' => 'boolean',
                    'default' => false,
                    'rules' => 'boolean',
                ],
                'report_default_range' => [
                    'label' => 'Default Report Range',
                    'type' => 'enum',
                    'default' => 'last_30_days',
                    'options' => ['today', 'this_week', 'this_month', 'last_30_days', 'this_year'],
                    'rules' => 'required|in:today,this_week,this_month,last_30_days,this_year',
                ],
            ],
        ],

        'prefixes' => [
            'label' => 'Prefixes',
            'icon' => 'type',
            'fields' => [
                'invoice_prefix' => ['label' => 'Invoice Prefix', 'type' => 'string', 'default' => 'INV', 'rules' => 'nullable|string|max:20'],
                'sale_prefix' => ['label' => 'Sale Prefix', 'type' => 'string', 'default' => 'SL', 'rules' => 'nullable|string|max:20'],
                'purchase_prefix' => ['label' => 'Purchase Prefix', 'type' => 'string', 'default' => 'PO', 'rules' => 'nullable|string|max:20'],
                'payment_prefix' => ['label' => 'Payment Prefix', 'type' => 'string', 'default' => 'PAY', 'rules' => 'nullable|string|max:20'],
                'customer_prefix' => ['label' => 'Customer Prefix', 'type' => 'string', 'default' => 'CU', 'rules' => 'nullable|string|max:20'],
                'supplier_prefix' => ['label' => 'Supplier Prefix', 'type' => 'string', 'default' => 'SU', 'rules' => 'nullable|string|max:20'],
                'expense_prefix' => ['label' => 'Expense Prefix', 'type' => 'string', 'default' => 'EX', 'rules' => 'nullable|string|max:20'],
            ],
        ],

        'email' => [
            'label' => 'Email Settings',
            'icon' => 'mail',
            'fields' => [
                'mail_driver' => [
                    'label' => 'Mail Driver',
                    'type' => 'enum',
                    'default' => 'smtp',
                    'options' => ['smtp', 'mailgun', 'sendmail', 'log', 'array'],
                    'rules' => 'required|in:smtp,mailgun,sendmail,log,array',
                ],
                'mail_host' => [
                    'label' => 'Host',
                    'type' => 'string',
                    'default' => null,
                    'rules' => 'required|string|max:255',
                ],
                'mail_port' => [
                    'label' => 'Port',
                    'type' => 'integer',
                    'default' => 587,
                    'rules' => 'required|integer|min:1|max:65535',
                ],
                'mail_username' => [
                    'label' => 'Username',
                    'type' => 'string',
                    'default' => null,
                    'rules' => 'required|string|max:255',
                ],
                'mail_password' => [
                    'label' => 'Password',
                    'type' => 'string',
                    'default' => null,
                    'sensitive' => true,
                    'rules' => 'nullable|string|max:255',
                ],
                'mail_encryption' => [
                    'label' => 'Encryption',
                    'type' => 'enum',
                    'default' => 'tls',
                    'options' => ['tls', 'ssl'],
                    'rules' => 'nullable|in:tls,ssl',
                ],
                'mail_from_address' => [
                    'label' => 'From Address',
                    'type' => 'string',
                    'default' => null,
                    'rules' => 'required|email|max:255',
                ],
                'mail_from_name' => [
                    'label' => 'From Name',
                    'type' => 'string',
                    'default' => null,
                    'rules' => 'required|string|max:255',
                ],
            ],
        ],

        'sms' => [
            'label' => 'SMS Settings',
            'icon' => 'message-square',
            'fields' => [
                'sms_service' => [
                    'label' => 'SMS Service',
                    'type' => 'enum',
                    'default' => 'other',
                    'options' => ['other', 'twilio', 'terminii', 'smartrecharge'],
                    'rules' => 'required|in:other,twilio,terminii,smartrecharge',
                ],
                'url' => [
                    'label' => 'SMS URL',
                    'type' => 'string',
                    'default' => null,
                    'rules' => 'required|url|max:500',
                ],
                'send_to_parameter' => [
                    'label' => 'Send-To Parameter Name',
                    'type' => 'string',
                    'default' => 'to',
                    'rules' => 'required|string|max:50',
                ],
                'message_parameter' => [
                    'label' => 'Message Parameter Name',
                    'type' => 'string',
                    'default' => 'text',
                    'rules' => 'required|string|max:50',
                ],
                'request_method' => [
                    'label' => 'Request Method',
                    'type' => 'enum',
                    'default' => 'POST',
                    'options' => ['GET', 'POST'],
                    'rules' => 'required|in:GET,POST',
                ],
                'data_parameter_type' => [
                    'label' => 'Data Parameter Type',
                    'type' => 'enum',
                    'default' => 'form_data',
                    'options' => ['form_data', 'json', 'query_string'],
                    'rules' => 'required|in:form_data,json,query_string',
                ],
                'header_1_key' => ['label' => 'Header 1 key', 'type' => 'string', 'default' => null, 'rules' => 'nullable|string|max:100'],
                'header_1_value' => ['label' => 'Header 1 value', 'type' => 'string', 'default' => null, 'rules' => 'nullable|string|max:255'],
                'header_2_key' => ['label' => 'Header 2 key', 'type' => 'string', 'default' => null, 'rules' => 'nullable|string|max:100'],
                'header_2_value' => ['label' => 'Header 2 value', 'type' => 'string', 'default' => null, 'rules' => 'nullable|string|max:255'],
                'header_3_key' => ['label' => 'Header 3 key', 'type' => 'string', 'default' => null, 'rules' => 'nullable|string|max:100'],
                'header_3_value' => ['label' => 'Header 3 value', 'type' => 'string', 'default' => null, 'rules' => 'nullable|string|max:255'],
                'parameter_1_key' => ['label' => 'Parameter 1 key', 'type' => 'string', 'default' => null, 'rules' => 'nullable|string|max:100'],
                'parameter_1_value' => ['label' => 'Parameter 1 value', 'type' => 'string', 'default' => null, 'rules' => 'nullable|string|max:255'],
                'parameter_2_key' => ['label' => 'Parameter 2 key', 'type' => 'string', 'default' => null, 'rules' => 'nullable|string|max:100'],
                'parameter_2_value' => ['label' => 'Parameter 2 value', 'type' => 'string', 'default' => null, 'rules' => 'nullable|string|max:255'],
                'parameter_3_key' => ['label' => 'Parameter 3 key', 'type' => 'string', 'default' => null, 'rules' => 'nullable|string|max:100'],
                'parameter_3_value' => ['label' => 'Parameter 3 value', 'type' => 'string', 'default' => null, 'rules' => 'nullable|string|max:255'],
                'parameter_4_key' => ['label' => 'Parameter 4 key', 'type' => 'string', 'default' => null, 'rules' => 'nullable|string|max:100'],
                'parameter_4_value' => ['label' => 'Parameter 4 value', 'type' => 'string', 'default' => null, 'rules' => 'nullable|string|max:255'],
                'parameter_5_key' => ['label' => 'Parameter 5 key', 'type' => 'string', 'default' => null, 'rules' => 'nullable|string|max:100'],
                'parameter_5_value' => ['label' => 'Parameter 5 value', 'type' => 'string', 'default' => null, 'rules' => 'nullable|string|max:255'],
                'parameter_6_key' => ['label' => 'Parameter 6 key', 'type' => 'string', 'default' => null, 'rules' => 'nullable|string|max:100'],
                'parameter_6_value' => ['label' => 'Parameter 6 value', 'type' => 'string', 'default' => null, 'rules' => 'nullable|string|max:255'],
                'parameter_7_key' => ['label' => 'Parameter 7 key', 'type' => 'string', 'default' => null, 'rules' => 'nullable|string|max:100'],
                'parameter_7_value' => ['label' => 'Parameter 7 value', 'type' => 'string', 'default' => null, 'rules' => 'nullable|string|max:255'],
                'parameter_8_key' => ['label' => 'Parameter 8 key', 'type' => 'string', 'default' => null, 'rules' => 'nullable|string|max:100'],
                'parameter_8_value' => ['label' => 'Parameter 8 value', 'type' => 'string', 'default' => null, 'rules' => 'nullable|string|max:255'],
                'parameter_9_key' => ['label' => 'Parameter 9 key', 'type' => 'string', 'default' => null, 'rules' => 'nullable|string|max:100'],
                'parameter_9_value' => ['label' => 'Parameter 9 value', 'type' => 'string', 'default' => null, 'rules' => 'nullable|string|max:255'],
                'parameter_10_key' => ['label' => 'Parameter 10 key', 'type' => 'string', 'default' => null, 'rules' => 'nullable|string|max:100'],
                'parameter_10_value' => ['label' => 'Parameter 10 value', 'type' => 'string', 'default' => null, 'rules' => 'nullable|string|max:255'],
                'test_number' => [
                    'label' => 'Test Number',
                    'type' => 'string',
                    'default' => null,
                    'rules' => 'nullable|string|max:30',
                ],
            ],
        ],

        'reward_points' => [
            'label' => 'Reward Point Settings',
            'icon' => 'star',
            'fields' => [
                'enable_reward_points' => [
                    'label' => 'Enable Reward Points',
                    'type' => 'boolean',
                    'default' => false,
                    'rules' => 'boolean',
                ],
                'points_per_naira' => [
                    'label' => 'Points per Naira',
                    'type' => 'decimal',
                    'default' => 1.00,
                    'rules' => 'numeric|min:0',
                ],
                'minimum_redeem_points' => [
                    'label' => 'Minimum Redeem Points',
                    'type' => 'integer',
                    'default' => 100,
                    'rules' => 'integer|min:0',
                ],
                'points_expiry_days' => [
                    'label' => 'Points Expiry (Days)',
                    'type' => 'integer',
                    'default' => 365,
                    'rules' => 'integer|min:0',
                ],
            ],
        ],

        'modules' => [
            'label' => 'Modules',
            'icon' => 'blocks',
            'fields' => [
                'enable_tax' => ['label' => 'Tax Module', 'type' => 'boolean', 'default' => true, 'rules' => 'boolean'],
                'enable_product' => ['label' => 'Product Module', 'type' => 'boolean', 'default' => true, 'rules' => 'boolean'],
                'enable_contact' => ['label' => 'Contact Module', 'type' => 'boolean', 'default' => true, 'rules' => 'boolean'],
                'enable_sale' => ['label' => 'Sale Module', 'type' => 'boolean', 'default' => true, 'rules' => 'boolean'],
                'enable_pos' => ['label' => 'POS Module', 'type' => 'boolean', 'default' => true, 'rules' => 'boolean'],
                'enable_display_screen' => ['label' => 'Display Screen Module', 'type' => 'boolean', 'default' => false, 'rules' => 'boolean'],
                'enable_purchase' => ['label' => 'Purchase Module', 'type' => 'boolean', 'default' => true, 'rules' => 'boolean'],
                'enable_payment' => ['label' => 'Payment Module', 'type' => 'boolean', 'default' => true, 'rules' => 'boolean'],
                'enable_reward_points' => ['label' => 'Reward Points Module', 'type' => 'boolean', 'default' => false, 'rules' => 'boolean'],
                'enable_sms' => ['label' => 'SMS Notifications', 'type' => 'boolean', 'default' => true, 'rules' => 'boolean'],
                'enable_email' => ['label' => 'Email Notifications', 'type' => 'boolean', 'default' => true, 'rules' => 'boolean'],
                'enable_custom_labels' => ['label' => 'Custom Labels', 'type' => 'boolean', 'default' => true, 'rules' => 'boolean'],
            ],
        ],

        'custom_labels' => [
            'label' => 'Custom Labels',
            'icon' => 'tags',
            'fields' => [
                'product_label' => ['label' => 'Product Label', 'type' => 'string', 'default' => 'Product', 'rules' => 'required|string|max:50'],
                'sale_label' => ['label' => 'Sale Label', 'type' => 'string', 'default' => 'Sale', 'rules' => 'required|string|max:50'],
                'purchase_label' => ['label' => 'Purchase Label', 'type' => 'string', 'default' => 'Purchase', 'rules' => 'required|string|max:50'],
                'customer_label' => ['label' => 'Customer Label', 'type' => 'string', 'default' => 'Customer', 'rules' => 'required|string|max:50'],
                'supplier_label' => ['label' => 'Supplier Label', 'type' => 'string', 'default' => 'Supplier', 'rules' => 'required|string|max:50'],
                'expense_label' => ['label' => 'Expense Label', 'type' => 'string', 'default' => 'Expense', 'rules' => 'required|string|max:50'],
            ],
        ],

        'api' => [
            'label' => 'VTU API',
            'icon' => 'plug-zap',
            'fields' => [
                'provider_mode' => [
                    'label' => 'Provider Mode',
                    'type' => 'enum',
                    'default' => 'auto',
                    'options' => ['auto', 'aida', 'easy_access'],
                    'rules' => 'required|in:auto,aida,easy_access',
                ],
                'aida_base_url' => [
                    'label' => 'AidaPay Base URL',
                    'type' => 'string',
                    'default' => null,
                    'rules' => 'nullable|url|max:500',
                ],
                'aida_public_key' => [
                    'label' => 'AidaPay Public Key',
                    'type' => 'string',
                    'default' => null,
                    'rules' => 'nullable|string|max:255',
                ],
                'aida_secret_key' => [
                    'label' => 'AidaPay Secret Key',
                    'type' => 'string',
                    'default' => null,
                    'sensitive' => true,
                    'rules' => 'nullable|string|max:500',
                ],
                'aida_account_pin' => [
                    'label' => 'AidaPay Account PIN',
                    'type' => 'string',
                    'default' => null,
                    'sensitive' => true,
                    'rules' => 'nullable|string|max:20',
                ],
                'easy_access_base_url' => [
                    'label' => 'EasyAccess Base URL',
                    'type' => 'string',
                    'default' => null,
                    'rules' => 'nullable|url|max:500',
                ],
                'easy_access_token' => [
                    'label' => 'EasyAccess API Token',
                    'type' => 'string',
                    'default' => null,
                    'sensitive' => true,
                    'rules' => 'nullable|string|max:500',
                ],
                'easy_access_enabled' => [
                    'label' => 'EasyAccess Enabled',
                    'type' => 'boolean',
                    'default' => false,
                    'rules' => 'boolean',
                ],
                'exchange_rate_margin' => [
                    'label' => 'Exchange Rate Margin (%)',
                    'type' => 'decimal',
                    'default' => 0,
                    'rules' => 'numeric|min:0|max:100',
                ],
                'max_requests_per_second' => [
                    'label' => 'Max Requests Per Second',
                    'type' => 'integer',
                    'default' => 5,
                    'rules' => 'integer|min:1|max:100',
                ],
            ],
        ],

        'themes' => [
            'label' => 'Themes',
            'icon' => 'palette',
            'fields' => [
                'primary_color' => [
                    'label' => 'Primary Color',
                    'type' => 'string',
                    'default' => '#2563eb',
                    'rules' => 'required|string|max:20',
                ],
                'accent_color' => [
                    'label' => 'Accent Color',
                    'type' => 'string',
                    'default' => '#f59e0b',
                    'rules' => 'required|string|max:20',
                ],
                'success_color' => [
                    'label' => 'Success Color',
                    'type' => 'string',
                    'default' => '#10b981',
                    'rules' => 'required|string|max:20',
                ],
                'mode' => [
                    'label' => 'Color Mode',
                    'type' => 'enum',
                    'default' => 'light',
                    'options' => ['light', 'dark', 'system'],
                    'rules' => 'required|in:light,dark,system',
                ],
                'radius' => [
                    'label' => 'Border Radius',
                    'type' => 'enum',
                    'default' => 'medium',
                    'options' => ['sharp', 'small', 'medium', 'large', 'full'],
                    'rules' => 'required|in:sharp,small,medium,large,full',
                ],
                'font_family' => [
                    'label' => 'Font Family',
                    'type' => 'string',
                    'default' => 'Inter',
                    'rules' => 'required|string|max:100',
                ],
            ],
        ],

        'layout' => [
            'label' => 'Layout',
            'icon' => 'layout-template',
            'fields' => [
                'sidebar_collapsed' => [
                    'label' => 'Sidebar Collapsed by Default',
                    'type' => 'boolean',
                    'default' => false,
                    'rules' => 'boolean',
                ],
                'dense_table_rows' => [
                    'label' => 'Dense Table Rows',
                    'type' => 'boolean',
                    'default' => true,
                    'rules' => 'boolean',
                ],
                'max_items_per_page' => [
                    'label' => 'Max Items Per Page',
                    'type' => 'integer',
                    'default' => 50,
                    'rules' => 'integer|min:10|max:500',
                ],
                'currency_position' => [
                    'label' => 'Currency Position',
                    'type' => 'enum',
                    'default' => 'before',
                    'options' => ['before', 'after'],
                    'rules' => 'required|in:before,after',
                ],
                'default_page' => [
                    'label' => 'Default Landing Page',
                    'type' => 'enum',
                    'default' => 'dashboard',
                    'options' => ['dashboard', 'transactions', 'services'],
                    'rules' => 'required|in:dashboard,transactions,services',
                ],
            ],
        ],

        'security' => [
            'label' => 'Security',
            'icon' => 'shield',
            'fields' => [
                'session_timeout_minutes' => [
                    'label' => 'Session Timeout (Minutes)',
                    'type' => 'integer',
                    'default' => 60,
                    'rules' => 'required|integer|min:5|max:10080',
                ],
                'token_expiry_minutes' => [
                    'label' => 'API Token Expiry (Minutes)',
                    'type' => 'integer',
                    'default' => 120,
                    'rules' => 'required|integer|min:5|max:43200',
                ],
                'max_failed_logins' => [
                    'label' => 'Max Failed Login Attempts',
                    'type' => 'integer',
                    'default' => 5,
                    'rules' => 'required|integer|min:1|max:100',
                ],
                'lockout_duration_minutes' => [
                    'label' => 'Lockout Duration (Minutes)',
                    'type' => 'integer',
                    'default' => 15,
                    'rules' => 'required|integer|min:1|max:1440',
                ],
                'require_strong_password' => [
                    'label' => 'Require Strong Password',
                    'type' => 'boolean',
                    'default' => true,
                    'rules' => 'boolean',
                ],
                'enable_login_captcha' => [
                    'label' => 'Enable Login Captcha',
                    'type' => 'boolean',
                    'default' => false,
                    'rules' => 'boolean',
                ],
            ],
        ],

        'notifications' => [
            'label' => 'Notifications',
            'icon' => 'bell',
            'fields' => [
                'notify_email' => [
                    'label' => 'Email Notifications',
                    'type' => 'boolean',
                    'default' => true,
                    'rules' => 'boolean',
                ],
                'notify_sms' => [
                    'label' => 'SMS Notifications',
                    'type' => 'boolean',
                    'default' => true,
                    'rules' => 'boolean',
                ],
                'notify_push' => [
                    'label' => 'Push Notifications',
                    'type' => 'boolean',
                    'default' => false,
                    'rules' => 'boolean',
                ],
                'topup_confirmation' => [
                    'label' => 'Top-Up Confirmation Alert',
                    'type' => 'boolean',
                    'default' => true,
                    'rules' => 'boolean',
                ],
                'wallet_debit_alert' => [
                    'label' => 'Wallet Debit Alert',
                    'type' => 'boolean',
                    'default' => true,
                    'rules' => 'boolean',
                ],
                'weekly_digest' => [
                    'label' => 'Weekly Summary Digest',
                    'type' => 'boolean',
                    'default' => false,
                    'rules' => 'boolean',
                ],
            ],
        ],

        'support' => [
            'label' => 'Support',
            'icon' => 'headphones',
            'fields' => [
                'support_email' => [
                    'label' => 'Support Email',
                    'type' => 'string',
                    'default' => null,
                    'rules' => 'nullable|email|max:255',
                ],
                'support_phone' => [
                    'label' => 'Support Phone',
                    'type' => 'string',
                    'default' => null,
                    'rules' => 'nullable|string|max:30',
                ],
                'whatsapp_number' => [
                    'label' => 'WhatsApp Number',
                    'type' => 'string',
                    'default' => null,
                    'rules' => 'nullable|string|max:30',
                ],
                'help_center_url' => [
                    'label' => 'Help Center URL',
                    'type' => 'string',
                    'default' => null,
                    'rules' => 'nullable|url|max:500',
                ],
                'office_hours' => [
                    'label' => 'Office Hours',
                    'type' => 'string',
                    'default' => null,
                    'rules' => 'nullable|string|max:120',
                ],
            ],
        ],
    ],

    'public_groups' => [
        'business',
        'system',
        'themes',
        'layout',
        'support',
        'notifications',
    ],
];
