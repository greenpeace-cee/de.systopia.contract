<?php

use CRM_Contract_ExtensionUtil as E;

return [
  [
    'name' => 'OptionGroup_contract_cancel_reason',
    'entity' => 'OptionGroup',
    'cleanup' => 'never',
    'update' => 'always',
    'params' => [
      'version' => 4,
      'values' => [
        'name' => 'contract_cancel_reason',
        'title' => E::ts('Contract Cancel Reason'),
        'description' => E::ts('Dropdown options for Contract cancellation reasons'),
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
    'name' => 'OptionGroup_contract_cancel_reason_OptionValue_Unknown',
    'entity' => 'OptionValue',
    'cleanup' => 'never',
    'update' => 'always',
    'params' => [
      'version' => 4,
      'values' => [
        'option_group_id.name' => 'contract_cancel_reason',
        'label' => E::ts('Unknown'),
        'value' => '1',
        'name' => 'Unknown',
        'is_active' => FALSE,
      ],
      'match' => [
        'name',
        'option_group_id',
      ],
    ],
  ],
];
