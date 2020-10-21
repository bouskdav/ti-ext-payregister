<?php

return [
    'fields' => [
        'transaction_mode' => [
            'label' => 'lang:igniter.payregister::default.gpwebpay.label_transaction_mode',
            'type' => 'radiotoggle',
            'default' => 'test',
            'options' => [
                'test' => 'lang:igniter.payregister::default.gpwebpay.text_test',
                'live' => 'lang:igniter.payregister::default.gpwebpay.text_live',
            ],
        ],
		'merchant_number' => [
            'label' => 'lang:igniter.payregister::default.gpwebpay.merchant_number',
            'type' => 'text',
        ],
        'live_secret_key' => [
            'label' => 'lang:igniter.payregister::default.gpwebpay.label_live_secret_key',
            'type' => 'textarea',
            'trigger' => [
                'action' => 'show',
                'field' => 'transaction_mode',
                'condition' => 'value[live]',
            ],
        ],
        'live_publishable_key' => [
            'label' => 'lang:igniter.payregister::default.gpwebpay.label_live_publishable_key',
            'type' => 'textarea',
            'trigger' => [
                'action' => 'show',
                'field' => 'transaction_mode',
                'condition' => 'value[live]',
            ],
        ],
		'live_secret_key_password' => [
            'label' => 'lang:igniter.payregister::default.gpwebpay.label_live_secret_key_password',
            'type' => 'text',
            'trigger' => [
                'action' => 'show',
                'field' => 'transaction_mode',
                'condition' => 'value[live]',
            ],
        ],
        'test_secret_key' => [
            'label' => 'lang:igniter.payregister::default.gpwebpay.label_test_secret_key',
            'type' => 'textarea',
            'trigger' => [
                'action' => 'show',
                'field' => 'transaction_mode',
                'condition' => 'value[test]',
            ],
        ],
        'test_publishable_key' => [
            'label' => 'lang:igniter.payregister::default.gpwebpay.label_test_publishable_key',
            'type' => 'textarea',
            'trigger' => [
                'action' => 'show',
                'field' => 'transaction_mode',
                'condition' => 'value[test]',
            ],
        ],
		'test_secret_key_password' => [
            'label' => 'lang:igniter.payregister::default.gpwebpay.label_test_secret_key_password',
            'type' => 'text',
            'trigger' => [
                'action' => 'show',
                'field' => 'transaction_mode',
                'condition' => 'value[test]',
            ],
        ],
        'order_fee_type' => [
            'label' => 'lang:igniter.payregister::default.label_order_fee_type',
            'type' => 'radiotoggle',
            'span' => 'left',
            'default' => 1,
            'options' => [
                1 => 'lang:admin::lang.coupons.text_fixed_amount',
                2 => 'lang:admin::lang.coupons.text_percentage',
            ],
        ],
        'order_fee' => [
            'label' => 'lang:igniter.payregister::default.label_order_fee',
            'type' => 'number',
            'span' => 'right',
            'comment' => 'lang:igniter.payregister::default.help_order_fee',
        ],
        'order_total' => [
            'label' => 'lang:igniter.payregister::default.label_order_total',
            'type' => 'currency',
            'span' => 'left',
            'comment' => 'lang:igniter.payregister::default.help_order_total',
        ],
        'order_status' => [
            'label' => 'lang:igniter.payregister::default.label_order_status',
            'type' => 'select',
            'options' => ['Admin\Models\Statuses_model', 'getDropdownOptionsForOrder'],
            'span' => 'right',
            'comment' => 'lang:igniter.payregister::default.help_order_status',
        ],
    ],
    'rules' => [
        ['transaction_mode', 'lang:igniter.payregister::default.gpwebpay.label_transaction_mode', 'string'],
        ['merchant_number', 'lang:igniter.payregister::default.gpwebpay.merchant_number', 'string'],
        ['live_secret_key', 'lang:igniter.payregister::default.gpwebpay.label_live_secret_key', 'string'],
        ['live_secret_key_password', 'lang:igniter.payregister::default.gpwebpay.label_live_secret_key_password', 'string'],
        ['live_publishable_key', 'lang:igniter.payregister::default.gpwebpay.label_live_publishable_key', 'string'],
        ['test_secret_key', 'lang:igniter.payregister::default.gpwebpay.label_test_secret_key', 'string'],
        ['test_secret_key_password', 'lang:igniter.payregister::default.gpwebpay.label_test_secret_key_password', 'string'],
        ['test_publishable_key', 'lang:igniter.payregister::default.gpwebpay.label_test_publishable_key', 'string'],
        ['order_fee_type', 'lang:igniter.payregister::default.label_order_fee_type', 'integer'],
        ['order_fee', 'lang:igniter.payregister::default.label_order_fee', 'numeric'],
        ['order_total', 'lang:igniter.payregister::default.label_order_total', 'numeric'],
        ['order_status', 'lang:igniter.payregister::default.label_order_status', 'integer'],
    ],
];
