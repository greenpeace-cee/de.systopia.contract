<?php

use CRM_Contract_ExtensionUtil as E;

return [
  [
    'name' => 'OptionGroup_payment_frequency',
    'entity' => 'OptionGroup',
    'cleanup' => 'never',
    'update' => 'always',
    'params' => [
      'version' => 4,
      'values' => [
        'name' => 'payment_frequency',
        'title' => E::ts('Payment Intervals'),
        'description' => E::ts('The value describes the payment interval in months'),
        'is_reserved' => FALSE,
        'is_active' => TRUE,
        'option_value_fields' => [
          'name',
          'label',
          'description',
        ],
      ],
      'match' => [
        'name',
      ],
    ],
  ],
  [
    'name' => 'OptionGroup_payment_frequency_OptionValue_one_off',
    'entity' => 'OptionValue',
    'cleanup' => 'never',
    'update' => 'always',
    'params' => [
      'version' => 4,
      'values' => [
        'option_group_id.name' => 'payment_frequency',
        'label' => E::ts('one-off'),
        'value' => '0',
        'name' => 'one-off',
        'is_active' => TRUE,
      ],
      'match' => [
        'name',
        'option_group_id',
      ],
    ],
  ],
  [
    'name' => 'OptionGroup_payment_frequency_OptionValue_annually',
    'entity' => 'OptionValue',
    'cleanup' => 'never',
    'update' => 'always',
    'params' => [
      'version' => 4,
      'values' => [
        'option_group_id.name' => 'payment_frequency',
        'label' => E::ts('annually'),
        'value' => '1',
        'name' => 'annually',
        'is_active' => TRUE,
      ],
      'match' => [
        'name',
        'option_group_id',
      ],
    ],
  ],
  [
    'name' => 'OptionGroup_payment_frequency_OptionValue_semi_annually',
    'entity' => 'OptionValue',
    'cleanup' => 'never',
    'update' => 'always',
    'params' => [
      'version' => 4,
      'values' => [
        'option_group_id.name' => 'payment_frequency',
        'label' => E::ts('semi-annually'),
        'value' => '2',
        'name' => 'semi-annually',
        'is_active' => TRUE,
      ],
      'match' => [
        'name',
        'option_group_id',
      ],
    ],
  ],
  [
    'name' => 'OptionGroup_payment_frequency_OptionValue_trimestral',
    'entity' => 'OptionValue',
    'cleanup' => 'never',
    'update' => 'always',
    'params' => [
      'version' => 4,
      'values' => [
        'option_group_id.name' => 'payment_frequency',
        'label' => E::ts('trimestral'),
        'value' => '3',
        'name' => 'trimestral',
        'is_active' => FALSE,
      ],
      'match' => [
        'name',
        'option_group_id',
      ],
    ],
  ],
  [
    'name' => 'OptionGroup_payment_frequency_OptionValue_quarterly',
    'entity' => 'OptionValue',
    'cleanup' => 'never',
    'update' => 'always',
    'params' => [
      'version' => 4,
      'values' => [
        'option_group_id.name' => 'payment_frequency',
        'label' => E::ts('quarterly'),
        'value' => '4',
        'name' => 'quarterly',
        'is_active' => TRUE,
      ],
      'match' => [
        'name',
        'option_group_id',
      ],
    ],
  ],
  [
    'name' => 'OptionGroup_payment_frequency_OptionValue_bi_monthly',
    'entity' => 'OptionValue',
    'cleanup' => 'never',
    'update' => 'always',
    'params' => [
      'version' => 4,
      'values' => [
        'option_group_id.name' => 'payment_frequency',
        'label' => E::ts('bi-monthly'),
        'value' => '6',
        'name' => 'bi-monthly',
        'is_active' => TRUE,
      ],
      'match' => [
        'name',
        'option_group_id',
      ],
    ],
  ],
  [
    'name' => 'OptionGroup_payment_frequency_OptionValue_monthly',
    'entity' => 'OptionValue',
    'cleanup' => 'never',
    'update' => 'always',
    'params' => [
      'version' => 4,
      'values' => [
        'option_group_id.name' => 'payment_frequency',
        'label' => E::ts('monthly'),
        'value' => '12',
        'name' => 'monthly',
        'is_active' => TRUE,
      ],
      'match' => [
        'name',
        'option_group_id',
      ],
    ],
  ],
];
