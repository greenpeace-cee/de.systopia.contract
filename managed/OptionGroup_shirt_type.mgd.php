<?php

use CRM_Contract_ExtensionUtil as E;

return [
  [
    'name' => 'OptionGroup_shirt_type',
    'entity' => 'OptionGroup',
    'cleanup' => 'never',
    'update' => 'always',
    'params' => [
      'version' => 4,
      'values' => [
        'name' => 'shirt_type',
        'title' => E::ts('T-Shirt Type Options'),
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
    'name' => 'OptionGroup_shirt_type_OptionValue_Herren',
    'entity' => 'OptionValue',
    'cleanup' => 'never',
    'update' => 'always',
    'params' => [
      'version' => 4,
      'values' => [
        'option_group_id.name' => 'shirt_type',
        'label' => E::ts('Herren'),
        'value' => 'M',
        'name' => 'Herren',
        'is_active' => TRUE,
      ],
      'match' => [
        'name',
        'option_group_id',
      ],
    ],
  ],
  [
    'name' => 'OptionGroup_shirt_type_OptionValue_Damen',
    'entity' => 'OptionValue',
    'cleanup' => 'never',
    'update' => 'always',
    'params' => [
      'version' => 4,
      'values' => [
        'option_group_id.name' => 'shirt_type',
        'label' => E::ts('Damen'),
        'value' => 'W',
        'name' => 'Damen',
        'is_active' => TRUE,
      ],
      'match' => [
        'name',
        'option_group_id',
      ],
    ],
  ],
];
