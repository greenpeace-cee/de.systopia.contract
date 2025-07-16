<?php

use CRM_Contract_ExtensionUtil as E;

return [
  [
    'name' => 'MembershipStatus_New',
    'entity' => 'MembershipStatus',
    'cleanup' => 'never',
    'update' => 'always',
    'params' => [
      'version' => 4,
      'values' => [
        'name' => 'New',
        'label' => E::ts('New'),
        'start_event' => 'join_date',
        'end_event' => 'join_date',
        'end_event_adjust_unit' => 'month',
        'end_event_adjust_interval' => 3,
        'is_current_member' => TRUE,
        'weight' => 1,
        'is_active' => FALSE,
      ],
      'match' => [
        'name',
      ],
    ],
  ],
  [
    'name' => 'MembershipStatus_Current',
    'entity' => 'MembershipStatus',
    'cleanup' => 'never',
    'update' => 'always',
    'params' => [
      'version' => 4,
      'values' => [
        'name' => 'Current',
        'label' => E::ts('Current'),
        'start_event' => 'join_date',
        'end_event' => 'end_date',
        'is_current_member' => TRUE,
        'weight' => 2,
        'is_active' => TRUE,
      ],
      'match' => [
        'name',
      ],
    ],
  ],
  [
    'name' => 'MembershipStatus_Pending',
    'entity' => 'MembershipStatus',
    'cleanup' => 'never',
    'update' => 'always',
    'params' => [
      'version' => 4,
      'values' => [
        'name' => 'Pending',
        'label' => E::ts('Pending'),
        'start_event' => 'join_date',
        'end_event' => 'join_date',
        'is_current_member' => TRUE,
        'weight' => 5,
        'is_reserved' => TRUE,
        'is_active' => TRUE,
      ],
      'match' => [
        'name',
      ],
    ],
  ],
  [
    'name' => 'MembershipStatus_Cancelled',
    'entity' => 'MembershipStatus',
    'cleanup' => 'never',
    'update' => 'always',
    'params' => [
      'version' => 4,
      'values' => [
        'name' => 'Cancelled',
        'label' => E::ts('Cancelled'),
        'start_event' => 'end_date',
        'end_event' => NULL,
        'is_current_member' => FALSE,
        'weight' => 6,
        'is_reserved' => TRUE,
        'is_active' => TRUE,
      ],
    ],
  ],
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
