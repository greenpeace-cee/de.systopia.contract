<?php
/*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2019 SYSTOPIA                                  |
| Author: B. Endres (endres -at- systopia.de)                  |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/

use Civi\Api4;
use CRM_Contract_ExtensionUtil as E;

/**
 * "Revive Membership" change
 */
class CRM_Contract_Change_Revive extends CRM_Contract_Change {

  /**
   * Map update (API) parameters to payment changes
   *
   * @param array $current_contract
   *
   * @return array - Payment changes
   */
  public function mapParametersToPaymentChanges ($current_contract) {
    // Derive the new payment adapter
    $payment_adapter_id = CRM_Utils_Array::value("payment_method.adapter", $this->data);

    if (empty($payment_adapter_id)) {
      $current_rc_id = $current_contract["membership_payment.membership_recurring_contribution"];
      $payment_adapter_id = CRM_Contract_Utils::getPaymentAdapterForRecurringContribution($current_rc_id);
    }

    // Map paramters
    $param_mapping = [
      "campaign_id"                             => "campaign_id",
      "contract_updates.ch_cycle_day"           => "cycle_day",
      "membership_payment.cycle_day"            => "cycle_day",
      "membership_payment.defer_payment_start"  => "defer_payment_start",
      "membership_payment.from_ba"              => "from_ba",
      "membership_payment.membership_annual"    => "annual",
      "membership_payment.membership_frequency" => "frequency",
    ];

    $payment_changes = [];

    foreach ($param_mapping as $original_key => $result_key) {
      if (array_key_exists($original_key, $this->data)) {
        $payment_changes[$result_key] = $this->data[$original_key];
      }
    }

    foreach ($this->data as $key => $value) {
      if (!preg_match("/^payment_method\./", $key)) continue;

      $stripped_key = preg_replace("/^payment_method\./", "", $key);
      $payment_changes[$stripped_key] = $value;
    }

    return [
      "activity_type_id" => CRM_Utils_Array::value("activity_type_id", $this->data),
      "adapter"          => $payment_adapter_id,
      "parameters"       => $payment_changes,
    ];
  }

  public function populateData () {
    if (!$this->isNew()) return;

    $contract = $this->getContract(TRUE);
    $contract_after_execution = $contract;

    // copy submitted changes to change activity
    foreach (CRM_Contract_Change::$field_mapping_change_contract as $contract_attribute => $change_attribute) {
      if (!array_key_exists($contract_attribute, $this->data)) continue;

      $param_value = $this->data[$contract_attribute];

      if ($param_value === NULL) continue;

      // we may receive change attributes that assert true with empty(), but
      // are in fact intended as updates. it would be cleaner to use a stricter
      // emptiness test (i.e. only skip if the key is not set or is NULL),
      // but that might break existing code, so we'll only deprecate it for now.
      if ($contract_attribute === "membership_payment.defer_payment_start" && in_array($param_value, [0, "0"], true)) {
        // skip deprecation warning for membership_payment.defer_payment_start = 0 or "0"
        // because this is an actual change
      } elseif (empty($param_value)) {
        CRM_Core_Error::deprecatedFunctionWarning(
          "de.systopia.contract: Empty values for contract update parameters that are not NULL are deprecated. Affected parameter: $contract_attribute"
        );

        continue;
      }

      $this->data[$change_attribute] = $param_value;
      $contract_after_execution[$contract_attribute] = $param_value;
    }

    $payment_changes = $this->mapParametersToPaymentChanges($contract);
    $this->data["contract_updates.ch_payment_changes"] = json_encode($payment_changes);

    // Delete all update parameters prefixed with payment_method.*
    foreach ($this->data as $key => $value) {
      if (preg_match('/^payment_method\./', $key)) {
        unset($this->data[$key]);
      }
    }

    // Get activity subject
    $this->data['subject'] = $this->getSubject($contract_after_execution, $contract);
  }

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
    $new_rc_id = $this->revivePayment($contract_before);

    $contract_update = $this->buildContractUpdate($contract_before);
    $contract_update["membership_payment.membership_recurring_contribution"] = $new_rc_id;

