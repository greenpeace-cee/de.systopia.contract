<?php

use CRM_Contract_ExtensionUtil as E;

return [
  [
    'name' => 'CustomGroup_membership_referral',
    'entity' => 'CustomGroup',
    'cleanup' => 'never',
    'update' => 'always',
    'params' => [
      'version' => 4,
      'values' => [
        'name' => 'membership_referral',
        'title' => E::ts('Referral Information'),
        'extends' => 'Membership',
        'style' => 'Inline',
        'help_pre' => '',
        'help_post' => '',
        'collapse_adv_display' => TRUE,
      ],
      'match' => [
        'name',
      ],
    ],
  ],
  [
    'name' => 'CustomGroup_membership_referral_CustomField_membership_referrer',
    'entity' => 'CustomField',
    'cleanup' => 'never',
    'update' => 'always',
    'params' => [
      'version' => 4,
      'values' => [
        'custom_group_id.name' => 'membership_referral',
        'name' => 'membership_referrer',
        'label' => E::ts('Referrer'),
        'data_type' => 'ContactReference',
        'html_type' => 'Autocomplete-Select',
        'is_searchable' => TRUE,
        'column_name' => 'membership_referrer',
        'filter' => 'action=lookup&group=',
      ],
      'match' => [
        'custom_group_id',
        'name',
      ],
    ],
  ],
];