<?php

/**
 * Synchronize membership payment data based on recurring contribution
 *
 * This is a pseudo change class - it's not really an activity!
 */
class CRM_Contract_Change_SynchronizeContributionRecur extends CRM_Contract_Change {

  public function __construct($data) {
    $this->data = $data;
  }

  public function getRequiredFields() {
    throw new Exception('Unreachable code');
  }

  /**
   * Fetch recurring contribution and sync to membership payment fields
   *
   * @throws \CiviCRM_API3_Exception
   */
  public function execute() {
    $this->getContract(TRUE);
    // build an array just with the recurring contribution and ID
    $updates = [
      'id' => $this->getContractID(),
      'membership_payment.membership_recurring_contribution' => $this->contract['membership_payment.membership_recurring_contribution'],
    ];
    // let derivePayment add other payment related fields based on ContributionRecur
    $this->derivePaymentData($updates);
    CRM_Contract_CustomData::resolveCustomFields($updates);
    // push fields back to the membership custom fields
    civicrm_api3('Membership', 'create', $updates);
  }

  public function renderDefaultSubject($contract_after, $contract_before = NULL) {
    throw new Exception('Unreachable code');
  }

}
