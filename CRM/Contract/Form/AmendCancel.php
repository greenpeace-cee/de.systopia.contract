<?php

use Civi\Api4;
use Civi\Api4\Tag;

class CRM_Contract_Form_AmendCancel extends CRM_Core_Form {

  function preProcess () {
    $activity_id = CRM_Utils_Request::retrieve('activity_id', 'Integer');

    if (empty($activity_id)) throw new CRM_Core_Exception('Missing cancel activity ID');

    $activity = Api4\Activity::get(FALSE)
      ->addSelect(
        'activity_type_id',
        'medium_id',
        'details',
        'source_record_id',
        'contract_cancellation.contact_history_cancel_reason',
        'GROUP_CONCAT(entity_tag.tag_id) AS cancel_tags'
      )
      ->addJoin(
        'EntityTag AS entity_tag',
        'LEFT',
        ['entity_tag.entity_id',    '=', 'id'                ],
        ['entity_tag.entity_table', '=', '"civicrm_activity"']
      )
      ->addWhere('id', '=', $activity_id)
      ->addGroupBy('id')
      ->addGroupBy('contract_cancellation.contact_history_cancel_reason')
      ->execute()
      ->first();

    $cancel_reason = Api4\OptionValue::get(FALSE)
     ->addSelect('id', 'filter')
     ->addWhere('option_group_id:name', '=', 'contract_cancel_reason')
     ->addWhere('value', '=', $activity['contract_cancellation.contact_history_cancel_reason'])
     ->execute()
     ->first();

    $this->assign('technical_cancel_reason', $cancel_reason['filter'] === 1);

    $activity['cancel_reason'] = $cancel_reason['id'];
    unset($activity['contract_cancellation.contact_history_cancel_reason']);

    $this->set('activity', $activity);
    $this->assign('activity', $activity);

    $membership = Api4\Membership::get(FALSE)
      ->addSelect(
        'status_id:name',
        'ABS(activity.id) AS most_recent_activity_id',
        'membership_cancellation.membership_cancel_reason:name',
        'ABS(membership_payment.membership_recurring_contribution) AS recurring_contribution_id'
      )
      ->addWhere('id', '=', $activity['source_record_id'])
      ->addJoin(
        'Activity AS activity',
        'LEFT',
        ['activity.source_record_id',      '=',  'id'                                        ],
        ['activity.activity_type_id:name', 'IN', array_keys(CRM_Contract_Change::$type2class)]
      )
      ->addOrderBy('activity.activity_date_time', 'DESC')
      ->setLimit(1)
      ->execute()
      ->first();

    $this->set('membership', $membership);
    $this->assign('membership', $membership);
  }

  function buildQuickForm () {
    $activity = $this->get('activity');

    $cancel_reasons = (array) Api4\OptionValue::get(FALSE)
      ->addSelect('value', 'label', 'description')
      ->addWhere('option_group_id:name', '=', 'contract_cancel_reason')
      ->addWhere('is_active', '=', TRUE)
      ->addClause('OR', ['filter', '=', 0], ['id', '=', $activity['cancel_reason']])
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
    $cancel_tags = (array) Tag::get(FALSE)
      ->addWhere('parent_id:name', '=', 'Contract Cancellation')
      ->addWhere('is_selectable', '=', TRUE)
      ->addSelect('name', 'label', 'description', 'color')
      ->execute();

    $this->add(
      'select2',
      'cancel_tags',
      ts('Cancellation tags'),
      $cancel_tags,
      FALSE,
      [ 'class' => 'crm-select2 huge', 'multiple' => TRUE ]
    );

    // Source medium (medium_id)
    $encounter_media = CRM_Contract_FormUtils::getOptionValueLabels("encounter_medium");

    $this->add(
      'select',
      'medium_id',
      ts('Source media'),
      $encounter_media,
      TRUE,
      [ 'class' => 'crm-select2' ]
    );

    // Notes (details)
    if (version_compare(CRM_Utils_System::version(), '4.7', '<')) {
      $this->addWysiwyg('details', ts('Notes'), []);
    } else {
      $this->add('wysiwyg', 'details', ts('Notes'));
    }

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
      'cancel_tags'   => implode(',', $activity['cancel_tags'] ?? []),
      'details'       => $activity['details'],
      'medium_id'     => $activity['medium_id'],
    ]);
  }

  function validate () {
    return parent::validate();
  }

  function postProcess () {
    $activity = $this->get('activity');

    $submitted = $this->exportValues();

    $cancel_reason = Api4\OptionValue::get(FALSE)
      ->addSelect('value')
      ->addWhere('id', '=', $submitted['cancel_reason'])
      ->execute()
      ->first()['value'];

    $cancel_tag_ids = empty($submitted['cancel_tags']) ? [] : explode(',', $submitted['cancel_tags']);
    $cancel_tags = [];
    foreach ($cancel_tag_ids as $cancel_tag_id) {
      $cancel_tags[] = Tag::get(FALSE)
        ->addSelect('name')
        ->addWhere('id', '=', $cancel_tag_id)
        ->execute()
        ->first()['name'];
    }
    $details = $submitted['details'];
    $medium_id = $submitted['medium_id'];

    civicrm_api3('Contract', 'amend_cancel', [
      'activity_id' => $activity['id'],
      'cancel_reason' => $cancel_reason,
      'medium_id' => $medium_id,
      'details' => $details,
      'cancel_tags' => $cancel_tags,
    ]);
  }

}
