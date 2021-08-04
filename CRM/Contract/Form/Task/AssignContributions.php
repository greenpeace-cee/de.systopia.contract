<?php
/*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2018 SYSTOPIA                                  |
| Author: B. Endres (endres -at- systopia.de)                  |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/

use Civi\Api4\Contribution;
use CRM_Contract_ExtensionUtil as E;
require_once 'CRM/Core/Form.php';

/**
 * Form controller class
 *
 * @see http://wiki.civicrm.org/confluence/display/CRMDOC43/QuickForm+Reference
 */
class CRM_Contract_Form_Task_AssignContributions extends CRM_Contribute_Form_Task {

  protected static $sepa_pi_names = array('OOFF', 'RCUR', 'FRST');

  protected $_eligibleContracts = NULL;

  /**
   * Compile task form
   */
  function buildQuickForm() {
    CRM_Utils_System::setTitle(E::ts('Assign %1 Contributions to:', array(1 => count($this->_contributionIds))));

    // get all contracts
    $contracts = $this->getEligibleContracts();
    $this->assign('contracts', json_encode($contracts));

    // contract selector
    $this->addElement('select',
        'contract_id',
        E::ts('Contract'),
        $this->getContractList($contracts),
        array('class' => 'crm-select2 huge'));

    // option: adjust financial type?
    $this->addCheckbox(
        'adjust_financial_type',
        E::ts('Adjust Financial Type'),
        ['' => true]);
    $this->setDefaults(['adjust_financial_type' => 'checked']);

    $this->addCheckbox(
      'adjust_campaign',
      E::ts('Adjust Campaign'),
      ['' => true]);

    // option: re-assign
    $this->addCheckbox(
        'reassign',
        E::ts('Re-Assign'),
        ['' => true]);

    // option: also assign to recurring contribution [no, yes, yes and adjust start data, only if within start/end date
    $this->addElement('select',
        'assign_mode',
        E::ts('Assign to Recurring'),
        array(
            'no'     => E::ts("no"),
            'yes'    => E::ts("yes"),
            'adjust' => E::ts("adjust start and end date"),
            'in'     => E::ts("only if within start/end date"),
        ),
        array('class' => 'crm-select2'));

    CRM_Core_Form::addDefaultButtons(E::ts("Assign"));
  }

