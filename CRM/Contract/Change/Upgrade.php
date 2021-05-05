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
    return [];
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
        // this is necessary because membership_payment.defer_payment_start = 0
        // asserts to true with empty(), but should be treated as a change below
        $isDeferPaymentStartSet = $contract_attribute == 'membership_payment.defer_payment_start' &&
          array_key_exists($contract_attribute, $this->data) &&
          $this->data[$contract_attribute] == '0';

        if (!empty($this->data[$contract_attribute]) || $isDeferPaymentStartSet) {
          $this->data[$change_attribute] = $this->data[$contract_attribute];
          $contract_after_execution[$contract_attribute] = $this->data[$contract_attribute];
        }
        // we may receive change attributes that assert true with empty(), but
        // are in fact intended as updates. it would be cleaner to use a stricter
        // emptiness test (i.e. only skip if the key is not set or is NULL),
        // but that might break existing code, so we'll only deprecate it for now.
        if (!$isDeferPaymentStartSet && empty($this->data[$contract_attribute]) &&
          array_key_exists($contract_attribute, $this->data) && $this->data[$contract_attribute] !== NULL
        ) {
          CRM_Core_Error::deprecatedFunctionWarning('de.systopia.contract: Empty values for contract update parameters that are not NULL are deprecated. Affected parameter: ' . $contract_attribute);
        }
      }

      // Try to derive the payment adapter if it is not explicitly set
      if (empty($this->data["payment_method.adapter"])) {
        $active_links = CRM_Contract_BAO_ContractPaymentLink::getActiveLinks($contract["id"]);
        $recurring_contribution_id = $active_links[0]["contribution_recur_id"];

        $this->data["payment_method.adapter"] =
          CRM_Contract_Utils::getPaymentAdapterForRecurringContribution($recurring_contribution_id);
      }

      $payment_changes = [];

      if (isset($this->data["payment_method.adapter"])) {
        $payment_adapter = CRM_Contract_Utils::getPaymentAdapterClass($this->data["payment_method.adapter"]);
        $payment_changes = $payment_adapter::mapUpdateParameters($this->data);
      }

      $this->data["contract_updates.ch_payment_changes"] = json_encode($payment_changes);

      foreach ($this->data as $key => $value) {
        if (preg_match('/^payment_method\./', $key)) {
          unset($this->data[$key]);
        }
      }

      // Get activity subject
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
    $new_rc_id = $this->updatePayment($contract_before);

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

    return $contract_update;
  }

  /**
   * Update contract change activity based on contract diff after execution
   *
   * @param $contract_after
   * @param $contract_before
   */
  protected function updateChangeActivity($contract_after, $contract_before) {
    foreach (CRM_Contract_Change::$field_mapping_change_contract as $membership_field => $change_field) {
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
    if ($this->isNew()) {
      // FIXME: replicating weird behaviour by old engine
      $contract_before = [];
      unset($contract_after['membership_type_id']);
      unset($contract_after['membership_payment.from_ba']);
      unset($contract_after['membership_payment.to_ba']);
      unset($contract_after['membership_payment.defer_payment_start']);
      unset($contract_after['membership_payment.payment_instrument']);
      unset($contract_after['membership_payment.cycle_day']);
    }

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
      // TODO: not needed any more? (see https://redmine.greenpeace.at/issues/1276#note-74)
      /*
      if (!$this->isNew() && $subject_abbreviation == 'member iban') {
        // add member iban in any case
        $differences[] = "{$subject_abbreviation} {$value_before} to {$value_after}";
        continue;
      } elseif (!$this->isNew() && $subject_abbreviation == 'freq.') {
        // use the values, not the labels
        $differences[] = "{$subject_abbreviation} {$raw_value_before} to {$raw_value_after}";
        continue;
      }
      */

      // standard behaviour:
      if ($value_before != $value_after) {
        $differences[] = "{$subject_abbreviation} {$value_before} to {$value_after}";
      }
    }

    $contract_id = $this->getContractID();
    $subject = "id{$contract_id}: " . implode(' AND ', $differences);

    // FIXME: replicating weird behaviour by old engine
    return preg_replace('/  to/', ' to', $subject);
  }

  /**
   * Get a list of the status names that this change can be applied to
   *
   * @return array list of membership status names
   */
  public static function getStartStatusList() {
    return ['Grace', 'Current'];
  }

  /**
   * Get a (human readable) title of this change
   *
   * @return string title
   */
  public static function getChangeTitle() {
    return E::ts("Update Contract");
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
          'name'  => E::ts("Update"),
          'title' => self::getChangeTitle(),
          'url'   => "civicrm/contract/modify",
          'bit'   => CRM_Core_Action::UPDATE,
          'qs'    => "modify_action=update&id=%%id%%",
      ];
    }
  }

  /**
   *
   */
  public function updatePayment ($contract) {
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
    $current_pa_id = CRM_Contract_Utils::getPaymentAdapterForRecurringContribution($current_rc_id);
    $current_payment_adapter = CRM_Contract_Utils::getPaymentAdapterClass($current_pa_id);

    $new_rc_id = CRM_Utils_Array::value("contract_updates.ch_recurring_contribution", $change_data, $current_rc_id);

    // If a new recurring contribution ID is explicitly set,
    // link the membership to it and terminate the old contribution/payment
    if ($new_rc_id !== $current_rc_id) {
      if ($current_payment_adapter !== null) $current_payment_adapter::terminate($current_rc_id, "CHNG");

      CRM_Contract_BAO_ContractPaymentLink::setContractPaymentLink($membership_id, $new_rc_id);

      return $new_rc_id;
    }

    $payment_changes = json_decode($change_data["contract_updates.ch_payment_changes"], true);

    $new_pa_id = CRM_Utils_Array::value("adapter", $payment_changes, $current_pa_id);

    // If a different payment adapter is set,
    // create a new contribution/payment and terminate the old one
    if ($new_pa_id !== $current_pa_id) {
      $new_payment_adapter = CRM_Contract_Utils::getPaymentAdapterClass($new_pa_id);

      // ...

      return $current_rc_id;
    }

    $new_rc_id = $current_payment_adapter::update($current_rc_id, $payment_changes);

    CRM_Contract_BAO_ContractPaymentLink::setContractPaymentLink($membership_id, $new_rc_id);

    return $new_rc_id;
  }
}
