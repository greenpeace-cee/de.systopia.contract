<?php

use CRM_Contract_ExtensionUtil as E;

return [
  [
    'name' => 'OptionGroup_contact_channel',
    'entity' => 'OptionGroup',
    'cleanup' => 'never',
    'update' => 'always',
    'params' => [
      'version' => 4,
      'values' => [
        'name' => 'contact_channel',
        'title' => E::ts('Channel'),
        'description' => E::ts('IMB Konaktart'),
        'is_active' => TRUE,
        'option_value_fields' => [
          'name',
          'label',
          'description',
        ],
      ],
      'match' => [
        'name',
      ],
    ],
  ],
  [
    'name' => 'OptionGroup_contact_channel_OptionValue_F2F',
    'entity' => 'OptionValue',
    'cleanup' => 'never',
    'update' => 'always',
    'params' => [
      'version' => 4,
      'values' => [
        'option_group_id.name' => 'contact_channel',
        'label' => E::ts('F2F'),
        'value' => 'F2F',
        'name' => 'F2F',
        'is_active' => TRUE,
      ],
      'match' => [
        'name',
        'option_group_id',
      ],
    ],
  ],
  [
    'name' => 'OptionGroup_contact_channel_OptionValue_D2D',
    'entity' => 'OptionValue',
    'cleanup' => 'never',
    'update' => 'always',
    'params' => [
      'version' => 4,
      'values' => [
        'option_group_id.name' => 'contact_channel',
        'label' => E::ts('D2D'),
        'value' => 'D2D',
        'name' => 'D2D',
        'is_active' => TRUE,
      ],
      'match' => [
        'name',
        'option_group_id',
      ],
    ],
  ],
  [
    'name' => 'OptionGroup_contact_channel_OptionValue_Event',
    'entity' => 'OptionValue',
    'cleanup' => 'never',
    'update' => 'always',
    'params' => [
      'version' => 4,
      'values' => [
        'option_group_id.name' => 'contact_channel',
        'label' => E::ts('Event'),
        'value' => 'Event',
        'name' => 'Event',
        'is_active' => TRUE,
      ],
      'match' => [
        'name',
        'option_group_id',
      ],
    ],
  ],
  [
    'name' => 'OptionGroup_contact_channel_OptionValue_Handel',
    'entity' => 'OptionValue',
    'cleanup' => 'never',
    'update' => 'always',
    'params' => [
      'version' => 4,
      'values' => [
        'option_group_id.name' => 'contact_channel',
        'label' => E::ts('Handel'),
        'value' => 'Handel',
        'name' => 'Handel',
        'is_active' => TRUE,
      ],
      'match' => [
        'name',
        'option_group_id',
      ],
    ],
  ],
  [
    'name' => 'OptionGroup_contact_channel_OptionValue_VR',
    'entity' => 'OptionValue',
    'cleanup' => 'never',
    'update' => 'always',
    'params' => [
      'version' => 4,
      'values' => [
        'option_group_id.name' => 'contact_channel',
        'label' => E::ts('VR'),
        'value' => 'VR',
        'name' => 'VR',
        'is_active' => TRUE,
      ],
      'match' => [
        'name',
        'option_group_id',
      ],
    ],
  ],
  [
    'name' => 'OptionGroup_contact_channel_OptionValue_DD_TFR',
    'entity' => 'OptionValue',
    'cleanup' => 'never',
    'update' => 'always',
    'params' => [
      'version' => 4,
      'values' => [
        'option_group_id.name' => 'contact_channel',
        'label' => E::ts('DD-TFR'),
        'value' => 'DD-TFR',
        'name' => 'DD-TFR',
        'is_active' => TRUE,
      ],
      'match' => [
        'name',
        'option_group_id',
      ],
    ],
  ],
  [
    'name' => 'OptionGroup_contact_channel_OptionValue_Stand_F2F',
    'entity' => 'OptionValue',
    'cleanup' => 'never',
    'update' => 'always',
    'params' => [
      'version' => 4,
      'values' => [
        'option_group_id.name' => 'contact_channel',
        'label' => E::ts('Stand F2F'),
        'value' => 'Stand F2F',
        'name' => 'Stand_F2F',
        'is_active' => TRUE,
      ],
      'match' => [
        'name',
        'option_group_id',
      ],
    ],
  ],
  [
    'name' => 'OptionGroup_contact_channel_OptionValue_Unknown',
    'entity' => 'OptionValue',
    'cleanup' => 'never',
    'update' => 'always',
    'params' => [
      'version' => 4,
      'values' => [
        'option_group_id.name' => 'contact_channel',
        'label' => E::ts('Unknown'),
        'value' => '1',
        'name' => 'Unknown',
        'is_active' => TRUE,
      ],
      'match' => [
        'name',
        'option_group_id',
      ],
    ],
  ],
];
