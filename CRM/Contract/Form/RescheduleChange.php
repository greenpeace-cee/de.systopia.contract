<?php

use Civi\Api4;

class CRM_Contract_Form_RescheduleChange extends CRM_Core_Form {

  public function preProcess() {
    $activity_id = CRM_Utils_Request::retrieve('activity_id', 'Integer');

    if (empty($activity_id)) throw new CRM_Core_Exception('Missing change activity ID');

    $activity = Api4\Activity::get(FALSE)
      ->addWhere('id', '=', $activity_id)
      ->addSelect('activity_type_id:label')
      ->execute()
      ->first();

    if (empty($activity)) throw new CRM_Core_Exception("No activity with ID $activity_id found");

    $this->set('activity_id', $activity_id);
    $this->set('activity_type', $activity['activity_type_id:label']);

    CRM_Core_Resources::singleton()->addVars("de.systopia.contract", [
      'minimum_change_date' => CRM_Contract_DateHelper::minimumChangeDate()->format('Y-m-d'),
    ]);
  }

  public function buildQuickForm() {
    // Schedule date (activity_date)
    $this->add(
        'datepicker',
        'activity_date',
        ts('Schedule date'),
        [],
        TRUE,
        [ 'time' => TRUE ]
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
  }

  public function postProcess() {
    $activity_id = $this->get('activity_id');
    $submitted = $this->exportValues();

    Api4\Activity::update(FALSE)
      ->addWhere('id', '=', $activity_id)
      ->addValue('activity_date_time', $submitted['activity_date'])
      ->execute();
  }

}