  /**
   * Execute the user's choice
   */
  function postProcess() {
    $values = $this->exportValues();
    $contribution_id_list = implode(',', $this->_contributionIds);
    $excluded_contribution_ids = array();

    $contracts = $this->getEligibleContracts();
    $contract = $contracts[$values['contract_id']];
    $contract_id = (int) $contract['id'];
    $contribution_recur = NULL;

    if (empty($contract)) {
      throw new Exception("No contract selected!");
    }

    if (empty($values['reassign'])) {
      // only assign currently unassigned ones -> add the assigned ones to the list
      $currently_assigned = CRM_Core_DAO::executeQuery("SELECT contribution_id FROM civicrm_membership_payment WHERE contribution_id IN ({$contribution_id_list});");
      while ($currently_assigned->fetch()) {
        $excluded_contribution_ids[] = $currently_assigned->contribution_id;
      }

    } else {
      // detach all contributions
      CRM_Core_DAO::executeQuery("DELETE FROM civicrm_membership_payment WHERE contribution_id IN ({$contribution_id_list})");
    }

    // assign contributions
    $excluded_contribution_id_list = implode(',', $excluded_contribution_ids);
    $NOT_IN_EXCLUDED = empty($excluded_contribution_id_list) ? 'TRUE' : "id NOT IN ({$excluded_contribution_id_list})";
    CRM_Core_DAO::executeQuery("INSERT IGNORE INTO civicrm_membership_payment (contribution_id,membership_id)
                                      SELECT
                                        id              AS contribution_id,
                                        {$contract_id}  AS membership_id
                                      FROM civicrm_contribution
                                      WHERE id IN ({$contribution_id_list})
                                        AND {$NOT_IN_EXCLUDED};");
    CRM_Core_Session::setStatus(E::ts("Assigned %1 contribution(s) to contract [%2]", array(
        1 => count($this->_contributionIds) - count($excluded_contribution_ids),
        2 => $contract_id)), E::ts("Success"), 'info');


    // load required contribution information
    $contribution_query = civicrm_api3('Contribution', 'get', array(
        'id'           => ['IN' => $this->_contributionIds],
        'options'      => ['limit' => 0],
        'sequential'   => 0,
        'return'       => 'receive_date,payment_instrument_id,id'));
    $contributions = $contribution_query['values'];

    // load recurring contribution, too
    $contribution_recur = civicrm_api3('ContributionRecur', 'getsingle', array(
        'id'     => $contract['contribution_recur_id'],
        'return' => 'start_date,end_date,campaign_id'));
    $contribution_recur['start_date'] = empty($contribution_recur['start_date']) ? NULL : date('YmdHis', strtotime($contribution_recur['start_date']));
    $contribution_recur['end_date']   = empty($contribution_recur['end_date'])   ? NULL : date('YmdHis', strtotime($contribution_recur['end_date']));

    // load SEPA payment instruments
    $sepa_pi_query = civicrm_api3('OptionValue', 'get', array(
        'option_group_id' => 'payment_instrument',
        'return'          => 'value,name',
        'name'            => ['IN' => self::$sepa_pi_names],
        'options'         => ['limit' => 0]));
    $sepa_pis = array();
    foreach ($sepa_pi_query['values'] as $value) {
      $sepa_pis[] = $value['value'];
    }

    // finally: update every single contribution
    $update_count = 0;
    $min_date = NULL;
    $max_date = NULL;
    foreach ($this->_contributionIds as $contribution_id) {
      if (in_array($contribution_id, $excluded_contribution_ids)) {
        continue; // this one has not been re-assiged -> no updates
      }

      // let's go...
      $contribution = $contributions[$contribution_id];
      $contribution_update = array();
      // update financial type - if requested
      if (!empty($values['adjust_financial_type'])) {
        $contribution_update['financial_type_id'] = $contract['financial_type_id'];
      }
      // update campaign if requested
      if (!empty($values['adjust_campaign'])) {
        // if rcur has no campaign, pass null as string to force it to NULL
        $contribution_update['campaign_id'] = $contribution_recur['campaign_id'] ?? 'NULL';
      }

      // now the non-sepa options: if NOT a SEPA contract AND NOT a SEPA contribution
      if (empty($contract['sepa_mandate_id']) && !in_array($contribution['payment_instrument_id'], $sepa_pis)) {
        // REMOVED: adjust payment instrument - if requested
        // if (!empty($values['adjust_pi'])) {
        //   $contribution_update['payment_instrument_id'] = $values['adjust_pi'];
        // }

        // assign to the recurring contribution
        switch ($values['assign_mode']) {
          case 'yes':
            // assign no matter what
            $contribution_update['contribution_recur_id'] = $contract['contribution_recur_id'];
            break;

          case 'adjust':
            // assign contribution in any case
            $contribution_update['contribution_recur_id'] = $contract['contribution_recur_id'];
            // but keep a record of the range, so we can potentially adjust it
            $receive_date = date('YmdHis', strtotime($contribution['receive_date']));
            if ($min_date == NULL || $min_date > $receive_date) {
              $min_date = $receive_date;
            }
            if ($max_date == NULL || $max_date < $receive_date) {
              $max_date = $receive_date;
            }
            break;

          case 'in':
            // only assign contribution if already in the start_date - end_date range
            $receive_date = date('YmdHis', strtotime($contribution['receive_date']));
            if (   ($contribution_recur['start_date'] == NULL || $contribution_recur['start_date'] <= $receive_date)
                && ($contribution_recur['end_date'] == NULL   || $contribution_recur['end_date']   >= $receive_date)) {
              $contribution_update['contribution_recur_id'] = $contract['contribution_recur_id'];
            }
            break;

          default:
          case 'no':
            // do nothing
            break;
        }
      }

      // finally: run the upgrade
      if (!empty($contribution_update)) {
        Contribution::update()
          ->addWhere('id', '=', $contribution_id)
          ->setValues($contribution_update)
          ->setCheckPermissions(FALSE)
          ->execute();
        $update_count += 1;
      }
    }

    // recurring contribution adjustment
    if (empty($contract['sepa_mandate_id']) && $values['assign_mode'] == 'adjust') {
      $contribution_recur_update = array();
      if ($min_date != NULL && $contribution_recur['start_date'] != NULL && $min_date < $contribution_recur['start_date']) {
        $contribution_recur_update['start_date'] = $min_date;
      }
      if ($max_date != NULL && $contribution_recur['end_date'] != NULL   && $max_date > $contribution_recur['end_date']) {
        $contribution_recur_update['end_date'] = $max_date;
      }
      if (!empty($contribution_recur_update)) {
        $contribution_recur_update['id'] = $contract['contribution_recur_id'];
        civicrm_api3('ContributionRecur', 'create', $contribution_recur_update);
        CRM_Core_Session::setStatus(E::ts("Adjusted date range of recurring contribution [%1]", array(1 => $contract['contribution_recur_id'])), E::ts("Success"), 'info');
      }
    }

    // see if we need to adjust the bank accounts
    if (empty($contract['sepa_mandate_id']) && $values['assign_mode'] != 'no') {
      // something might have changed, check the accounts
      [$from_ba, $to_ba] = CRM_Contract_BankingLogic::getAccountsFromRecurringContribution($contract['contribution_recur_id']);

      if ($from_ba != $contract['from_ba'] || $to_ba != $contract['to_ba']) {
        $contract_update = [];
        if ($from_ba != $contract['from_ba']) {
          $field_key = CRM_Contract_Utils::getCustomFieldId('membership_payment.from_ba');
          $contract_update[$field_key] = $from_ba;
        }
        if ($to_ba != $contract['to_ba']) {
          $field_key = CRM_Contract_Utils::getCustomFieldId('membership_payment.to_ba');
          $contract_update[$field_key] = $to_ba;
        }

        if (!empty($contract_update)) {
          $contract_update['id'] = $contract['id'];
          $contract_update['skip_handler'] = 1;
          civicrm_api3('Membership', 'create', $contract_update);
        }
      }
    }

    // done:
    if ($update_count > 0) {
      CRM_Core_Session::setStatus(E::ts("%1 contribution(s) were adjusted as requested.", array(1 => $update_count)), E::ts("Success"), 'info');
    }
  }



  /**
   * Get a list of all eligible contracts
   */
  protected function getEligibleContracts() {
    if ($this->_eligibleContracts === NULL) {
      // get pi group id
      $payment_instruments_group_id = civicrm_api3('OptionGroup', 'getvalue', array(
          'return' => 'id',
          'name'   => 'payment_instrument'));
      if (empty($payment_instruments_group_id)) $payment_instruments_group_id = 10; // fallback

      $this->_eligibleContracts = array();
      $contribution_id_list = implode(',', $this->_contributionIds);
      $search = CRM_Core_DAO::executeQuery("
      SELECT
       m.id                                AS contract_id,
       m.contact_id                        AS contact_id,
       m.start_date                        AS start_date,
       m.status_id                         AS status_id,
       m.membership_type_id                AS membership_type_id,
       f.id                                AS financial_type_id,
       f.name                              AS financial_type,
       p.from_ba                           AS from_ba,
       p.to_ba                             AS to_ba,
       p.membership_recurring_contribution AS contribution_recur_id,
       IF(pi.name IN ('RCUR', 'FRST'), 'SEPA', pi.label)
                                           AS contribution_recur_pi,
       s.id                                AS sepa_mandate_id,
       cmp.title                           AS rcur_campaign
      FROM civicrm_contribution c
      LEFT JOIN civicrm_membership m               ON m.contact_id = c.contact_id
      LEFT JOIN civicrm_value_membership_payment p ON p.entity_id = m.id
      LEFT JOIN civicrm_contribution_recur r       ON r.id = p.membership_recurring_contribution
      LEFT JOIN civicrm_option_value pi            ON pi.value = r.payment_instrument_id AND pi.option_group_id = {$payment_instruments_group_id}
      LEFT JOIN civicrm_membership_type t          ON t.id = m.membership_type_id
      LEFT JOIN civicrm_financial_type f           ON f.id = t.financial_type_id
      LEFT JOIN civicrm_sdd_mandate s              ON s.entity_id = p.membership_recurring_contribution
                                                    AND s.entity_table = 'civicrm_contribution_recur'
      LEFT JOIN civicrm_campaign cmp               ON cmp.id = r.campaign_id
      WHERE c.id IN ({$contribution_id_list})
        AND p.membership_recurring_contribution IS NOT NULL
      GROUP BY m.id
      ORDER BY m.status_id ASC, m.start_date DESC;");
      while ($search->fetch()) {
        $this->_eligibleContracts[$search->contract_id] = array(
            'id'                    => $search->contract_id,
            'start_date'            => $search->start_date,
            'status_id'             => $search->status_id,
            'membership_type_id'    => $search->membership_type_id,
            'contribution_recur_id' => $search->contribution_recur_id,
            'contribution_recur_pi' => $search->contribution_recur_pi,
            'sepa_mandate_id'       => $search->sepa_mandate_id,
            'financial_type'        => $search->financial_type,
            'from_ba'               => $search->from_ba,
            'to_ba'                 => $search->to_ba,
            'contact_id'            => $search->contact_id,
            'financial_type_id'     => $search->financial_type_id,
            'rcur_campaign'         => $search->rcur_campaign,
        );
      }
    }

    return $this->_eligibleContracts;
  }

  /**
   * Get a list of all eligible contracts
   */
  protected function getContractList($contracts) {
    $contract_list = array();

    // load membership types
    $membership_types = civicrm_api3('MembershipType', 'get', array(
        'option.limit' => 0,
        'sequential'   => 0,
        'return'       => 'name'));
    $membership_status = civicrm_api3('MembershipStatus', 'get', array(
        'option.limit' => 0,
        'sequential'   => 0,
        'return'       => 'label'));

    foreach ($contracts as $contract) {
      $contract_list[$contract['id']] = E::ts("[%4] '%1' since %2 (%3) - %5", array(
          1 => $membership_types['values'][$contract['membership_type_id']]['name'],
          2 => substr($contract['start_date'], 0, 4),
          3 => $membership_status['values'][$contract['status_id']]['label'],
          4 => $contract['id'],
          5 => $contract['contribution_recur_pi']
          ));
    }

    return $contract_list;
  }

  /**
   * Get a list of all eligible contracts
   */
  protected function getEligiblePaymentInstruments() {
    $eligible_pis = array();
    $all_pis = civicrm_api3('OptionValue', 'get', array(
        'option_group_id' => 'payment_instrument',
        'is_active'       => 1,
        'return'          => 'value,name,label',
        'option.limit'    => 0));
    foreach ($all_pis['values'] as $pi) {
      if (!in_array($pi['name'], self::$sepa_pi_names)) {
        $eligible_pis[$pi['value']] = $pi['label'];
      }
    }

    return array('' => E::ts('no')) + $eligible_pis;
  }

}