    $this->updateContract($contract_update);
    $this->updateChangeActivity($this->getContract(), $contract_before);
  }

  protected function buildContractUpdate($contract_before) {
    $contract_update = [];

    $new_membership_type = CRM_Utils_Array::value("contract_updates.ch_membership_type", $this->data);

    if (
      isset($new_membership_type)
      && $contract_before["membership_type_id"] !== $new_membership_type
    ) {
        $contract_update["membership_type_id"] = $new_membership_type;
    }

    $contract_update["end_date"] = "";
    $contract_update["status_id"] = "Current";
    $contract_update["membership_cancellation.membership_cancel_reason"] = "";
    $contract_update["membership_cancellation.membership_cancel_date"] = "";

    return $contract_update;
  }

  /**
   * Get a list of the status names that this change can be applied to
   *
   * @return array list of membership status names
   */
  public static function getStartStatusList() {
    return ['Cancelled'];
  }

  /**
   * Get a (human readable) title of this change
   *
   * @return string title
   */
  public static function getChangeTitle() {
    return E::ts("Revive Contract");
  }

  /**
   * Modify action links provided to the user for a given membership
   *
   * @param $links                array  currently given links
   * @param $current_status_name  string membership status as a string
   * @param $membership_data      array  all known information on the membership in question
   */
  public static function modifyMembershipActionLinks(&$links, $current_status_name, $membership_data) {
    if (in_array($current_status_name, self::getStartStatusList())) {
      $links[] = [
          'name'  => E::ts("Revive"),
          'title' => self::getChangeTitle(),
          'url'   => "civicrm/contract/modify",
          'bit'   => CRM_Core_Action::UPDATE,
          'qs'    => "modify_action=revive&id=%%id%%",
      ];
    }
  }

  /**
   *
   */
  public function revivePayment ($contract) {
    $change_data = $this->data;

    // Resolve custom field IDs
    foreach ($change_data as $key => $value) {
      if (preg_match('/^custom_\d+$/', $key)) {
        $name = CRM_Contract_Utils::getCustomFieldName($key);
        $change_data[$name] = $value;
        unset($change_data[$key]);
      }
    }

    $membership_id = $this->getContractID();
    $current_rc_id = $contract["membership_payment.membership_recurring_contribution"];
    $new_rc_id = CRM_Utils_Array::value("contract_updates.ch_recurring_contribution", $change_data);

    // If a new recurring contribution ID is explicitly set, link the membership to it
    if (isset($new_rc_id)) {
      CRM_Contract_BAO_ContractPaymentLink::setContractPaymentLink($membership_id, $new_rc_id);
      return $new_rc_id;
    }

    $current_pa_id = CRM_Contract_Utils::getPaymentAdapterForRecurringContribution($current_rc_id);
    $current_payment_adapter = CRM_Contract_Utils::getPaymentAdapterClass($current_pa_id);
    $payment_changes = json_decode($change_data["contract_updates.ch_payment_changes"], true);
    $params = $payment_changes['parameters'];

    // Calculate the new amount & frequency
    $current_rc = Api4\ContributionRecur::get()
      ->addWhere("id", "=", $current_rc_id)
      ->addSelect("amount", "frequency_interval", "frequency_unit")
      ->execute()
      ->first();

    $current_annual = CRM_Contract_Utils::calcAnnualAmount(
      (float) $current_rc["amount"],
      (int) $current_rc["frequency_interval"],
      (string) $current_rc["frequency_unit"]
    );

    $new_recurring_amount = CRM_Contract_Utils::calcRecurringAmount(
      (float) CRM_Utils_Array::value("annual", $params, $current_annual["annual"]),
      (int) CRM_Utils_Array::value("frequency", $params, $current_annual["frequency"])
    );

    unset($params["annual"]);
    unset($params["frequency"]);
    $params = array_merge($params, $new_recurring_amount);

    // If a different payment adapter is set,
    // create a new contribution/payment and terminate the old one
    if ($payment_changes["adapter"] !== $current_pa_id) {
      $new_payment_adapter = CRM_Contract_Utils::getPaymentAdapterClass($payment_changes["adapter"]);

      $new_rc_id =  $new_payment_adapter::createFromUpdate(
        $current_rc_id,
        $current_pa_id,
        $params,
        $payment_changes["activity_type_id"]
      );

      CRM_Contract_BAO_ContractPaymentLink::setContractPaymentLink($membership_id, $new_rc_id);

      return $new_rc_id;
    }

    if (isset($current_payment_adapter)) {
      $new_rc_id = $current_payment_adapter::revive($current_rc_id, $params);
    } else {
      $update_params = array_merge($params, [
        "id"                     => $current_rc_id,
        "contribution_status_id" => "In Progress",
      ]);

      civicrm_api3("ContributionRecur", "create", $update_params);
      $new_rc_id = $current_rc_id;
    }

    CRM_Contract_BAO_ContractPaymentLink::setContractPaymentLink($membership_id, $new_rc_id);

    return $new_rc_id;
  }

  /**
   * Render the default subject
   *
   * @param $contract_after       array  data of the contract after
   * @param $contract_before      array  data of the contract before
   * @return                      string the subject line
   */
  public function renderDefaultSubject($contract_after, $contract_before = NULL) {
    if ($this->isNew()) {
      $contract_before = [];
      unset($contract_after['membership_type_id']);
      unset($contract_after['membership_payment.from_ba']);
      unset($contract_after['membership_payment.to_ba']);
      unset($contract_after['membership_payment.defer_payment_start']);
      unset($contract_after['membership_payment.payment_instrument']);
      unset($contract_after['membership_payment.cycle_day']);
    }

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

      if ($value_before != $value_after) {
        $differences[] = "{$subject_abbreviation} {$value_before} to {$value_after}";
      }
    }

    $contract_id = $this->getContractID();
    $subject = "id{$contract_id}: " . implode(' AND ', $differences);

    return preg_replace('/  to/', ' to', $subject);
  }

  /**
   * Update contract change activity based on contract diff after execution
   *
   * @param $contract_after
   * @param $contract_before
   */
  protected function updateChangeActivity($contract_after, $contract_before) {
    foreach (CRM_Contract_Change::$field_mapping_change_contract as $membership_field => $change_field) {
      if ($change_field === "contract_updates.ch_defer_payment_start") continue;

      // copy fields
      if (isset($contract_after[$membership_field])) {
        $this->setParameter($change_field, $contract_after[$membership_field]);
      }
    }
    $this->setParameter('contract_updates.ch_annual_diff', $contract_after['membership_payment.membership_annual'] - $contract_before['membership_payment.membership_annual']);
    $this->setParameter('subject', $this->getSubject($contract_after, $contract_before));
    $this->setParameter("activity_date_time", date('Y-m-d H:i:s'));
    $this->setStatus('Completed');
    $this->save();
  }

}
