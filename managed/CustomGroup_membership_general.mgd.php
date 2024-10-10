<?php

use CRM_Contract_ExtensionUtil as E;

return [
  [
    'name' => 'CustomGroup_membership_general',
    'entity' => 'CustomGroup',
    'cleanup' => 'never',
    'update' => 'always',
    'params' => [
      'version' => 4,
      'values' => [
        'name' => 'membership_general',
        'title' => E::ts('General Information'),
        'extends' => 'Membership',
        'style' => 'Inline',
        'weight' => 31,
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
    'name' => 'CustomGroup_membership_general_CustomField_membership_contract',
    'entity' => 'CustomField',
    'cleanup' => 'never',
    'update' => 'always',
    'params' => [
      'version' => 4,
      'values' => [
        'custom_group_id.name' => 'membership_general',
        'name' => 'membership_contract',
        'label' => E::ts('Contract Number'),
        'html_type' => 'Text',
        'is_searchable' => TRUE,
        'text_length' => 24,
        'column_name' => 'membership_contract',
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
    'name' => 'CustomGroup_membership_general_CustomField_membership_reference',
    'entity' => 'CustomField',
    'cleanup' => 'never',
    'update' => 'always',
    'params' => [
      'version' => 4,
      'values' => [
        'custom_group_id.name' => 'membership_general',
        'name' => 'membership_reference',
        'label' => E::ts('Reference Number'),
        'html_type' => 'Text',
        'is_searchable' => TRUE,
        'text_length' => 24,
        'column_name' => 'membership_reference',
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
    'name' => 'CustomGroup_membership_general_CustomField_membership_channel',
    'entity' => 'CustomField',
    'cleanup' => 'never',
    'update' => 'always',
    'params' => [
      'version' => 4,
      'values' => [
        'custom_group_id.name' => 'membership_general',
        'name' => 'membership_channel',
        'label' => E::ts('Membership Channel'),
        'html_type' => 'Select',
        'is_searchable' => TRUE,
        'is_search_range' => TRUE,
        'column_name' => 'membership_channel',
        'option_group_id.name' => 'contact_channel',
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
    'name' => 'CustomGroup_membership_general_CustomField_membership_dialoger',
    'entity' => 'CustomField',
    'cleanup' => 'never',
    'update' => 'always',
    'params' => [
      'version' => 4,
      'values' => [
        'custom_group_id.name' => 'membership_general',
        'name' => 'membership_dialoger',
        'label' => E::ts('Dialoger'),
        'data_type' => 'ContactReference',
        'html_type' => 'Autocomplete-Select',
        'is_searchable' => TRUE,
        'is_search_range' => TRUE,
        'column_name' => 'membership_dialoger',
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
    'name' => 'CustomGroup_membership_general_CustomField_contract_file',
    'entity' => 'CustomField',
    'cleanup' => 'never',
    'update' => 'always',
    'params' => [
      'version' => 4,
      'values' => [
        'custom_group_id.name' => 'membership_general',
        'name' => 'contract_file',
        'label' => E::ts('Contract File'),
        'data_type' => 'File',
        'html_type' => 'File',
        'column_name' => 'contract_file',
        'is_active' => TRUE,
      ],
      'match' => [
        'custom_group_id',
        'name',
      ],
    ],
  ],
];
