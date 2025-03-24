<?php

use CRM_Contract_ExtensionUtil as E;

$activity_type_contract_cancelled = CRM_Core_PseudoConstant::getKey(
  'CRM_Activity_BAO_Activity',
  'activity_type_id',
  'Contract_Cancelled'
);

return [
  [
    'name' => 'CustomGroup_contract_cancellation',
    'entity' => 'CustomGroup',
    'cleanup' => 'never',
    'update' => 'always',
    'params' => [
      'version' => 4,
      'values' => [
        'name' => 'contract_cancellation',
        'title' => E::ts('Contract Cancellation'),
        'extends' => 'Activity',
        'extends_entity_column_value' => [
          $activity_type_contract_cancelled,
        ],
        'style' => 'Inline',
        'collapse_display' => TRUE,
        'weight' => 6,
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
    'name' => 'CustomGroup_contract_cancellation_CustomField_contact_history_cancel_reason',
    'entity' => 'CustomField',
    'cleanup' => 'never',
    'update' => 'always',
    'params' => [
      'version' => 4,
      'values' => [
        'custom_group_id.name' => 'contract_cancellation',
        'name' => 'contact_history_cancel_reason',
        'label' => E::ts('Cancel Reason'),
        'html_type' => 'Select',
        'is_searchable' => TRUE,
        'is_search_range' => FALSE,
        'column_name' => 'contact_history_cancel_reason',
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
];
