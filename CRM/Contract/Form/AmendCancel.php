<?php

use Civi\Api4;

class CRM_Contract_Form_AmendCancel extends CRM_Core_Form {

  function preProcess () {
    $activity_id = CRM_Utils_Request::retrieve('activity_id', 'Integer');

    if (empty($activity_id)) CRM_Core_Error::fatal('Missing cancel activity ID');

    $activity = Api4\Activity::get(FALSE)
      ->addSelect(
        'ABS(option_value.id) AS cancel_reason',
        'GROUP_CONCAT(entity_tag.tag_id) AS cancel_tags'
      )
      ->addJoin(
        'OptionValue AS option_value',
        'INNER',
        ['contract_cancellation.contact_history_cancel_reason', '=', 'option_value.value']
      )
      ->addJoin(
        'OptionGroup AS option_group',
        'INNER',
        ['option_group.id',   '=', 'option_value.option_group_id'],
        ['option_group.name', '=', '"contract_cancel_reason"'    ]
      )
      ->addJoin(
        'EntityTag AS entity_tag',
        'LEFT',
        ['entity_tag.entity_id', '=', 'id'],
      )
      ->addWhere('id', '=', $activity_id)
      ->addWhere('entity_tag.entity_table', '=', 'civicrm_activity')
      ->addGroupBy('id')
      ->addGroupBy('option_value.id')
      ->execute()
      ->first();

    $this->set('activity', $activity);
  }

  function buildQuickForm () {
    // Cancellation reason (cancel_reason)
    $cancel_reasons = (array) Api4\OptionValue::get(FALSE)
      ->addSelect('value', 'label', 'description')
      ->addWhere('option_group_id:name', '=', 'contract_cancel_reason')
      ->addWhere('is_active', '=', TRUE)
      ->addWhere('filter', '=', 0)
      ->addOrderBy('weight', 'ASC')
      ->execute();

    $this->add(
      'select2',
      'cancel_reason',
      ts('Cancellation reason'),
      $cancel_reasons,
      TRUE,
      [ 'class' => 'crm-select2 huge' ]
    );

    // Cancellation tags (cancel_tags)
    $cancel_tags = (array) Api4\Tag::get(FALSE)
      ->addWhere('parent_id:name', '=', 'contract_cancellation')
      ->addWhere('is_selectable', '=', TRUE)
      ->addSelect('name', 'label', 'description', 'color')
      ->execute();

    $this->add(
      'select2',
      'cancel_tags',
      ts('Cancellation tags'),
      $cancel_tags,
      FALSE,
      [ 'class' => 'crm-select2', 'multiple' => TRUE ]
    );

    // Discard/Confirm buttons
    $this->addButtons([
      [
        'type' => 'cancel',
        'name' => ts('Discard changes'),
        'submitOnce' => TRUE,
      ],
      [
        'type' => 'submit',
        'name' => ts('Confirm'),
        'isDefault' => TRUE,
        'submitOnce' => TRUE,
      ],
    ]);

    $this->setDefaults();
  }

  function setDefaults($default_values = NULL, $filter = NULL) {
    $activity = $this->get('activity');

    parent::setDefaults([
      'cancel_reason' => $activity['cancel_reason'],
      'cancel_tags' => implode(',', $activity['cancel_tags'] ?? []),
    ]);
  }

  function validate () {
    return parent::validate();
  }

  function postProcess () {
    $activity = $this->get('activity');
    $submitted = $this->exportValues();
    $cancel_reason_id = $submitted['cancel_reason'];
    $cancel_tags = explode(',', $submitted['cancel_tags']);

    $cancel_reason_value = Api4\OptionValue::get(FALSE)
      ->addSelect('value')
      ->addWhere('id', '=', $cancel_reason_id)
      ->execute()
      ->first()['value'];

    Civi::log()->debug('CRM_Contract_Form_AmendCancel::postProcess', [
      '$cancel_reason' => $cancel_reason_value,
      '$cancel_tags' => $cancel_tags,
    ]);

    Api4\Activity::update(FALSE)
      ->addValue('contract_cancellation.contact_history_cancel_reason', $cancel_reason_value)
      ->addWhere('id', '=', $activity['id'])
      ->execute();
  }

}
