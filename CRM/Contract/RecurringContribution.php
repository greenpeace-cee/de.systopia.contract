<?php
/*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2017-2019 SYSTOPIA                             |
| Author: B. Endres (endres -at- systopia.de)                  |
|         M. McAndrew (michaelmcandrew@thirdsectordesign.org)  |
|         P. Figel (pfigel -at- greenpeace.org)                |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/

use Civi\Api4;

class CRM_Contract_RecurringContribution {

  /** cached variables */
  protected $paymentInstruments = NULL;
  protected $sepaPaymentInstruments = NULL;
  static protected $cached_results = array();

  /**
   * Return a detailed list of recurring contribution
   * for the given contact
   *
   * @param $cid
   * @param bool $thatAreNotAssignedToOtherContracts
   * @param null $contractId
   * @param bool $useCache
   *
   * @return array|mixed
   * @throws \CiviCRM_API3_Exception
   */
  public static function getAllForContact($cid, $thatAreNotAssignedToOtherContracts = TRUE, $contractId = NULL, $useCache = TRUE) {
    $object = new CRM_Contract_RecurringContribution();
    return $object->getAll($cid, $thatAreNotAssignedToOtherContracts, $contractId, $useCache);
  }

  /**
   * Gets the cycle day for the given recurring contribution
   *
   * @todo caching (in this whole section)
   */
  public static function getCycleDay($recurring_contribution_id) {
    $recurring_contribution_id = (int) $recurring_contribution_id;
    if ($recurring_contribution_id) {
      try {
        $recurring_contribution = civicrm_api3('ContributionRecur', 'getsingle', [
          'id'     => $recurring_contribution_id,
          'return' => 'cycle_day']);
        if (!empty($recurring_contribution['cycle_day'])) {
          return $recurring_contribution['cycle_day'];
        }
      } catch (Exception $e) {
        // doesn't exist?
      }
    }
    return NULL;
  }

  /**
   * Return a detailed list of recurring contribution
   * for the given contact
   */
  public static function getCurrentContract($contact_id, $recurring_contribution_id) {
    // make sure we have the necessary information
    if (empty($contact_id) || empty($recurring_contribution_id)) {
      return array();
    }

    // load contact
    $contact = civicrm_api3('Contact', 'getsingle', array(
      'id'     => $contact_id,
      'return' => 'display_name'));

    // load contribution
    $contributionRecur = civicrm_api3('ContributionRecur', 'getsingle', ['id' => $recurring_contribution_id]);

    // load SEPA creditors
    $sepaCreditors = civicrm_api3('SepaCreditor', 'get')['values'];

    // load mandate
    $sepaMandates = civicrm_api3('SepaMandate', 'get', [
      'contact_id'   => $contact_id,
      'type'         => 'RCUR',
      'entity_table' => 'civicrm_contribution_recur',
      'entity_id'    => $recurring_contribution_id,
      ])['values'];

    $object = new CRM_Contract_RecurringContribution();
    return $object->renderRecurringContribution($contributionRecur, $contact, $sepaMandates, $sepaCreditors);
  }

  /**
   * Render all recurring contributions for that contact
   *
   * @param $cid
   * @param bool $thatAreNotAssignedToOtherContracts
   * @param null $contractId
   * @param bool $useCache
   *
   * @return array|mixed
   * @throws \CiviCRM_API3_Exception
   */
  public function getAll($cid, $thatAreNotAssignedToOtherContracts = TRUE, $contractId = NULL, $useCache = TRUE) {
    $return = array();

    // TODO: this smells like premature optimiziation, check if we can drop
    // see if we have that cached (it's getting called multiple times)
    $cache_key = "{$cid}-{$thatAreNotAssignedToOtherContracts}-{$contractId}";
    if (isset(self::$cached_results[$cache_key]) && $useCache) {
      return self::$cached_results[$cache_key];
    }

    // load contact
    $contact = civicrm_api3('Contact', 'getsingle', array(
      'id'     => $cid,
      'return' => 'display_name'));

    // load contribution
    $contributionRecurs = civicrm_api3('ContributionRecur', 'get', [
      'contact_id'             => $cid,
      'sequential'             => 0,
      'contribution_status_id' => ['IN' => $this->getValidRcurStatusIds()],
      'option.limit'           => 1000
      ])['values'];

    // load attached mandates
    if (!empty($contributionRecurs)) {
      $sepaMandates = civicrm_api3('SepaMandate', 'get', [
        'contact_id'   => $cid,
        'type'         => 'RCUR',
        'entity_table' => 'civicrm_contribution_recur',
        'entity_id'    => ['IN' => array_keys($contributionRecurs)]
        ])['values'];
    } else {
      $sepaMandates = array();
    }

    // load SEPA creditors
    $sepaCreditors = civicrm_api3('SepaCreditor', 'get')['values'];

    // render all recurring contributions
    foreach($contributionRecurs as $cr) {
      $return[$cr['id']] = $this->renderRecurringContribution($cr, $contact, $sepaMandates, $sepaCreditors);
    }

    // We don't want to return recurring contributions for selection if they are
    // or will be assigned to OTHER contracts
    if ($thatAreNotAssignedToOtherContracts && !empty($return)) {
      // find contracts already using any of our collected recurring contributions:
      $rcField = CRM_Contract_Utils::getCustomFieldId('membership_payment.membership_recurring_contribution');
      $contract_using_rcs = civicrm_api3('Membership', 'get', [
        $rcField  => ['IN' => array_keys($return)],
        'return'  => $rcField,
        'options' => ['limit' => 0],
      ]);

      // remove the ones from the $return list that are being used by other contracts
      foreach ($contract_using_rcs['values'] as $contract) {
        // but leave the current one in
        if ($contract['id'] != $contractId) {
          unset($return[$contract[$rcField]]);
        }
      }
      if (!empty($return)) {
        // find pending contract updates using our recurring contributions
        $rcUpdateField = CRM_Contract_Utils::getCustomFieldId('contract_updates.ch_recurring_contribution');
        $updates_using_rcs = civicrm_api3('Activity', 'get', [
          'return'       => [$rcUpdateField, 'source_record_id'],
          'status_id'    => ['IN' => ['Scheduled', 'Needs Review']],
          $rcUpdateField => ['IN' => array_keys($return)],
          'options'      => ['limit' => 0],
        ]);
        // remove any matches not associated with the current contract
        foreach ($updates_using_rcs['values'] as $update) {
          if ($update['source_record_id'] != $contractId) {
            unset($return[$update[$rcUpdateField]]);
          }
        }
      }
    }

    self::$cached_results[$cache_key] = $return;
    return $return;
  }

  /**
   * Check if a recurring contribution can be assigned to a contract
   *
   * @param $contribution_recur_id
   * @param $contract_id
   *
   * @return bool
   * @throws \CiviCRM_API3_Exception
   */
  public function isAssignableToContract($contribution_recur_id, $contract_id) {
    $rcField = CRM_Contract_Utils::getCustomFieldId('membership_payment.membership_recurring_contribution');
    $contract_using_rc = civicrm_api3('Membership', 'getcount', [
      $rcField  => $contribution_recur_id,
      'id'      => ['<>' => $contract_id],
    ]);
    $rcUpdateField = CRM_Contract_Utils::getCustomFieldId('contract_updates.ch_recurring_contribution');
    $updates_using_rc = civicrm_api3('Activity', 'getcount', [
      'status_id'        => ['IN' => ['Scheduled', 'Needs Review']],
      $rcUpdateField     => $contribution_recur_id,
      'source_record_id' => ['<>' => $contract_id],
    ]);
    return $contract_using_rc === 0 && $updates_using_rc === 0;
  }


  /**
   * Render the given recurring contribution
   */
  protected function renderRecurringContribution($cr, $contact, $sepaMandates, $sepaCreditors) {
    $result = array();

    // get payment instruments
    $paymentInstruments = $this->getPaymentInstruments();

    // render fields
    $result['fields'] = [
      'display_name' => $contact['display_name'],
      'payment_instrument' => $paymentInstruments[$cr['payment_instrument_id']],
      'frequency' => $this->writeFrequency($cr),
      'amount' => CRM_Contract_Utils::formatMoney($cr['amount']),
      'annual_amount' => CRM_Contract_Utils::formatMoney($this->calcAnnualAmount($cr)),
      'next_debit' => '?',
    ];

    // render text

    // override some values for SEPA mandates
    if (in_array($cr['payment_instrument_id'], $this->getSepaPaymentInstruments())) {
      // this is a SEPA DD mandate
      $mandate = $this->getSepaByRecurringContributionId($cr['id'], $sepaMandates);
      $result['fields']['payment_instrument'] = "SEPA Direct Debit";
      $result['fields']['iban'] = $mandate['iban'];
      $result['fields']['org_iban'] = $sepaCreditors[$mandate['creditor_id']]['iban'];
      $result['fields']['creditor_name'] = $sepaCreditors[$mandate['creditor_id']]['name'];
      // $result['fields']['org_iban'] = $sepa;
      // $result['fields']['org_iban'] = $cr['id'];
      $result['fields']['next_debit'] = substr($cr['next_sched_contribution_date'], 0, 10);
      $result['label'] = "SEPA, {$result['fields']['amount']} {$result['fields']['frequency']} ({$mandate['reference']})";

      $result['text_summary'] = "
        Debitor name: {$result['fields']['display_name']}<br />
        Debitor account: {$result['fields']['iban']}<br />
        Creditor name: {$result['fields']['creditor_name']}<br />
        Creditor account: {$result['fields']['org_iban']}<br />
        Payment method: {$result['fields']['payment_instrument']}<br />
        Frequency: {$result['fields']['frequency']}<br />
        Annual amount: {$result['fields']['annual_amount']}&nbsp;{$cr['currency']}<br />
        Installment amount: {$result['fields']['amount']}&nbsp;{$cr['currency']}<br />
        Next debit: {$result['fields']['next_debit']}
      ";

    } else {
      // this is a non-SEPA recurring contribution
      $result['text_summary'] = "
        Debitor name: {$result['fields']['display_name']}<br />
        Payment method: {$result['fields']['payment_instrument']}<br />
        Frequency: {$result['fields']['frequency']}<br />
        Annual amount: {$result['fields']['annual_amount']}&nbsp;{$cr['currency']}<br />
        Installment amount: {$result['fields']['amount']}&nbsp;{$cr['currency']}<br />
      ";
      $result['label'] = "{$result['fields']['payment_instrument']}, {$result['fields']['amount']} {$result['fields']['frequency']}";
    }

    return $result;
  }


  /**
   * Get the status IDs for eligible recurring contributions
   */
  protected function getValidRcurStatusIds() {
    $validStatuses[] =  CRM_Core_PseudoConstant::getKey(
      'CRM_Contribute_BAO_Contribution',
      'contribution_status_id',
      'Pending'
    );
    $current_id = CRM_Core_PseudoConstant::getKey(
      'CRM_Contribute_BAO_Contribution',
      'contribution_status_id',
      'In Progress'
    );
    // "In Progress" may not exist on newer installs
    if (!empty($current_id)) {
      $validStatuses[] = $current_id;
    }
    return $validStatuses;
  }


  /**
   * ??
   * @author Michael
   */
  private function writeFrequency($cr){
    if($cr['frequency_interval']==1){
      $frequency = "Every {$cr['frequency_unit']}";
    }else{
      $frequency = "Every {$cr['frequency_interval']} {$cr['frequency_unit']}s";
    }

    // FIXME: use SepaLogic::getPaymentFrequencies
    $shortHands = [
      'Every 12 months' => 'annually',
      'Every year'      => 'annually',
      'Every month'     => 'monthly'
    ];
    if(array_key_exists($frequency, $shortHands)){
      return $shortHands[$frequency];
    }
    return $frequency;
  }

  /**
   * ??
   * @author Michael
   */
  private function calcAnnualAmount($cr){
    if($cr['frequency_unit']=='month'){
      $multiplier = 12;
    }elseif($cr['frequency_unit']=='year'){
      $multiplier = 1;
    }
    return $cr['amount'] * $multiplier / $cr['frequency_interval'];
  }

  /**
   * ??
   * @author Michael
   */
  public function writePaymentContractLabel($contributionRecur)
  {
      $paymentInstruments = $this->getPaymentInstruments();
      if (in_array($contributionRecur['payment_instrument_id'], $this->getSepaPaymentInstruments())) {
          $sepaMandate = civicrm_api3('SepaMandate', 'getsingle', array(
            'entity_table' => 'civicrm_contribution_recur',
            'entity_id' => $contributionRecur['id'],
          ));

          $plural = $contributionRecur['frequency_interval'] > 1 ? 's' : '';
          return "SEPA: {$sepaMandate['reference']} ({$contributionRecur['amount']} every {$contributionRecur['frequency_interval']} {$contributionRecur['frequency_unit']}{$plural})";
      } else {
          return "{$paymentInstruments[$contributionRecur['payment_instrument_id']]}: ({$contributionRecur['amount']} every {$contributionRecur['frequency_interval']} {$contributionRecur['frequency_unit']})";
      }
  }

  /**
   * get all payment instruments
   */
  protected function getPaymentInstruments() {
    if (!isset($this->paymentInstruments)) {
      // load payment instruments
      $paymentInstrumentOptions = civicrm_api3('OptionValue', 'get', array(
        'option_group_id' => "payment_instrument")
        )['values'];
      $this->paymentInstruments = array();
      foreach($paymentInstrumentOptions as $paymentInstrumentOption){
        // $this->paymentInstruments[$paymentInstrumentOption['value']] = $paymentInstrumentOption['name'];
        $this->paymentInstruments[$paymentInstrumentOption['value']] = $paymentInstrumentOption['label'];
      }
    }
    return $this->paymentInstruments;
  }

  /**
   * Get all CiviSEPA payment instruments(?)
   * @author Michael
   */
  public function getSepaPaymentInstruments() {
      if (!isset($this->sepaPaymentInstruments)) {
          $this->sepaPaymentInstruments = array();
          $result = civicrm_api3('OptionValue', 'get', array('option_group_id' => 'payment_instrument', 'name' => array('IN' => array('RCUR', 'OOFF', 'FRST'))));
          foreach ($result['values'] as $paymentInstrument) {
              $this->sepaPaymentInstruments[] = $paymentInstrument['value'];
          }
      }

      return $this->sepaPaymentInstruments;
  }

  /**
   * Get the CiviSEPA mandate id connected to the given recurring contribution,
   * from the given list.
   * @author Michael
   */
  private function getSepaByRecurringContributionId($id, $sepas){
    foreach($sepas as $sepa){
      if($sepa['entity_id'] == $id && $sepa['entity_table'] == 'civicrm_contribution_recur'){
        return $sepa;
      }
    }
  }

  /**
   * Return the date of the last successfully collected contribution
   *  of the give recurring contribution
   * If no such contribution is found, the current date is returned
   *
   * @return string date ('Y-m-d H:i:s')
   */
  public static function getNextInstallmentDate($contribution_recur_id, $cycle_days) {
    $now = date('Y-m-d H:i:s');

    if (!$contribution_recur_id) {
      return $now;
    }

    // load last successull collection for the recurring contribution
    $last_collection_search = civicrm_api3('Contribution', 'get', array(
      'contribution_recur_id'  => $contribution_recur_id,
      'contribution_status_id' => array('IN' => array(1,5)), // status Completed or In Progres
      'options'                => array('sort'  => 'receive_date desc',
                                        'limit' => 1),
      'return'                 => 'id,receive_date',
      ));

    if ($last_collection_search['count'] > 0) {
      $last_collection = reset($last_collection_search['values']);

      // load recurring contribution
      $contribution_recur = civicrm_api3('ContributionRecur', 'getsingle', array(
        'id'     => $contribution_recur_id,
        'return' => 'frequency_unit,frequency_interval'));

      // GP-1094: go back to last cycle day
      $safety_counter = 35;
      $last_collection_date = strtotime($last_collection['receive_date']);
      while (!in_array(date('j', $last_collection_date), array_keys($cycle_days)) && $safety_counter > 0) {
        $last_collection_date = strtotime("-1 day", $last_collection_date);
        $safety_counter -= 1;
      }
      if ($safety_counter == 0) {
        // something went wrong, reset:
        $last_collection_date = strtotime($last_collection['receive_date']);
      }

      // now calculate the next collection date
      $next_collection = date('Y-m-d H:i:s', strtotime("+{$contribution_recur['frequency_interval']} {$contribution_recur['frequency_unit']}", $last_collection_date));
      if ($next_collection > $now) {
        // only makes sense if in the future
        return $next_collection;
      }
    }

    // check recurring contribution start date
    $contribution_recur = civicrm_api3('ContributionRecur', 'getsingle', array(
      'id'     => $contribution_recur_id,
      'return' => 'start_date,contribution_status_id'));
    if (!empty($contribution_recur['start_date']) && !empty($contribution_recur['contribution_status_id'])) {
      $status_id = (int) $contribution_recur['contribution_status_id'];
      // only consider active recurring contributions (2=Pending, 5=in Progress)
      if ($status_id == 2 || $status_id == 5) {
        $start_date = date('Y-m-d H:i:s', strtotime($contribution_recur['start_date']));
        if ($start_date > $now) {
          return $start_date;
        }
      }
    }

    return $now;
  }

  /**
   * Calculate the new contributions's start date.
   * In most cases this is simply 'now', but in the case of a update
   * the membership period already paid by the donor should be respected
   *
   * @see https://redmine.greenpeace.at/issues/771
   */
  public static function getUpdateStartDate($current_state, $desired_state, $activity, $cycle_days) {
    $now = date('YmdHis');

    $update_activity_type  = CRM_Core_PseudoConstant::getKey(
      'CRM_Activity_BAO_Activity',
      'activity_type_id',
      'Contract_Updated'
    );

    $revive_activity_type  = CRM_Core_PseudoConstant::getKey(
      'CRM_Activity_BAO_Activity',
      'activity_type_id',
      'Contract_Revived'
    );

    $contribution_recur_id = CRM_Utils_Array::value('membership_payment.membership_recurring_contribution', $current_state);

    // check if it is a proper update/revive and if we should defer the start date to respect already paid periods
    if (
      $contribution_recur_id
      && in_array($activity['activity_type_id'], [$update_activity_type, $revive_activity_type], true)
      && $desired_state['contract_updates.ch_defer_payment_start'] == '1'
    ) {
      // load last successull collection for the recurring contribution
      $calculated_date = CRM_Contract_RecurringContribution::getNextInstallmentDate(
        $contribution_recur_id,
        $cycle_days
      );
      // re-format date (returned as 'Y-m-d H:i:s') and return
      return date('YmdHis', strtotime($calculated_date));
    }

    return $now;
  }

  /**
   * Get a list of (accepted) payment frequencies
   *
   * @return array list of payment frequencies
   */
  public static function getPaymentFrequencies() {
    // this is a hand-picked list of options
    $optionValues = civicrm_api3('OptionValue', 'get', array(
      'value'           => array('IN' => array(1, 2, 4, 12)),
      'return'          => 'label,value',
      'option_group_id' => 'payment_frequency',
    ));

    $options = array();
    foreach ($optionValues['values'] as $value) {
      $options[$value['value']] = $value['label'];
    }
    return $options;
  }

  /**
   * Post-process contributions related to payment processor ContributionRecurs
   *
   * @param \PostEvent $event
   *
   * @return void
   * @throws \API_Exception
   * @throws \CiviCRM_API3_Exception
   * @throws \Civi\API\Exception\UnauthorizedException
   */
  public static function amendContribution(Civi\Core\Event\PostEvent $event): void {
    if ($event->action === 'create' && $event->entity === 'Contribution') {
      $contribution = $event->object;
      if (empty($contribution->contribution_recur_id)) {
        return;
      }
      $contributionRecur = \Civi\Api4\ContributionRecur::get(FALSE)
        ->addWhere('id', '=', $contribution->contribution_recur_id)
        ->addWhere('payment_processor_id', 'IS NOT EMPTY')
        ->execute()
        ->first();
      if (empty($contributionRecur)) {
        return;
      }
      // Find all memberships using this recurring contribution
      // there should never be more than one, but this mirrors existing behaviour ...
      $memberships = \Civi\Api4\Membership::get(FALSE)
        ->addWhere('membership_payment.membership_recurring_contribution', '=', $contributionRecur['id'])
        ->execute();
      foreach ($memberships as $membership) {
        // track membership payments for each membership
        civicrm_api3('MembershipPayment', 'create', [
          'membership_id' => $membership['id'],
          'contribution_id' => $contribution->id,
        ]);
      }
    }
  }

  public static function getById($recurring_contribution_id) {
    return Api4\ContributionRecur::get(FALSE)
      ->addWhere('id', '=', $recurring_contribution_id)
      ->addSelect('*')
      ->execute()
      ->first();
  }

  public static function getCurrentForContract($membership_id) {
    $membership_result = Api4\Membership::get(FALSE)
      ->addWhere('id', '=', $membership_id)
      ->addSelect('membership_payment.membership_recurring_contribution')
      ->setLimit(1)
      ->execute();

    if ($membership_result->count() < 1) return NULL;

    $membership = $membership_result->first();

    return self::getById($membership['membership_payment.membership_recurring_contribution']);
  }

  public static function getLatestContribution($membership_id) {
    $membership_payment_result = civicrm_api3('MembershipPayment', 'get', [
      'membership_id' => $membership_id,
      'options' => [ 'limit' => 0 ],
      'sequential'    => 1,
    ]);

    if ($membership_payment_result['count'] < 1) return NULL;

    $contribution_ids = array_map(
      fn ($mp) => $mp['contribution_id'],
      $membership_payment_result['values']
    );

    return Api4\Contribution::get(FALSE)
      ->addWhere('id'                         , 'IN' , $contribution_ids)
      ->addWhere('contribution_status_id:name', 'IN', ['Completed', 'In Progress'])
      ->addSelect('*')
      ->addOrderBy('receive_date', 'DESC')
      ->setLimit(1)
      ->execute()
      ->first();
  }

}
