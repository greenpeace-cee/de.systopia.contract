<?php

use Civi\Api4;

class CRM_Contract_Form_CancelChange extends CRM_Core_Form {

  public function preProcess() {
    $activity_id = CRM_Utils_Request::retrieve('activity_id', 'Integer');

    if (empty($activity_id)) throw new CRM_Core_Exception('Missing change activity ID');

    $activity = Api4\Activity::get(FALSE)
      ->addWhere('id', '=', $activity_id)
      ->addSelect('activity_type_id:label', 'source_record_id')
      ->execute()
      ->first();

    if (empty($activity)) throw new CRM_Core_Exception("No activity with ID $activity_id found");

    $this->set('activity_id', $activity_id);
    $this->set('activity_type', $activity['activity_type_id:label']);
    $this->set('membership_id', $activity['source_record_id']);
  }

  public function buildQuickForm() {
    $action = preg_replace("/ contract$/", "", strtolower($this->get('activity_type')));

    // Cancel or delete
    $this->addRadio('cancel_or_delete', NULL, [
      'cancel' => implode('<br />', [
        "<span style=\"font-weight:bold\">The contact no longer wishes to $action their membership</span>",
        '<span>The change no longer reflects the contact\'s wishes. This will stop the change from being applied, but will keep a record of it in the membership\'s history.</span>',
      ]),
      'delete' => implode('<br />', [
        "<span style=\"font-weight:bold\">The change was entered incorrectly</span>",
        '<span>The change was a mistake and did not reflect the contact\'s wishes at all. The change will be deleted - no record of it will be visible in the membership\'s history.</span>',
      ]),
    ], [], '', TRUE);

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
    $activity_type = $this->get('activity_type');
    $membership_id = $this->get('membership_id');

    $submitted = $this->exportValues();

    $activityUpdate = Api4\Activity::update(FALSE)
      ->addWhere('id', '=', $activity_id)
      ->addValue('status_id:name', 'Cancelled');

    if ($submitted['cancel_or_delete'] === 'delete') {
      $activityUpdate->addValue('is_deleted', TRUE);
    }

    $activityUpdate->execute();

    if ($activity_type === 'Pause Contract') {
      // If a contract pause is cancelled, the corresponding 'Resume' activity
      // should be cancelled as well
      $resume_update = Api4\Activity::update(FALSE)
        ->addWhere('activity_type_id:name', '=', 'Contract_Resumed')
        ->addWhere('source_record_id', '=', $membership_id)
        ->addWhere('status_id:name', '=', 'Scheduled')
        ->addValue('status_id:name', 'Cancelled');

      if ($submitted['cancel_or_delete'] === 'delete') {
        $resume_update->addValue('is_deleted', TRUE);
      }

      $resume_update
        ->addOrderBy('activity_date_time', 'ASC')
        ->setLimit(1)
        ->execute();
    }
  }

}
