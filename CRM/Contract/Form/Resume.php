<?php

use Civi\Api4;

class CRM_Contract_Form_Resume extends CRM_Core_Form {

  public function preProcess() {
    $membership_id = CRM_Utils_Request::retrieve('membership_id', 'Integer');

    if (empty($membership_id)) throw new CRM_Core_Exception('Missing contract ID (membership_id)');

    $this->set('membership_id', $membership_id);

    $resume_activity = Api4\Activity::get(FALSE)
      ->addSelect('id')
      ->addWhere('activity_type_id:name', '=', 'Contract_Resumed')
      ->addWhere('status_id:name',        '=', 'Scheduled')
      ->addWhere('source_record_id',      '=', $membership_id)
      ->addOrderBy('activity_date_time', 'DESC')
      ->setLimit(1)
      ->execute()
      ->first();

    if (is_null($resume_activity)) throw new CRM_Core_Exception('No Resume activity scheduled');

    $this->set('resume_activity_id', $resume_activity['id']);

    $membership = Api4\Membership::get(FALSE)
      ->addWhere('id', '=', $membership_id)
      ->addSelect('membership_payment.membership_recurring_contribution')
      ->execute()
      ->first();

    $rc_id = $membership['membership_payment.membership_recurring_contribution'];
    $nsc_date = CRM_Contract_RecurringContribution::nextScheduledContributionDate($rc_id);
    $this->assign('next_scheduled_contribution_date', $nsc_date->format('Y-m-d'));
  }

  public function buildQuickForm() {
    $this->addButtons([
      [
        'type'       => 'cancel',
        'name'       => ts('No'),
        'submitOnce' => TRUE,
      ],
      [
        'type'       => 'submit',
        'name'       => ts('Yes'),
        'isDefault'  => TRUE,
        'submitOnce' => TRUE,
      ],
    ]);
  }

  public function postProcess() {
    Api4\Activity::update(FALSE)
      ->addValue('activity_date_time', date('Y-m-d H:i:s'))
      ->addWhere('id', '=', $this->get('resume_activity_id'))
      ->execute();

    civicrm_api3("Contract", "process_scheduled_modifications", [
      "id" => $this->get('membership_id'),
    ]);
  }

}
