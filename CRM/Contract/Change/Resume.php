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
class CRM_Contract_Change_Resume extends CRM_Contract_Change {

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
    $contract_update = [ "status_id" => "Current" ];

    $recurring_contribution_id = $contract_before["membership_payment.membership_recurring_contribution"];
    $payment_adapter_id = CRM_Contract_Utils::getPaymentAdapterForRecurringContribution($recurring_contribution_id);
    $payment_adapter = CRM_Contract_Utils::getPaymentAdapterClass($payment_adapter_id);
    $payment_adapter::resume($recurring_contribution_id);

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

  public function renderDefaultSubject($contract_after, $contract_before = null) {
    $contract_id = $this->getContractID();
    $old_status = CRM_Utils_Array::value("status_id", $contract_before, "(null)");
    $new_status = $contract_after["status_id"];
    $subject = "id{$contract_id}: status_id $old_status to $new_status";

    return $subject;
  }

    /**
   * Update contract change activity based on contract diff after execution
   *
   * @param $contract_after
   * @param $contract_before
   */
  protected function updateChangeActivity($contract_after, $contract_before) {
    foreach (CRM_Contract_Change::$field_mapping_change_contract as $membership_field => $change_field) {
      if (isset($contract_after[$membership_field])) {
        $this->setParameter($change_field, $contract_after[$membership_field]);
      }
    }

    $annual_before = $contract_before["membership_payment.membership_annual"];
    $annual_after = $contract_after["membership_payment.membership_annual"];
    $this->setParameter("contract_updates.ch_annual_diff", $annual_after - $annual_before);

    $this->setParameter("subject", $this->getSubject($contract_after, $contract_before));
    $this->setParameter("activity_date_time", date("Y-m-d H:i:s"));
    $this->setStatus("Completed");

    $this->save();
  }
}
