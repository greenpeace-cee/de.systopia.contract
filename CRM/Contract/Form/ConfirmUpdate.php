<?php

use Civi\Api4;

class CRM_Contract_Form_ConfirmUpdate extends CRM_Core_Form {

  function preProcess () {
    $activity_date = CRM_Utils_Request::retrieve('activity_date', 'String');
    $amount = CRM_Utils_Request::retrieve('amount', 'String');
    $annual_amount = CRM_Utils_Request::retrieve('annual_amount', 'String');
    $currency = CRM_Utils_Request::retrieve('currency', 'String');
    $first_debit_after_update = CRM_Utils_Request::retrieve('first_debit_after_update', 'String');
    $frequency = CRM_Utils_Request::retrieve('frequency', 'String');
    $membership_id = CRM_Utils_Request::retrieve('membership_id', 'Integer');
    $next_sched_contribution_date = CRM_Utils_Request::retrieve('next_sched_contribution_date', 'String');
    $payment_instrument = CRM_Utils_Request::retrieve('payment_instrument', 'String');

    $this->set('day_before_update', (new DateTime($activity_date))->sub(new DateInterval('P1D')));
    $this->set('membership_id', $membership_id);
    $this->set('next_sched_contribution_date', new DateTime($next_sched_contribution_date));

    $this->assign('activity_date', $activity_date);
    $this->assign('amount', $amount);
    $this->assign('annual_amount', $annual_amount);
    $this->assign('currency', $currency);
    $this->assign('first_debit_after_update', $first_debit_after_update);
    $this->assign('frequency', $frequency);
    $this->assign('next_sched_contribution_date', $next_sched_contribution_date);
    $this->assign('payment_instrument', $payment_instrument);
  }

  function buildQuickForm () {
    $minimum_change_date = CRM_Contract_DateHelper::minimumChangeDate('tomorrow');
    $day_before_update = $this->get('day_before_update');
    $next_sched_contribution_date = $this->get('next_sched_contribution_date');

    if (
      $minimum_change_date < $next_sched_contribution_date
      && $next_sched_contribution_date < $day_before_update
    ) {
      $this->assign('automatic_pause_before_update', TRUE);

      $mcd_fmt = $minimum_change_date->format('Y-m-d');
      $dbu_fmt = $day_before_update->format('Y-m-d');

      $this->addRadio('pause_until_update', NULL, [
        'yes' => "Yes, pause the contract from $mcd_fmt until the day before the update ($dbu_fmt)",
        'no'  => 'No, continue debiting until the update',
      ], [ 'options_per_line' => 1 ], '', TRUE);
    } else {
      $this->assign('automatic_pause_before_update', FALSE);
    }

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

}
