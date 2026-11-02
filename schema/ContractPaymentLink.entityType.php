<?php
use CRM_Contract_ExtensionUtil as E;

return [
  'name' => 'ContractPaymentLink',
  'table' => 'civicrm_contract_payment',
  'class' => 'CRM_Contract_DAO_ContractPaymentLink',
  'getInfo' => fn() => [
    'title' => E::ts('Contract Payment Link'),
    'title_plural' => E::ts('Contract Payment Links'),
    'description' => E::ts('Links a contract to a recurring contribution'),
    'log' => TRUE,
  ],
  'getIndices' => fn() => [
    'contract_id' => [
      'fields' => [
        'contract_id' => TRUE,
      ],
    ],
    'contribution_recur_id' => [
      'fields' => [
        'contribution_recur_id' => TRUE,
      ],
    ],
    'is_active' => [
      'fields' => [
        'is_active' => TRUE,
      ],
    ],
    'start_date' => [
      'fields' => [
        'start_date' => TRUE,
      ],
    ],
    'end_date' => [
      'fields' => [
        'end_date' => TRUE,
      ],
    ],
  ],
  'getFields' => fn() => [
    'id' => [
      'title' => E::ts('ID'),
      'sql_type' => 'int unsigned',
      'input_type' => 'Number',
      'required' => TRUE,
      'description' => E::ts('Unique ContractPaymentLink ID'),
      'primary_key' => TRUE,
      'auto_increment' => TRUE,
    ],
    'contract_id' => [
      'title' => E::ts('Contract ID'),
      'sql_type' => 'int unsigned',
      'input_type' => 'EntityRef',
      'required' => TRUE,
      'description' => E::ts('FK to Membership ID'),
      'entity_reference' => [
        'entity' => 'Membership',
        'key' => 'id',
        'on_delete' => 'CASCADE',
      ],
    ],
    'contribution_recur_id' => [
      'title' => E::ts('ContributionRecur ID'),
      'sql_type' => 'int unsigned',
      'input_type' => 'EntityRef',
      'required' => TRUE,
      'description' => E::ts('FK to civicrm_contribution_recur'),
      'entity_reference' => [
        'entity' => 'ContributionRecur',
        'key' => 'id',
        'on_delete' => 'CASCADE',
      ],
    ],
    'is_active' => [
      'title' => E::ts('Is Active'),
      'sql_type' => 'boolean',
      'input_type' => 'CheckBox',
      'required' => TRUE,
      'description' => E::ts('Is this link still active?'),
      'default' => TRUE,
    ],
    'creation_date' => [
      'title' => E::ts('Creation Date'),
      'sql_type' => 'datetime',
      'input_type' => 'Select Date',
      'required' => TRUE,
      'description' => E::ts('Link creation date'),
    ],
    'start_date' => [
      'title' => E::ts('Start Date'),
      'sql_type' => 'datetime',
      'input_type' => 'Select Date',
      'description' => E::ts('Start date of the link (optional)'),
    ],
    'end_date' => [
      'title' => E::ts('End Date'),
      'sql_type' => 'datetime',
      'input_type' => 'Select Date',
      'description' => E::ts('End date of the link (optional)'),
    ],
  ],
];
