<?php
/*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2019 SYSTOPIA                                  |
| Author: B. Endres (endres -at- systopia.de)                  |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/

use CRM_Contract_ExtensionUtil as E;

/**
 * "Upgrade Membership" change
 */
class CRM_Contract_Change_Upgrade extends CRM_Contract_Change {

  /**
   * Get a list of required fields for this type
   *
   * @return array list of required fields
   */
  public function getRequiredFields() {
    return [
//        'membership_cancellation.membership_cancel_reason'
    ];
  }

  /**
   * Derive/populate additional data
   */
  public function populateData() {

    if ($this->isNew()) {
      $contract = $this->getContract(TRUE);
      $contract_after_execution = $contract;

      // copy submitted changes to change activity
      foreach (CRM_Contract_Change::$field_mapping_change_contract as $contract_attribute => $change_attribute) {
        if (!empty($this->data[$contract_attribute])) {
          $this->data[$change_attribute] = $this->data[$contract_attribute];
          $contract_after_execution[$contract_attribute] = $this->data[$contract_attribute];
        }
      }

      $this->data['subject'] = $this->getSubject($contract_after_execution, $contract);
    }
  }


  /**
   * Apply the given change to the contract
   *
   * @throws Exception should anything go wrong in the execution
   */
  public function execute() {
    $contract_before = $this->getContract(TRUE);

    // compile upgrade
    $contract_update = [];

    // adjust membership type?
    $membership_type_update = $this->getParameter('contract_updates.ch_membership_type');
    if ($membership_type_update) {
      if ($contract_before['membership_type_id'] != $membership_type_update) {
        $contract_update['membership_type_id'] = $membership_type_update;
      }
    } else {
      // FIXME: replicating weird behaviour by old engine
      $this->setParameter('contract_updates.ch_membership_type', $contract_before['membership_type_id']);
    }

    // adjust mandate/payment mode?
    $new_payment_contract = CRM_Contract_SepaLogic::updateSepaMandate(
        $this->getContractID(),
        $contract_before,
        $this->data,
        $this->data,
        $this->getActionName());

    if ($new_payment_contract) {
      // this means a new mandate has been created -> set
      $contract_update['membership_payment.membership_recurring_contribution'] = $new_payment_contract;
    }

    // perform the update
    $this->updateContract($contract_update);

    // update change activity
    $contract_after = $this->getContract();
    foreach (CRM_Contract_Change::$field_mapping_change_contract as $membership_field => $change_field) {
      // copy fields
      if (isset($contract_after[$membership_field])) {
        $this->setParameter($change_field, $contract_after[$membership_field]);
      }
    }
    $this->setParameter('contract_updates.ch_annual_diff', $contract_after['membership_payment.membership_annual'] - $contract_before['membership_payment.membership_annual']);
    $this->setParameter('subject', $this->getSubject($contract_after, $contract_before));
    $this->setStatus('Completed');
    $this->save();
  }

  /**
   * Check whether this change activity should actually be created
   *
   * CANCEL activities should not be created, if there is another one already there
   *
   * @throws Exception if the creation should be disallowed
   */
  public function shouldBeAccepted() {
    parent::shouldBeAccepted();

    // TODO:
  }

  /**
   * Render the default subject
   *
   * @param $contract_after       array  data of the contract after
   * @param $contract_before      array  data of the contract before
   * @return                      string the subject line
   */
  public function renderDefaultSubject($contract_after, $contract_before = NULL) {
    // calculate differences
    $differences        = [];
    $field2abbreviation = [
        'membership_type_id'                      => 'type',
        'membership_payment.membership_annual'    => 'amt.',
        'membership_payment.membership_frequency' => 'freq.',
        'membership_payment.to_ba'                => 'gp iban',
        'membership_payment.from_ba'              => 'member iban',
        'membership_payment.cycle_day'            => 'cycle day',
        'membership_payment.payment_instrument'   => 'payment method',
        'membership_payment.defer_payment_start'  => 'defer',
    ];

    foreach ($field2abbreviation as $field_name => $subject_abbreviation) {
      $raw_value_before = CRM_Utils_Array::value($field_name, $contract_before);
      $value_before     = $this->labelValue($raw_value_before, $field_name);
      $raw_value_after  = CRM_Utils_Array::value($field_name, $contract_after);
      $value_after      = $this->labelValue($raw_value_after, $field_name);

      // FIXME: replicating weird behaviour by old engine
      if ($subject_abbreviation == 'member iban') {
        // add member iban in any case
        $differences[] = "{$subject_abbreviation} {$value_before} to {$value_after}";
        continue;
      } elseif (!$this->isNew() && $subject_abbreviation == 'freq.') {
        // use the values, not the labels
        $differences[] = "{$subject_abbreviation} {$raw_value_before} to {$raw_value_after}";
        continue;
      }

      // standard behaviour:
      if ($value_before != $value_after) {
        $differences[] = "{$subject_abbreviation} {$value_before} to {$value_after}";
      }
    }

    $contract_id = $this->getContractID();
    return "id{$contract_id}: " . implode(' AND ', $differences);
  }
}
