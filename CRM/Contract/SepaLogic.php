<?php
/*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2017 SYSTOPIA                                  |
| Author: B. Endres (endres -at- systopia.de)                  |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/

/**
 * Interface to CiviSEPA functions
 *
 * @todo resolve hard dependecy to CiviSEPA module
 */
class CRM_Contract_SepaLogic {

  private static $organisation_ibans = NULL;

  /**
   * Create a new SEPA mandate
   */
  public static function createNewMandate($params) {
    // TODO: fill? sanitise?

    // pass it to the SEPA API
    $new_mandate = civicrm_api3('SepaMandate', 'createfull', $params);

    // reload to get all values
    $new_mandate = civicrm_api3('SepaMandate', 'getsingle', array('id' => $new_mandate['id']));

    // create user message
    $mandate_url = CRM_Utils_System::url('civicrm/sepa/xmandate', "mid={$new_mandate['id']}");
    CRM_Core_Session::setStatus("New SEPA Mandate <a href=\"{$mandate_url}\">{$new_mandate['reference']}</a> created.", "Success", 'info');

    return $new_mandate;
  }

  /**
   * Adjust or update the given SEPA mandate according to the
   * requested change
   */
  public static function updateSepaMandate($membership_id, $current_state, $desired_state, $activity, $action = NULL) {
    // desired_state (from activity) hasn't resolved the numeric custom_ fields yet
    foreach ($desired_state as $key => $value) {
      if (preg_match('#^custom_\d+$#', $key)) {
        $full_key = CRM_Contract_Utils::getCustomFieldName($key);
        $desired_state[$full_key] = $value;
      }
    }

    // all relevant fields (activity field  -> membership field)
    $mandate_relevant_fields = array(
      'contract_updates.ch_annual'                 => 'membership_payment.membership_annual',
      'contract_updates.ch_from_ba'                => 'membership_payment.from_ba',
      // 'contract_updates.ch_to_ba'                  => 'membership_payment.to_ba', // TODO: implement when multiple creditors are around
      'contract_updates.ch_frequency'              => 'membership_payment.membership_frequency',
      'contract_updates.ch_cycle_day'              => 'membership_payment.cycle_day',
      'contract_updates.ch_recurring_contribution' => 'membership_payment.membership_recurring_contribution');

    // calculate changes to see whether we have to act
    $mandate_relevant_changes = array();
    foreach ($mandate_relevant_fields as $desired_field_name => $current_field_name) {
      if (    isset($desired_state[$desired_field_name])
           && $desired_state[$desired_field_name] != CRM_Utils_Array::value($current_field_name, $current_state)) {
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
      return NULL;
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
            $annual_amount = self::formatMoney($recurring_contribution['amount'] * $frequency);
          }
          if (empty($from_ba)) {
            $mandate = self::getMandateForRecurringContributionID($recurring_contribution_id);
            if ($mandate) {
              $from_ba = CRM_Contract_BankingLogic::getOrCreateBankAccount($current_state['contact_id'], $mandate['iban'], $mandate['bic']);
            }
          }
        }
      }


      // calculate some stuff
      if ($cycle_day < 1 || $cycle_day > 30) {
        // invalid cycle day
        $cycle_day = self::nextCycleDay();
      }

      // calculate amount
      $frequency_interval = 12 / $frequency;
      $amount = self::formatMoney($annual_amount / $frequency);
      if ($amount < 0.01) {
        throw new Exception("Installment amount too small");
      }

      // get bank account
      $donor_account = CRM_Contract_BankingLogic::getBankAccount($from_ba);
      if (empty($donor_account['bic']) && self::isLittleBicExtensionAccessible()) {
        $bic_search = civicrm_api3('Bic', 'findbyiban', array('iban' => $donor_account['iban']));
        if (!empty($bic_search['bic'])) {
          $donor_account['bic'] = $bic_search['bic'];
        }
      }
      if (empty($donor_account['iban'])) {
        throw new Exception("No donor bank account given.");
      }
      if (empty($donor_account['bic'])) {
        // this could be problem until SEPA-245 is implemented
        //  (https://github.com/Project60/org.project60.sepa/issues/245)
        error_log("de.systopia.contract: Trying to create SepaMandate without BIC for membership [{$membership_id}].");
        CRM_Core_Session::setStatus(ts("The created mandate for membership [%1] has no BIC.", [1 => $membership_id]), ts('Potential Problem'), 'alert');
      }


