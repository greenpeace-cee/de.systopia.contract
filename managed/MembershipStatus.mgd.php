<?php

use CRM_Contract_ExtensionUtil as E;

return [
  [
    'name' => 'MembershipStatus_Paused',
    'entity' => 'MembershipStatus',
    'cleanup' => 'never',
    'update' => 'always',
    'params' => [
      'version' => 4,
      'values' => [
        'name' => 'Paused',
        'label' => E::ts('Paused'),
        'is_current_member' => TRUE,
        'is_active' => TRUE,
      ],
      'match' => [
        'name',
      ],
    ],
  ],
];
