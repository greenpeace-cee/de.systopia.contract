<?php
/*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2017-2019 SYSTOPIA                             |
| Author: B. Endres (endres -at- systopia.de)                  |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/

/**
 * Interface to CiviBanking functions
 *
 * @todo resolve hard dependecy to CiviBanking module
 */
class CRM_Contract_BankingLogic {

  /** cached value for self::getCreditorBankAccount() */
  protected static $_creditorBankAccount = NULL;
  protected static $_ibanReferenceType = NULL;

  /**
   * get bank account information
   */
  public static function getBankAccount($account_id) {
    if (empty($account_id)) return NULL;

    $data = array();
    $account = civicrm_api3('BankingAccount', 'getsingle', array('id' => $account_id));
    $data['contact_id'] = $account['contact_id'];
    $data['id'] = $account['id'];
    if (!empty($account['data_parsed'])) {
      $data_parsed = json_decode($account['data_parsed'], TRUE);
      if ($data_parsed) {
        foreach ($data_parsed as $key => $value) {
          $data[$key] = $value;
          // also add in lower case to avoid stuff like bic/BIC confusion
          $data[strtolower($key)] = $value;
        }
      }
    }

    // load IBAN reference
    $reference = civicrm_api3('BankingAccountReference', 'getsingle', array(
      'ba_id'             => $account_id,
      'reference_type_id' => self::getIbanReferenceTypeID()));
    $data['iban'] = $reference['reference'];

    return $data;
  }

  /**
   * Get the ID of the BankingAccount entity representating the
   * submitted contact, IBAN, and BIC.
   * The account will be created if it doesn't exist yet
   *
   * @param $contact_id
   * @param $iban
   * @param $bic
   *
   * @return int account ID
   * @todo cache results?
   */
  public static function getOrCreateBankAccount($contact_id, $iban, $bic = NULL) {
    if (empty($iban)) {
      return '';
    }

    try {
      // find existing references
      $existing_references = civicrm_api3('BankingAccountReference', 'get', array(
        'reference'         => $iban,
        'reference_type_id' => self::getIbanReferenceTypeID(),
        'option.limit'      => 0));

      // get the accounts for this
      $bank_account_ids = array();
      foreach ($existing_references['values'] as $account_reference) {
        $bank_account_ids[] = $account_reference['ba_id'];
      }
      if (!empty($bank_account_ids)) {
        $contact_bank_accounts = civicrm_api3('BankingAccount', 'get', array(
          'id'           => array('IN' => $bank_account_ids),
          'contact_id'   => $contact_id,
          'option.limit' => 1));
        if ($contact_bank_accounts['count']) {
          // bank account already exists with the contact
          $account = reset($contact_bank_accounts['values']);
          return $account['id'];
        }
      }

      // if we get here, that means that there is no such bank account
      //  => create one
      $data = array('country' => substr($iban, 0, 2));
      if (!empty($bic)) {
        $data['BIC'] = $bic;
      }
      $bank_account = civicrm_api3('BankingAccount', 'create', array(
        'contact_id'  => $contact_id,
        'description' => "Bulk Importer",
        'data_parsed' => json_encode($data))
      );

      $bank_account_reference = civicrm_api3('BankingAccountReference', 'create', array(
        'reference'         => $iban,
        'reference_type_id' =>self::getIbanReferenceTypeID(),
        'ba_id'             => $bank_account['id']));
      return $bank_account['id'];
    } catch (Exception $e) {
      error_log("Couldn't add bank account '{$iban}' [{$contact_id}]");
    }
  }

  /**
   * Get the (target) bank account of the creditor
   */
  public static function getCreditorBankAccount() {
    if (self::$_creditorBankAccount === NULL) {
      $creditor = CRM_Sepa_Logic_Settings::defaultCreditor();
      self::$_creditorBankAccount = self::getOrCreateBankAccount($creditor->creditor_id, $creditor->iban, $creditor->bic);
    }
    return self::$_creditorBankAccount;
  }

  /**
   * return the IBAN for the given bank account id if there is one
   */
  public static function getIBANforBankAccount($bank_account_id) {
    $iban_references = civicrm_api3('BankingAccountReference', 'get', array(
      'ba_id'             => $bank_account_id,
      'reference_type_id' => self::getIbanReferenceTypeID(),
      'return'            => 'reference'));
    if ($iban_references['count'] > 0) {
      $reference = reset($iban_references['values']);
      return $reference['reference'];
    } else {
      return '';
    }
  }

  /**
   * Get the reference type ID for IBAN references (cached)
   */
  public static function getIbanReferenceTypeID() {
    if (self::$_ibanReferenceType === NULL) {
      $reference_type_value = civicrm_api3('OptionValue', 'getsingle', array(
        'value'           => 'IBAN',
        'return'          => 'id',
        'option_group_id' => 'civicrm_banking.reference_types',
        'is_active'       => 1));
      self::$_ibanReferenceType = $reference_type_value['id'];
    }
    return self::$_ibanReferenceType;
  }

  /**
   * Extract account (IDs) from a recurring contribution by looking at the most recent
   * contribution
   *
   * @param $contribution_recur_id ID of an recurring contribution entity
   * @return array (from_ba_id, to_ba_id)
   */
  public static function getAccountsFromRecurringContribution($contribution_recur_id) {
    $contribution_recur_id = (int) $contribution_recur_id;
    if (!empty($contribution_recur_id)) {
      try {
        // TODO: make this relationship configurable
        $most_recent_contribution = CRM_Core_DAO::executeQuery("
          SELECT from_ba, to_ba
          FROM civicrm_contribution c
            LEFT JOIN civicrm_value_contribution_information i ON i.entity_id = c.id
          WHERE c.contribution_recur_id = {$contribution_recur_id}
          ORDER BY receive_date DESC
          LIMIT 1;");
        if ($most_recent_contribution->fetch()) {
          return array($most_recent_contribution->from_ba, $most_recent_contribution->to_ba);
        }
      } catch (Exception $ex) {
        // the civicrm_value_contribution_information probably doesn't exist
      }
    }

    // fallback: empty
    return array('', '');
  }
}
