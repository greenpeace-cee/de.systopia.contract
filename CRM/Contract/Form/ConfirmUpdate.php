<?php

use Civi\Api4;

class CRM_Contract_Form_ConfirmUpdate extends CRM_Core_Form {

  function preProcess () {
    $activity_date = CRM_Utils_Request::retrieve('activity_date', 'String');
    $next_sched_contribution_date = CRM_Utils_Request::retrieve('next_sched_contribution_date', 'String');

    $this->assign('activity_date', $activity_date);
    $this->assign('next_sched_contribution_date', $next_sched_contribution_date);
  }

  function buildQuickForm () {
    $this->addRadio('pause_until_update', NULL, [
      'yes' => 'Yes, pause the contract until the update',
      'no'  => 'No, continue debiting until the update',
    ], [ 'options_per_line' => 1, ], '', TRUE);

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

  function postProcess () {
    // ...
  }

}
