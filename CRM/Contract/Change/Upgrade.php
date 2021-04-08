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
    if (!$this->getParameter('contract_updates.ch_membership_type')) {
      // FIXME: replicating weird behaviour by old engine
      $this->setParameter('contract_updates.ch_membership_type', $contract_before['membership_type_id']);
    }

    $contract_update = $this->buildContractUpdate($contract_before);

    // perform the update
    $this->updateContract($contract_update);
    $this->updateChangeActivity($this->getContract(), $contract_before);
  }

  protected function buildContractUpdate($contract_before) {
    // compile upgrade
    $contract_update = [];

    // adjust membership type?
    $membership_type_update = $this->getParameter('contract_updates.ch_membership_type');
    if ($membership_type_update) {
      if ($contract_before['membership_type_id'] != $membership_type_update) {
        $contract_update['membership_type_id'] = $membership_type_update;
      }
    }

    // adjust mandate/payment mode?
    $membership_id = $this->getContractID();
    $current_state = $contract_before;
    $desired_state = $this->data;
    $activity = $this->data;
    $action = $this->getActionName();

    // desired_state (from activity) hasn't resolved the numeric custom_ fields yet
    foreach ($desired_state as $key => $value) {
      if (preg_match('#^custom_\d+$#', $key)) {
        $full_key = CRM_Contract_Utils::getCustomFieldName($key);
        $desired_state[$full_key] = $value;
      }
    }

    // all relevant fields (activity field  -> membership field)
    $mandate_relevant_fields = [
      'contract_updates.ch_annual'                 => 'membership_payment.membership_annual',
      'contract_updates.ch_from_ba'                => 'membership_payment.from_ba',
      // 'contract_updates.ch_to_ba'                  => 'membership_payment.to_ba', // TODO: implement when multiple creditors are around
      'contract_updates.ch_frequency'              => 'membership_payment.membership_frequency',
      'contract_updates.ch_cycle_day'              => 'membership_payment.cycle_day',
      'contract_updates.ch_recurring_contribution' => 'membership_payment.membership_recurring_contribution',
      'contract_updates.ch_defer_payment_start'    => 'membership_payment.defer_payment_start',
    ];

    // calculate changes to see whether we have to act
    $mandate_relevant_changes = array();
    foreach ($mandate_relevant_fields as $desired_field_name => $current_field_name) {
      if (
        isset($desired_state[$desired_field_name])
        && $desired_state[$desired_field_name] != CRM_Utils_Array::value($current_field_name, $current_state)
      ) {
        $mandate_relevant_changes[] = $desired_field_name;
      }
    }

    // GP-1732: maybe the revive of current RecurringContribution is requested...
    if ($action == 'revive' && !empty($desired_state['contract_updates.ch_recurring_contribution'])) {
      // ...so consider this to be a change even if it's the same as before
      $mandate_relevant_changes[] = 'contract_updates.ch_recurring_contribution';
    }

    if (empty($mandate_relevant_changes) && $action != 'revive') {
      // nothing to do here
      return $contract_update;
    }

    if (!in_array('contract_updates.ch_recurring_contribution', $mandate_relevant_changes)) {
      // if there is no new recurring contribution given, create a new one based
      //  on the parameters. See GP-669 / GP-789

      // get the right values (desired first, else from current)
      $from_ba       = CRM_Utils_Array::value('contract_updates.ch_from_ba', $desired_state,
                        /* fallback: membership */ CRM_Utils_Array::value('membership_payment.from_ba', $current_state));
      $cycle_day     = (int) CRM_Utils_Array::value('contract_updates.ch_cycle_day', $desired_state,
                        /* fallback: membership */ CRM_Utils_Array::value('membership_payment.cycle_day', $current_state));
      $annual_amount = CRM_Utils_Array::value('contract_updates.ch_annual', $desired_state,
                        /* fallback: membership */ CRM_Utils_Array::value('membership_payment.membership_annual', $current_state));
      $frequency     = (int) CRM_Utils_Array::value('contract_updates.ch_frequency',
                        /* fallback: membership */ $desired_state, CRM_Utils_Array::value('membership_payment.membership_frequency', $current_state));

      $recurring_contribution = null;
      $recurring_contribution_id = (int) CRM_Utils_Array::value('membership_payment.membership_recurring_contribution', $current_state);
      if ($recurring_contribution_id) {
        $recurring_contribution = civicrm_api3('ContributionRecur', 'getsingle', ['id' => $recurring_contribution_id]);
      }

      $campaign_id = CRM_Utils_Array::value('campaign_id', $activity,
        /* fallback: r. contrib. */ CRM_Utils_Array::value('campaign_id', $recurring_contribution));


      // fallback 2: take (still) missing from connected recurring contribution
      if (empty($cycle_day) || empty($frequency) || empty($annual_amount) || empty($from_ba)) {

        if (!is_null($recurring_contribution)) {
          if (empty($cycle_day)) {
            $cycle_day = $recurring_contribution['cycle_day'];
          }
          if (empty($frequency)) {
            if ($recurring_contribution['frequency_unit'] == 'month') {
              $frequency = 12 / $recurring_contribution['frequency_interval'];
            } if ($recurring_contribution['frequency_unit'] == 'year') {
              $frequency = 1 / $recurring_contribution['frequency_interval'];
            }
          }
          if (empty($annual_amount)) {
            $annual_amount = CRM_Contract_Utils::formatMoney(CRM_Contract_Utils::formatMoney($recurring_contribution['amount']) * $frequency);
          }
          if (empty($from_ba)) {
            $mandate = CRM_Contract_PaymentInstrument_SepaMandate::loadByRecurringContributionId($recurring_contribution_id);
            if (isset($mandate)) {
              $mandate_params = $mandate->getParameters();
              $from_ba = CRM_Contract_BankingLogic::getOrCreateBankAccount($current_state['contact_id'], $mandate_params['iban'], $mandate_params['bic'] ?? NULL);
            }
          }
        }
      }

      // calculate some stuff
      if ($cycle_day < 1 || $cycle_day > 30) {
        // invalid cycle day
        $cycle_day = CRM_Contract_PaymentInstrument_SepaMandate::nextCycleDay();
      }

      // calculate amount
      $frequency_interval = 12 / $frequency;
      $amount = CRM_Contract_Utils::formatMoney(CRM_Contract_Utils::formatMoney($annual_amount) / $frequency);
      if ($amount < 0.01) {
        throw new Exception("Installment amount too small");
      }

      // get bank account
      $donor_account = CRM_Contract_BankingLogic::getBankAccount($from_ba);
      if (empty($donor_account['bic']) && CRM_Sepa_Logic_Settings::isLittleBicExtensionAccessible()) {
        $bic_search = civicrm_api3('Bic', 'findbyiban', array('iban' => $donor_account['iban']));
        if (!empty($bic_search['bic'])) {
          $donor_account['bic'] = $bic_search['bic'];
        }
      }
      if (empty($donor_account['iban'])) {
        throw new Exception("No donor bank account given.");
      }

      // we need to create a new mandate
      $update_start_date = CRM_Contract_RecurringContribution::getUpdateStartDate(
        $current_state,
        $desired_state,
        $activity,
        CRM_Contract_PaymentInstrument_SepaMandate::getCycleDays()
      );

      $new_mandate_values =  array(
        'type'               => 'RCUR',
        'contact_id'         => $current_state['contact_id'],
        'amount'             => $amount,
        'currency'           => CRM_Sepa_Logic_Settings::defaultCreditor()->currency,
        'start_date'         => $update_start_date,
        'creation_date'      => date('YmdHis'), // NOW
        'date'               => date('YmdHis', strtotime($activity['activity_date_time'])),
        'validation_date'    => date('YmdHis'), // NOW
        'iban'               => $donor_account['iban'],
        'bic'                => $donor_account['bic'] ?? NULL,
        // 'source'             => ??
        'campaign_id'        => $campaign_id,
        'financial_type_id'  => 2, // Membership Dues
        'frequency_unit'     => 'month',
        'cycle_day'          => $cycle_day,
        'frequency_interval' => $frequency_interval,
        );

      // create and reload (to get all data)
      $new_mandate = CRM_Contract_PaymentInstrument_SepaMandate::create($new_mandate_values);
      $new_mandate_params = $new_mandate->getParameters();
      $new_recurring_contribution = $new_mandate_params['entity_id'];

      // try to create replacement link
      if (!empty($current_state['membership_payment.membership_recurring_contribution'])) {
        // see if the old one was a mandate
        $old_mandate = civicrm_api3('SepaMandate', 'get', array(
            'entity_table' => 'civicrm_contribution_recur',
            'entity_id'    => $current_state['membership_payment.membership_recurring_contribution'],
            'return'       => 'id'));
        if (!empty($old_mandate['id'])) {
          CRM_Contract_PaymentInstrument_SepaMandate::addSepaMandateReplacedLink(
            $new_mandate_params['id'],
            $old_mandate['id']
          );
        }
      }

    } else {
      // another (existing) recurring contribution has been chosen by the user:
      $new_recurring_contribution = (int) CRM_Utils_Array::value('contract_updates.ch_recurring_contribution', $desired_state, CRM_Utils_Array::value('membership_payment.membership_recurring_contribution', $current_state));
    }

    // finally: terminate the old one
    $recurring_contribution_id = $current_state['membership_payment.membership_recurring_contribution'];

    if (isset($recurring_contribution_id)) {
      $mandate = CRM_Contract_PaymentInstrument_SepaMandate::loadByRecurringContributionId(
        $recurring_contribution_id
      );

      if (isset($mandate)) {
        $mandate->terminate();
      }

      civicrm_api3("ContributionRecur", "create", [
        "id"                     => $recurring_contribution_id,
        "end_date"               => date("YmdHis"),
        "cancel_date"            => date("YmdHis"),
        "contribution_status_id" => 1,
      ]);

      $recurring_contribution = new CRM_Contribute_DAO_ContributionRecur();
      $recurring_contribution->get("id", $recurring_contribution_id);
      $recurring_contribution->cancel_reason = "CHNG";
      $recurring_contribution->save();
    }

    // link recurring contribution to contract
    CRM_Contract_BAO_ContractPaymentLink::setContractPaymentLink(
      $membership_id,
      $new_recurring_contribution
    );

    if ($new_recurring_contribution) {
      // this means a new mandate has been created -> set
      $contract_update['membership_payment.membership_recurring_contribution'] = $new_recurring_contribution;
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
}
