<?php

use CRM_Contract_ExtensionUtil as E;

return [
  [
    'name' => 'OptionGroup_activity_type_OptionValue_Contract_Signed',
    'entity' => 'OptionValue',
    'cleanup' => 'never',
    'update' => 'always',
    'params' => [
      'version' => 4,
      'values' => [
        'option_group_id.name' => 'activity_type',
        'label' => E::ts('Sign Contract'),
        'value' => '113',
        'name' => 'Contract_Signed',
        'is_reserved' => TRUE,
        'is_active' => TRUE,
      ],
      'match' => [
        'name',
        'option_group_id',
      ],
    ],
  ],
  [
    'name' => 'OptionGroup_activity_type_OptionValue_Contract_Paused',
    'entity' => 'OptionValue',
    'cleanup' => 'never',
    'update' => 'always',
    'params' => [
      'version' => 4,
      'values' => [
        'option_group_id.name' => 'activity_type',
        'label' => E::ts('Pause Contract'),
        'value' => '114',
        'name' => 'Contract_Paused',
        'is_reserved' => TRUE,
        'is_active' => TRUE,
      ],
      'match' => [
        'name',
        'option_group_id',
      ],
    ],
  ],
  [
    'name' => 'OptionGroup_activity_type_OptionValue_Contract_Resumed',
    'entity' => 'OptionValue',
    'cleanup' => 'never',
    'update' => 'always',
    'params' => [
      'version' => 4,
      'values' => [
        'option_group_id.name' => 'activity_type',
        'label' => E::ts('Resume Contract'),
        'value' => '115',
        'name' => 'Contract_Resumed',
        'is_reserved' => TRUE,
        'is_active' => TRUE,
      ],
      'match' => [
        'name',
        'option_group_id',
      ],
    ],
  ],
  [
    'name' => 'OptionGroup_activity_type_OptionValue_Contract_Updated',
    'entity' => 'OptionValue',
    'cleanup' => 'never',
    'update' => 'always',
    'params' => [
      'version' => 4,
      'values' => [
        'option_group_id.name' => 'activity_type',
        'label' => E::ts('Update Contract'),
        'value' => '116',
        'name' => 'Contract_Updated',
        'is_reserved' => TRUE,
        'is_active' => TRUE,
      ],
      'match' => [
        'name',
        'option_group_id',
      ],
    ],
  ],
  [
    'name' => 'OptionGroup_activity_type_OptionValue_Contract_Cancelled',
    'entity' => 'OptionValue',
    'cleanup' => 'never',
    'update' => 'always',
    'params' => [
      'version' => 4,
      'values' => [
        'option_group_id.name' => 'activity_type',
        'label' => E::ts('Cancel Contract'),
        'value' => '117',
        'name' => 'Contract_Cancelled',
        'is_reserved' => TRUE,
        'is_active' => TRUE,
      ],
      'match' => [
        'name',
        'option_group_id',
      ],
    ],
  ],
  [
    'name' => 'OptionGroup_activity_type_OptionValue_Contract_Revived',
    'entity' => 'OptionValue',
    'cleanup' => 'never',
    'update' => 'always',
    'params' => [
      'version' => 4,
      'values' => [
        'option_group_id.name' => 'activity_type',
        'label' => E::ts('Revive Contract'),
        'value' => '118',
        'name' => 'Contract_Revived',
        'is_reserved' => TRUE,
        'is_active' => TRUE,
      ],
      'match' => [
        'name',
        'option_group_id',
      ],
    ],
  ],
];