      // we need to create a new mandate
      $new_mandate_values =  array(
        'type'               => 'RCUR',
        'contact_id'         => $current_state['contact_id'],
        'amount'             => $amount,
        'currency'           => self::getCreditor()->currency,
        'start_date'         => self::getMandateUpdateStartDate($current_state, $current_state, $desired_state, $activity),
        'creation_date'      => date('YmdHis'), // NOW
        'date'               => date('YmdHis', strtotime($activity['activity_date_time'])),
        'validation_date'    => date('YmdHis'), // NOW
        'iban'               => $donor_account['iban'],
        'bic'                => $donor_account['bic'],
        // 'source'             => ??
        'campaign_id'        => $campaign_id,
        'financial_type_id'  => 2, // Membership Dues
        'frequency_unit'     => 'month',
        'cycle_day'          => $cycle_day,
        'frequency_interval' => $frequency_interval,
        );

      // create and reload (to get all data)
      $new_mandate = self::createNewMandate($new_mandate_values);
      $new_recurring_contribution = $new_mandate['entity_id'];

    } else {
      // another (existing) recurring contribution has been chosen by the user:
      $new_recurring_contribution = (int) CRM_Utils_Array::value('contract_updates.ch_recurring_contribution', $desired_state, CRM_Utils_Array::value('membership_payment.membership_recurring_contribution', $current_state));
    }

    // finally: terminate the old one
    if (!empty($current_state['membership_payment.membership_recurring_contribution'])) {
      self::terminateSepaMandate($current_state['membership_payment.membership_recurring_contribution']);
    }

    // and set the new recurring contribution
    return $new_recurring_contribution;
  }

  /**
   * Terminate the mandate connected ot the recurring contribution
   * (if there is one)
   */
  public static function terminateSepaMandate($recurring_contribution_id, $reason = 'CHNG') {
    $mandate = self::getMandateForRecurringContributionID($recurring_contribution_id);
    if ($mandate) {
      // FIXME: use "now" instead of "today" once that's fixed in CiviSEPA
      CRM_Sepa_BAO_SEPAMandate::terminateMandate($mandate['id'], "today", $reason);
    } else {
      if ($recurring_contribution_id) {
        // set (other) recurring contribution to 'COMPLETED' [1]
        civicrm_api3('ContributionRecur', 'create', array(
          'id'                     => $recurring_contribution_id,
          'end_date'               => date('YmdHis'),
          'contribution_status_id' => 1));
      }
    }
  }

  /**
   * Pause the mandate connected ot the recurring contribution
   * (if there is one)
   */
  public static function pauseSepaMandate($recurring_contribution_id) {
    $mandate = self::getMandateForRecurringContributionID($recurring_contribution_id);
    if ($mandate) {
      if ($mandate['status'] == 'RCUR' || $mandate['status'] == 'FRST') {
        // only for active mandates:
        // set status to ONHOLD
        civicrm_api3('SepaMandate', 'create', array(
          'id'     => $mandate['id'],
          'status' => 'ONHOLD'));

        // delete any scheduled (pending) contributions
        $pending_contributions = civicrm_api3('Contribution', 'get', array(
          'return'                 => 'id',
          'contribution_recur_id'  => $mandate['entity_id'],
          'contribution_status_id' => (int) CRM_Core_OptionGroup::getValue('contribution_status', 'Pending', 'name'),
          'receive_date'           => array('>=' => date('YmdHis'))));
        foreach ($pending_contributions['values'] as $pending_contribution) {
          civicrm_api3("Contribution", "delete", array('id' => $pending_contribution['id']));
        }
      } else {
        throw new Exception("Mandate is not active, cannot be paused");
      }

    } else {
      // NON-SEPA contributions not be changed, see GP-796
    }
  }

  /**
   * Resume the mandate connected ot the recurring contribution
   * (if there is one)
   */
  public static function resumeSepaMandate($recurring_contribution_id) {
    $mandate = self::getMandateForRecurringContributionID($recurring_contribution_id);
    if ($mandate) {
      if ($mandate['status'] == 'ONHOLD') {
        $new_status = empty($mandate['first_contribution_id']) ? 'FRST' : 'RCUR';
        civicrm_api3('SepaMandate', 'create', array(
          'id'     => $mandate['id'],
          'status' => $new_status));
      } else {
        throw new Exception(" Mandate is not paused, cannot be activated");
      }

    } else {
      // NON-SEPA contributions not be changed, see GP-796
    }
  }


  /**
   * Calculate the new mandate's start date.
   * In most cases this is simply 'now', but in the case of a update
   * the membership period already paid by the donor should be respected
   *
   * @see https://redmine.greenpeace.at/issues/771
   */
  public static function getMandateUpdateStartDate($current_state, $current_state, $desired_state, $activity) {
    $now = date('YmdHis');
    $update_activity_type  = CRM_Core_OptionGroup::getValue('activity_type', 'Contract_Updated', 'name');
    $contribution_recur_id = CRM_Utils_Array::value('membership_payment.membership_recurring_contribution', $current_state);

    // check if it is a proper update
    if ($contribution_recur_id && $activity['activity_type_id'] == $update_activity_type) {
      // load last successull collection for the recurring contribution
      $calculated_date = self::getNextInstallmentDate($contribution_recur_id);
      // re-format date (returned as 'Y-m-d H:i:s') and return
      return date('YmdHis', strtotime($calculated_date));
    }

    return $now;
  }


  /**
   * Return the date of the last successfully collected contribution
   *  of the give recurring contribution
   * If no such contribution is found, the current date is returned
   *
   * @return string date ('Y-m-d H:i:s')
   */
  public static function getNextInstallmentDate($contribution_recur_id) {
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
      $cycle_days = array_keys(self::getCycleDays());
      $safety_counter = 35;
      $last_collection_date = strtotime($last_collection['receive_date']);
      while (!in_array(date('j', $last_collection_date), $cycle_days) && $safety_counter > 0) {
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
   * Return the mandate entity if there is one attached to this recurring contribution
   *
   * @return mandate or NULL if there is not a (unique) match
   */
  public static function getMandateForRecurringContributionID($recurring_contribution_id) {
    if (empty($recurring_contribution_id)) {
      return NULL;
    }

    // load mandate
    $mandate = civicrm_api3('SepaMandate', 'get', array(
      'entity_id'    => $recurring_contribution_id,
      'entity_table' => 'civicrm_contribution_recur',
      'type'         => 'RCUR'));

    if ($mandate['count'] == 1 && $mandate['id']) {
      return reset($mandate['values']);
    } else {
      return NULL;
    }
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
   * Get next collections
   *
   * @return array list cycle_day => next collection date
   */
  public static function getNextCollections($now_string = 'NOW') {
    $cycle_days   = self::getCycleDays();
    $nextCycleDay = self::nextCycleDay();
    $nextCollections = array();

    // jump to nearest cycle day
    $now = strtotime($now_string);
    while (date('d', $now) != $nextCycleDay) {
      $now = strtotime('+ 1 day', $now);
    }
    $nextCollections[(int) $nextCycleDay] = date('Y-m-d', $now);

    // add the other cycle days
    while (date('d', $now) != $nextCycleDay) {
      $now = strtotime('+ 1 day', $now);
    }

    // now add the other cycle days
    for ($i=1; $i < count($cycle_days); $i++) {
      $now = strtotime('+ 1 day', $now);
      while (!in_array(date('d', $now), $cycle_days)) {
        $now = strtotime('+ 1 day', $now);
      }
      // found one
      $nextCollections[(int) date('d', $now)] = date('Y-m-d', $now);
    }

    return $nextCollections;
  }


  /**
   * Get the available cycle days
   *
   * @return array list of accepted cycle days
   */
  public static function getCycleDays() {
    $creditor = CRM_Contract_SepaLogic::getCreditor();
    return CRM_Sepa_Logic_Settings::getListSetting("cycledays", range(1, 28), $creditor->id);
  }

  /**
   * Get the creditor to be used for Contracts
   *
   * @return object creditor (BAO)
   */
  public static function getCreditor() {
    // currently we're just using the default creditor
    return CRM_Sepa_Logic_Settings::defaultCreditor();
  }

  /**
   * Calculate the next possible cycle day
   *
   * @return int next valid cycle day
   */
  public static function nextCycleDay() {
    $creditor = CRM_Sepa_Logic_Settings::defaultCreditor();
    $buffer_days = (int) CRM_Sepa_Logic_Settings::getSetting("pp_buffer_days") + (int) CRM_Sepa_Logic_Settings::getSetting("batching.FRST.notice", $creditor->id);
    $cycle_days = self::getCycleDays();

    $safety_counter = 32;
    $start_date = strtotime("+{$buffer_days} day", strtotime('now'));
    while (!in_array(date('d', $start_date), $cycle_days)) {
      $start_date = strtotime('+ 1 day', $start_date);
      $safety_counter -= 1;
      if ($safety_counter == 0) {
        throw new Exception("There's something wrong with the nextCycleDay method.");
      }
    }
    return (int) date('d', $start_date);
  }

  /**
   * check if this $iban is one of the organisation's own
   */
  public static function isOrganisationIBAN($iban) {
    if (empty($iban)) return FALSE;

    if (self::$organisation_ibans === NULL) {
      // load organisation's IBANs
      self::$organisation_ibans = array();
      $query = CRM_Core_DAO::executeQuery("SELECT reference
  FROM civicrm_bank_account_reference
  LEFT JOIN civicrm_bank_account ON civicrm_bank_account_reference.ba_id = civicrm_bank_account.id
  LEFT JOIN civicrm_option_value ON civicrm_bank_account_reference.reference_type_id = civicrm_option_value.id
 WHERE civicrm_bank_account.contact_id = 1
   AND civicrm_option_value.name = 'IBAN'");
      while ($query->fetch()) {
        self::$organisation_ibans[] = $query->reference;
      }
      $query->free();
    }
    // error_log("$iban vs. " . json_encode(self::$organisation_ibans));
    return in_array($iban, self::$organisation_ibans);
  }

  /**
   * Validate the given IBAN
   *
   * @return TRUE if IBAN is valid
   */
  public static function validateIBAN($iban) {
    return NULL == CRM_Sepa_Logic_Verification::verifyIBAN($iban);
  }

  /**
   * Checks whether the "Little BIC Extension" is installed
   *
   * @return TRUE if it is
   */
  public static function isLittleBicExtensionAccessible() {
    return CRM_Sepa_Logic_Settings::isLittleBicExtensionAccessible();
  }

  /**
   * Validate the given BIC
   *
   * @return TRUE if BIC is valid
   */
  public static function validateBIC($bic) {
    return NULL == CRM_Sepa_Logic_Verification::verifyBIC($bic);
  }

  /**
   * formats a value to the CiviCRM failsafe format: 0.00 (e.g. 999999.90)
   * even if there are ',' in there, which are used in some countries
   * (e.g. Germany, Austria,) as a decimal point.
   *
   * @todo move to CiviSEPA, then use that
   */
  public static function formatMoney($raw_value) {
    // strip whitespaces
    $stripped_value = preg_replace('#\s#', '', $raw_value);

    // find out if there's a problem with ','
    if (strpos($stripped_value, ',') !== FALSE) {
      // if there are at least three digits after the ','
      //  it's a thousands separator
      if (preg_match('#,\d{3}#', $stripped_value)) {
        // it's a thousands separator -> just strip
        $stripped_value = preg_replace('#,#', '', $stripped_value);
      } else {
        // it has to be interpreted as a decimal
        // first remove all other decimals
        $stripped_value = preg_replace('#[.]#', '', $stripped_value);
        // then replace with decimal
        $stripped_value = preg_replace('#,#', '.', $stripped_value);
      }
    }

    // finally format properly
    $clean_value = number_format($stripped_value, 2, '.', '');
    return $clean_value;
  }


  /**
   * prepare and inject the sepa tools JS
   */
  public static function addJsSepaTools() {
    // calculate creditor parameters
    $creditor_parameters = civicrm_api3('SepaCreditor', 'get', array(
      'options.limit' => 0))['values'];
    foreach ($creditor_parameters as &$creditor) {
      $creditor['grace']  = (int) CRM_Sepa_Logic_Settings::getSetting("batching.RCUR.grace", $creditor['id']);
      $creditor['notice'] = (int) CRM_Sepa_Logic_Settings::getSetting("batching.RCUR.notice", $creditor['id']);
    }

    // set the default
    $default_creditor_id = (int) CRM_Sepa_Logic_Settings::getSetting('batching_default_creditor');
    $creditor_parameters['default'] = $creditor_parameters[$default_creditor_id];

    // prep script and inject
    $script = file_get_contents(__DIR__ . '/../../js/sepa_tools.js');
    $script = str_replace('SEPA_CREDITOR_PARAMETERS', json_encode($creditor_parameters), $script);
    CRM_Core_Region::instance('page-header')->add(array('script' => $script));
  }
}
