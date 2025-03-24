<?php

use CRM_Contract_ExtensionUtil as E;

return [
  [
    'name' => 'CustomGroup_membership_cancellation',
    'entity' => 'CustomGroup',
    'cleanup' => 'never',
    'update' => 'always',
    'params' => [
      'version' => 4,
      'values' => [
        'name' => 'membership_cancellation',
        'title' => E::ts('Cancellation Information'),
        'extends' => 'Membership',
        'style' => 'Inline',
        'weight' => 4,
        'collapse_adv_display' => TRUE,
        'is_active' => TRUE,
      ],
      'match' => [
        'extends',
        'name',
      ],
    ],
  ],
  [
    'name' => 'CustomGroup_membership_cancellation_CustomField_membership_cancel_reason',
    'entity' => 'CustomField',
    'cleanup' => 'never',
    'update' => 'always',
    'params' => [
      'version' => 4,
      'values' => [
        'custom_group_id.name' => 'membership_cancellation',
        'name' => 'membership_cancel_reason',
        'label' => E::ts('Cancel Reason'),
        'html_type' => 'Select',
        'is_searchable' => TRUE,
        'is_search_range' => FALSE,
        'column_name' => 'membership_cancel_reason',
        'option_group_id.name' => 'contract_cancel_reason',
        'in_selector' => TRUE,
        'is_active' => TRUE,
      ],
      'match' => [
        'custom_group_id',
        'name',
      ],
    ],
  ],
  [
    'name' => 'CustomGroup_membership_cancellation_CustomField_membership_cancel_date',
    'entity' => 'CustomField',
    'cleanup' => 'never',
    'update' => 'always',
    'params' => [
      'version' => 4,
      'values' => [
        'custom_group_id.name' => 'membership_cancellation',
        'name' => 'membership_cancel_date',
        'label' => E::ts('Cancellation Date'),
        'data_type' => 'Date',
        'html_type' => 'Select Date',
        'is_searchable' => TRUE,
        'is_search_range' => TRUE,
        'date_format' => 'mm/dd/yy',
        'column_name' => 'membership_cancel_date',
        'in_selector' => TRUE,
        'is_active' => TRUE,
      ],
      'match' => [
        'custom_group_id',
        'name',
      ],
    ],
  ],
];
