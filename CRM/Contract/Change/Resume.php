<?php
/*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2019 SYSTOPIA                                  |
| Author: B. Endres (endres -at- systopia.de)                  |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/

use CRM_Contract_ExtensionUtil as E;

/**
 * "Resume Membership" change
 */
class CRM_Contract_Change_Resume extends CRM_Contract_Change_Upgrade {

  /**
   * Get a list of required fields for this type
   *
   * @return array list of required fields
   */
  public function getRequiredFields() {
    return [];
  }

  /**
   * Apply the given change to the contract
   *
   * @throws Exception should anything go wrong in the execution
   */
  public function execute() {
    $contract_before = $this->getContract(TRUE);

    // get any changes stored in the resume activity
    $contract_update = $this->buildContractUpdate($contract_before);
    $contract_update['status_id'] = 'Current';

    if (empty($contract_update['membership_payment.membership_recurring_contribution'])) {
      // recurring contribution is unchanged - resume it
      $recurring_contribution_id = CRM_Utils_Array::value(
        "membership_payment.membership_recurring_contribution",
        $contract_before
      );

      $payment_adapter_id = null;
      $payment_adapter = null;

      if (isset($recurring_contribution_id)) {
        $payment_adapter_id = CRM_Contract_Utils::getPaymentAdapterForRecurringContribution(
          $recurring_contribution_id
        );
      }

      if (isset($payment_adapter_id)) {
        $payment_adapter = CRM_Contract_Utils::getPaymentAdapterClass($payment_adapter_id);
      }

      if (isset($payment_adapter)) {
        $payment_adapter::resume($recurring_contribution_id);
      }
    }

    // perform the update
    $this->updateContract($contract_update);
    $this->updateChangeActivity($this->getContract(), $contract_before);

  }

  /**
   * Get a list of the status names that this change can be applied to
   *
   * @return array list of membership status names
   */
  public static function getStartStatusList() {
    return ['Paused'];
  }

  /**
   * Get a (human readable) title of this change
   *
   * @return string title
   */
  public static function getChangeTitle() {
    return E::ts("Resume Contract");
  }

  /**
   * Modify action links provided to the user for a given membership
   *
   * @param array $links
   * @param string $current_status_name
   * @param array $membership_data
   */
  public static function modifyMembershipActionLinks(&$links, $current_status_name, $membership_data) {
    // no-op
  }

}
