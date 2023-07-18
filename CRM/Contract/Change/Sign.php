<?php
/*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2019 SYSTOPIA                                  |
| Author: B. Endres (endres -at- systopia.de)                  |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/

use CRM_Contract_ExtensionUtil as E;

/**
 * "New Membership Signed" record
 */
class CRM_Contract_Change_Sign extends CRM_Contract_Change {

  /**
   * Get a list of required fields for this type
   *
   * @return array list of required fields
   */
  public function getRequiredFields() {
    return []; // none required because change is documentary
  }

  /**
   * Apply the given change to the contract
   *
   * @throws Exception should anything go wrong in the execution
   */
  public function execute() {
    throw new Exception("New membership sign-ups are documentary, they cannot be scheduled into the future, and therefore not executed.");
  }

  /**
   * Derive/populate additional data
   */
  public function populateData() {
    parent::populateData();
    $contract = $this->getContract(TRUE);
    $this->data['contract_updates.ch_annual_diff'] = CRM_Contract_Utils::formatMoney(CRM_Utils_Array::value('membership_payment.membership_annual', $contract, ''));
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

    // TODO: check if the parameters are good
  }

  /**
   * Render the default subject
   *
   * @param $contract_after       array  data of the contract after
   * @param $contract_before      array  data of the contract before
   * @return                      string the subject line
   */
  public function renderDefaultSubject($contract_after, $contract_before = NULL) {
    $attributes = [];
    $contract = $contract_after;

    // collect values
    if (isset($contract['membership_type_id'])) {
      $membership_type = $this->labelValue($contract['membership_type_id'], 'membership_type_id');
      $attributes[] = "type {$membership_type}";
    }
    if (isset($contract['membership_payment.membership_annual'])) {
      $attributes[] = 'amt. '. $contract['membership_payment.membership_annual'];
    }
    if (isset($contract['membership_payment.membership_frequency'])) {
      // FIXME: replicating weird behaviour by old engine
      $attributes[] = 'freq. ' . $contract['membership_payment.membership_frequency'];
      //$attributes[] = 'freq. ' . $this->labelValue($contract['membership_payment.membership_frequency'], 'membership_payment.membership_frequency');
    }
    if (isset($contract['membership_payment.to_ba'])) {
      $attributes[] = 'gp iban ' . $this->labelValue($contract['membership_payment.to_ba'], 'membership_payment.to_ba');
    }
    if (isset($contract['membership_payment.from_ba'])) {
      $attributes[] = 'member iban ' . $this->labelValue($contract['membership_payment.from_ba'], 'membership_payment.from_ba');
    }
    if (isset($contract['membership_payment.cycle_day'])) {
      $attributes[] = 'cycle day '. $contract['membership_payment.cycle_day'];
    }
    if (isset($contract['membership_payment.payment_instrument'])) {
      // FIXME: replicating weird behaviour by old engine
      $attributes[] = 'payment method ' . $this->labelValue($contract['membership_payment.payment_instrument'], 'membership_payment.payment_instrument');
    }
    if (isset($contract['membership_payment.defer_payment_start'])) {
      $attributes[] = 'defer '. $contract['membership_payment.defer_payment_start'];
    }

    return "id{$contract['id']}: ".implode(' AND ', $attributes);
  }

  /**
   * Get a (human readable) title of this change
   *
   * @return string title
   */
  public static function getChangeTitle() {
    return E::ts("Sign contract");
  }

  /**
   * Get a list of the status names that this change can be applied to
   *
   * @return array list of membership status names
   */
  public static function getStartStatusList() {
    return [];
  }
}
