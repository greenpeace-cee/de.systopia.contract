<?php

use CRM_Contract_ExtensionUtil as E;

return [
  [
    'name' => 'Tag_contract_cancellation',
    'entity' => 'Tag',
    'cleanup' => 'never',
    'update' => 'always',
    'params' => [
      'version' => 4,
      'values' => [
        'name' => 'contract_cancellation',
        'label' => E::ts('Contract Cancellation'),
        'description' => E::ts('Tag Set for contract cancellation activities'),
        'is_reserved' => TRUE,
        'is_tagset' => FALSE,
        'used_for' => [
          'civicrm_activity',
        ],
      ],
      'match' => [
        'name',
      ],
    ],
  ],
];
