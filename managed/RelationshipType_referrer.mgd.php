<?php

use CRM_Contract_ExtensionUtil as E;

return [
  [
    'name' => 'RelationshipType_Referrer',
    'entity' => 'RelationshipType',
    'cleanup' => 'never',
    'update' => 'always',
    'params' => [
      'version' => 4,
      'values' => [
        'name_a_b' => 'Referrer of',
        'label_a_b' => E::ts('Referrer of'),
        'name_b_a' => 'Referred by',
        'label_b_a' => E::ts('Referred by'),
        'contact_type_a' => 'Individual',
        'contact_type_b' => 'Individual',
      ],
      'match' => [
        'name_a_b',
        'name_b_a',
      ],
    ],
  ],
];