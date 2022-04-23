<?php
/*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2019 SYSTOPIA                                  |
| Author: B. Endres (endres -at- systopia.de)                  |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/

use CRM_Contract_ExtensionUtil as E;

/**
 * Base class for contract changes. These are tracked changes to
 *  a contract, represented by an activity
 *
 * This new 'Change' concept is the replacement for the CRM_Contract_ModificationActivity
 *  and the CRM_Contract_Handlers
 */
abstract class CRM_Contract_Change implements CRM_Contract_Change_SubjectRendererInterface {

  /**
   * Data representing the data. Will mostly be the activity data
   */
  protected $data = NULL;

  /**
   * Contract data (cached)
   */
  protected $contract = NULL;

  /**
   * List of known changes,
   *  activity_type_name => change class
   */
  public static $type2class = [
    'Contract_Signed'    => 'CRM_Contract_Change_Sign',
    'Contract_Cancelled' => 'CRM_Contract_Change_Cancel',
    'Contract_Updated'   => 'CRM_Contract_Change_Upgrade',
    'Contract_Resumed'   => 'CRM_Contract_Change_Resume',
    'Contract_Revived'   => 'CRM_Contract_Change_Revive',
    'Contract_Paused'    => 'CRM_Contract_Change_Pause',
  ];

  /**
   * List of known actions,
   *  activity_type_name => change class
   */
  protected static $action2class = [
      'sign'    => 'CRM_Contract_Change_Sign',
      'cancel'  => 'CRM_Contract_Change_Cancel',
      'update'  => 'CRM_Contract_Change_Upgrade',
      'resume'  => 'CRM_Contract_Change_Resume',
      'revive'  => 'CRM_Contract_Change_Revive',
      'pause'   => 'CRM_Contract_Change_Pause',
  ];

  /**
   * List of activity_type_id => change class
   * Will be be populated on demand
   */
  protected static $_type_id2class = NULL;

  /**
   * @var array Maps the contract fields to the change activity fields
   */
  protected static $field_mapping_change_contract = [
      'membership_type_id'                                   => 'contract_updates.ch_membership_type',
      'membership_payment.membership_recurring_contribution' => 'contract_updates.ch_recurring_contribution',
      'membership_payment.payment_instrument'                => 'contract_updates.ch_payment_instrument',
      'membership_payment.membership_annual'                 => 'contract_updates.ch_annual',
      'membership_payment.membership_frequency'              => 'contract_updates.ch_frequency',
      'membership_payment.from_ba'                           => 'contract_updates.ch_from_ba',
      'membership_payment.to_ba'                             => 'contract_updates.ch_to_ba',
      'membership_payment.cycle_day'                         => 'contract_updates.ch_cycle_day',
      'membership_payment.defer_payment_start'               => 'contract_updates.ch_defer_payment_start',
  ];


  /**
   * CRM_Contract_Change constructor.
   * @param $data
   */
  protected function __construct($data) {
    $this->data = $data;
    // make sure activity_type_id is numeric
    $this->data['activity_type_id'] = $this->getActivityTypeID();
  }

  ################################################################################
  ##                          ABSTRACT FUNCTIONS                                ##
  ################################################################################

  /**
   * Apply the given change to the contract
   *
   * @throws Exception should anything go wrong in the execution
   */
  abstract public function execute();

  /**
   * Get a list of required fields for this type
   *
   * @return array list of required fields
   */
  abstract public function getRequiredFields();

  /**
   * Get action name for
   *
   * @param $contract_after       array  data of the contract after
   * @param $contract_before      array  data of the contract before
   * @return                      string the subject line
   */
  abstract public function renderDefaultSubject($contract_after, $contract_before = NULL);


  ################################################################################
  ##                           COMMON FUNCTIONS                                 ##
  ################################################################################

  /**
   * Check whether this change activity should actually be created
   *
   * @throws Exception if the creation should be disallowed
   */
  public function shouldBeAccepted() {}

  /**
   * Make sure that the data for this change is valid
   *
   * This performs checks that apply to all contract changes. Further checks
   * may be performed in child classes.
   *
   * @throws Exception if the data is not valid
   */
  public function verifyData() {
    // check if all required fields are there
    $required_fields = $this->getRequiredFields();
    foreach ($required_fields as $required_field) {
      if (!isset($this->data[$required_field])) {
        throw new Exception("Parameter '{$required_field}' missing.");
      }
    }
    // if a recurring contribution is associated with this change, check that it
    // is not yet in use by another contract
    if (!empty($this->data['contract_updates.ch_recurring_contribution'])) {
      $rcur = new CRM_Contract_RecurringContribution();
      if (!$rcur->isAssignableToContract($this->data['contract_updates.ch_recurring_contribution'], $this->getContractID())) {
        throw new Exception('Recurring contribution already in use for different contract');
      }
    }
  }

  public function verifyStatusChange() {
    $class = get_class($this);
    if (!method_exists($class, 'getStartStatusList')) {
      return;
    }
    $contract = $this->getContract();
    $status_name = CRM_Contract_Utils::getMembershipStatusName($contract['status_id']);
    if (!in_array($status_name, $class::getStartStatusList())) {
      throw new Exception("Cannot {$this->getActionName()} a membership when its status is '{$status_name}'.");
    }
  }

  /**
   * Get the change ID
   */
  public function getID() {
    return $this->data['id'];
  }

  /**
   * Get the internal action name
   */
  public function getActionName() {
    $class2action = array_flip(self::$action2class);
    return $class2action[get_class($this)];
  }

  /**
   * Get the contract ID
   */
  public function getContractID() {
    return $this->data['source_record_id'];
  }

  /**
   * Derive/populate additional data
   */
  public function populateData() {
    // populate parameters
    $contract = $this->getContract(TRUE);

    // propagate derived fields
    foreach (CRM_Contract_Change::$field_mapping_change_contract as $contract_attribute => $change_attribute) {
      if (empty($this->data[$change_attribute])) {
        $this->data[$change_attribute] = CRM_Utils_Array::value($contract_attribute, $contract, '');
      }
    }

    if (empty($this->data['subject'])) {
      // add default subject
      $this->setParameter('subject', $this->getSubject($contract, NULL));
    }
  }

  /**
   * Get the contract data
   *
   * @param boolean $with_payment_data
   * @return array contract data
   */
  public function getContract($with_payment_data = FALSE) {
    $contract_id = $this->getContractID();
    if ($this->contract === NULL || $this->contract['id'] != $contract_id) {
      // (re)load contract
      try {
        $this->contract = civicrm_api3('Membership', 'getsingle', ['id' => $contract_id]);
      } catch (Exception $ex) {
        throw new Exception("Contract [{$contract_id}] not found!");
      }
      CRM_Contract_CustomData::labelCustomFields($this->contract);
    }

    // add the payment data, if requested
    if ($with_payment_data) {
      if (empty($this->contract['membership_payment.membership_frequency'])) {
        $this->derivePaymentData($this->contract);
      }
    }

    return $this->contract;
  }

  /**
   * Enrich the given contact with payment data
   *
   * @param $contract array contract data
   */
  public function derivePaymentData(&$contract) {
    if (!empty($contract['membership_payment.membership_recurring_contribution'])) {
      // we have a recurring contribution!
      try {
        $contributionRecur = civicrm_api3('ContributionRecur', 'getsingle', ['id' => $contract['membership_payment.membership_recurring_contribution']]);
        $contract['membership_payment.membership_annual']    = $this->calcAnnualAmount($contributionRecur);
        $contract['membership_payment.membership_frequency'] = $this->calcPaymentFrequency($contributionRecur);
        $contract['membership_payment.cycle_day']            = $contributionRecur['cycle_day'];
        $contract['membership_payment.payment_instrument']   = $contributionRecur['payment_instrument_id'];

        // if this is a sepa payment, get the 'to' and 'from' bank account
        $sepaMandateResult = civicrm_api3('SepaMandate', 'get', array(
            'entity_table' => "civicrm_contribution_recur",
            'entity_id'    => $contributionRecur['id']
        ));
        if($sepaMandateResult['count'] == 1) {
          $sepaMandate = $sepaMandateResult['values'][$sepaMandateResult['id']];
          $contract['membership_payment.from_ba'] = CRM_Contract_BankingLogic::getOrCreateBankAccount($sepaMandate['contact_id'], $sepaMandate['iban'], $sepaMandate['bic']);
          $contract['membership_payment.to_ba']   = CRM_Contract_BankingLogic::getCreditorBankAccount();

        } elseif ($sepaMandateResult['count'] == 0) {
          // this should be a recurring contribution -> get from the latest contribution
          list($from_ba, $to_ba) = CRM_Contract_BankingLogic::getAccountsFromRecurringContribution($contributionRecur['id']);
          $contract['membership_payment.from_ba'] = $from_ba;
          $contract['membership_payment.to_ba']   = $to_ba;

        } else {
          // this is an error:
          $contract['membership_payment.from_ba'] = '';
          $contract['membership_payment.to_ba']   = '';

        }
      } catch(Exception $ex) {
        CRM_Core_Error::debug_log_message("Couldn't load recurring contribution [{$contract['membership_payment.membership_recurring_contribution']}]");
      }
    }
  }

  /**
   * Get the (numeric) activity type ID
   *
   * @return int activity type ID
   */
  public function getActivityTypeID() {
    if (is_numeric($this->data['activity_type_id'])) {
      return $this->data['activity_type_id'];
    }

    // otherwise translate class to ID
    $id2class = self::getActivityTypeId2Class();
    $class2id = array_flip($id2class);
    if (!isset($class2id[get_class($this)])) {
      throw Exception("Missing contract change activity type: " . get_class($this));
    }
    $activity_type_id = $class2id[get_class($this)];
    return $activity_type_id;
  }

  /**
   * Update the contract with the given data
   *
   * @param $updates array changes: attribute->value
   * @throws Exception
   */
  public function updateContract($updates) {
    // make sure the ID is there
    $updates['id'] = $this->getContractID();

    // derive fields if possible
    $this->derivePaymentData($updates);

    // make sure all fields are resolved
    CRM_Contract_CustomData::resolveCustomFields($updates);

    // finally: write through
    civicrm_api3('Membership', 'create', $updates);

    // and delete the cached contract data (if any)
    $this->contract = NULL;
  }

  /**
   * Calculate the subject line for this activity
   *
   * @param $contract_before array contract before update
   * @param $contract_after  array contract after update
   *
   * @return string subject line
   */
  public function getSubject($contract_after, $contract_before = NULL) {
    $subject_renderer = CRM_Contract_Configuration::getSubjectRender();
    if (!$subject_renderer) {
      $subject_renderer = $this; // use default renderer
    }
    return $subject_renderer->renderChangeSubject($this, $contract_after, $contract_before);
  }

  /**
   * Calculate the activities subject
   *
   * @param $change               CRM_Contract_Change the change object
   * @param $contract_before      array  data of the contract before
   * @param null $contract_after  array  data of the contract after
   * @return                      string the subject line
   */
  public function renderChangeSubject($change, $contract_after, $contract_before = NULL) {
    return $change->renderDefaultSubject($contract_after, $contract_before);
  }


  /**
   * Calculate annual amount
   *
   * @param $contributionRecur array recurring contribution data
   * @return string properly formatted annual amount
   */
  protected function calcAnnualAmount($contributionRecur){
    // only 'month' and 'year' should be in use
    $frequencyUnitTranslate = ['month' => 12, 'year'  => 1];
    return CRM_Contract_Utils::formatMoney(CRM_Contract_Utils::formatMoney($contributionRecur['amount']) * $frequencyUnitTranslate[$contributionRecur['frequency_unit']] / $contributionRecur['frequency_interval']);
  }


  /**
   * Calculate the frequency from the unit/interval set in the recurring contribution data
   * @param $contributionRecur array recurring contribution data
   * @return int payment frequency (in months)
   * @throws Exception if the unit is not recognised ('month' or 'year')
   */
  protected function calcPaymentFrequency($contributionRecur) {
    if (empty($contributionRecur['frequency_interval'])) {
      // unable to calculate
      return 0;
    }

    if ($contributionRecur['frequency_unit'] == 'year') {
      return 1 / $contributionRecur['frequency_interval'];
    } else if ($contributionRecur['frequency_unit'] == 'month') {
      return 12 / $contributionRecur['frequency_interval'];
    } else {
      throw new Exception("Frequency unit '{$contributionRecur['frequency_unit']}' not allowed.");
    }
  }

  /**
   * Set a parameter with the activity
   *
   * @param $key   string property name
   * @param $value string value to set
   */
  public function setParameter($key, $value) {
    $this->data[$key] = $value;
    // TODO: mark as dirty?
  }

  /**
   * Get a parameter from the activity
   *
   * @param $key     string property name
   * @param $default mixed  default to return if not set
   * @return mixed value in the activity data
   */
  public function getParameter($key, $default = NULL) {
    return CRM_Utils_Array::value($key, $this->data, $default);
  }

  /**
   * Save data to the DB (activity)
   */
  public function save() {
    // make sure all custom fields are transformed into the 'custom_[id]' notation
    CRM_Contract_CustomData::resolveCustomFields($this->data);

    // store via API
    $result = civicrm_api3('Activity', 'create', $this->data);

    // make sure we store the activity ID (if this is the first time)
    if (empty($this->data['id'])) {
      $this->data['id'] = $result['id'];
    }
  }

  /**
   * Check if this change is new, i.e. has not yet been saved to the DB
   */
  public function isNew() {
    return empty($this->data['id']);
  }

  /**
   * Set change status
   *
   * @param $status string valid activity status
   */
  public function setStatus($status) {
    $this->data['status_id'] = $status;
  }

  public function checkForConflicts() {
    // TODO: refactor CRM_Contract_Handler_ModificationConflicts
    $conflictHandler = new CRM_Contract_Handler_ModificationConflicts();
    $conflictHandler->checkForConflicts($this->getContractID());
  }

  /**
   * Cached query for API lookups
   *
   * @param $entity    string entity
   * @param $query     array query options
   * @param $attribute string attribute having the desired value
   * @return mixed value
   */
  protected function lookupValue($entity, $attribute, $query) {
    static $lookup_cache = [];

    // create a key
    $query['return'] = $attribute;
    $cache_key = "$entity" . serialize($query);
    if (!isset($lookup_cache[$cache_key])) {
      try {
        $value = civicrm_api3($entity, 'getvalue', $query);
      } catch (Exception $ex) {
        CRM_Core_Error::debug_log_message("Error looking up value {$entity} attribute '{$attribute}' with " . json_encode($query));
        $value = 'ERROR';
      }
      $lookup_cache[$cache_key] = $value;
    }
    return $lookup_cache[$cache_key];
  }

  /**
   * Provide a universal function to label a internal ID with the corresponding label where applicable
   *
   * @param $value       string current value
   * @param $field_name  string field this value is from
   * @return string      string labelled value
   */
  protected function labelValue($value, $field_name) {
    switch ($field_name) {
      case 'membership_type_id':
      case 'contract_updates.ch_membership_type':
        if (is_numeric($value)) {
          return $this->lookupValue('MembershipType', 'name', ['id' => $value]);
        } else {
          return $value;
        }

      case 'membership_payment.membership_frequency':
      case 'contract_updates.ch_frequency':
        if (is_numeric($value)) {
          return $this->lookupValue('OptionValue', 'label', ['value' => $value, 'option_group_id' => 'payment_frequency']);
        } else {
          return $value;
        }

      case 'membership_payment.from_ba':
      case 'contract_updates.ch_from_ba':
      case 'membership_payment.to_ba':
      case 'contract_updates.ch_to_ba':
        if (is_numeric($value)) {
          return CRM_Contract_BankingLogic::getIBANforBankAccount($value);
        } else {
          return $value;
        }

      case 'membership_payment.payment_instrument':
      case 'contract_updates.ch_payment_instrument':
        if (is_numeric($value)) {
          return $this->lookupValue('OptionValue', 'label', ['value' => $value, 'option_group_id' => 'payment_instrument']);
        } else {
          return $value;
        }

      case 'membership_cancellation.membership_cancel_reason':
      case 'contract_cancellation.contact_history_cancel_reason':
        if (is_numeric($value)) {
          return $this->lookupValue('OptionValue', 'label', ['value' => $value, 'option_group_id' => 'contract_cancel_reason']);
        } else {
          return $value;
        }

      default:
        return $value;
    }
  }

  /**
   * Provide a universal function to resolve the identity of a label
   *
   * @param $value       string current label
   * @param $field_name  string field this value is from
   * @return string      string labelled value
   */
  protected function resolveValue($value, $field_name) {
    switch ($field_name) {
      case 'membership_type_id':
      case 'contract_updates.ch_membership_type':
        if (is_numeric($value)) {
          return $value;
        } else {
          return $this->lookupValue('MembershipType', 'id', ['name' => $value]);
        }

      case 'membership_payment.membership_frequency':
      case 'contract_updates.ch_frequency':
        if (is_numeric($value)) {
          return $value;
        } else {
          return $this->lookupValue('OptionValue', 'value', ['label' => $value, 'option_group_id' => 'payment_frequency']);
        }

      case 'membership_payment.from_ba':
      case 'contract_updates.ch_from_ba':
      case 'membership_payment.to_ba':
      case 'contract_updates.ch_to_ba':
        if (is_numeric($value)) {
          return $value;
        } else {
          return 'ERROR, cannot resolve bank account by IBAN';
        }

      case 'membership_payment.payment_instrument':
      case 'contract_updates.ch_payment_instrument':
        if (is_numeric($value)) {
          return $value;
        } else {
          return $this->lookupValue('OptionValue', 'value', ['label' => $value, 'option_group_id' => 'payment_instrument']);
        }

      case 'membership_cancellation.membership_cancel_reason':
      case 'contract_cancellation.contact_history_cancel_reason':
        if (is_numeric($value)) {
          return $value;
        } else {
          return $this->lookupValue('OptionValue', 'value', ['label' => $value, 'option_group_id' => 'contract_cancel_reason']);
        }

      default:
        return $value;
    }
  }

  ################################################################################
  ##                           STATIC FUNCTIONS                                 ##
  ################################################################################

  /**
   * @param $membership_data
   * @param $links
   */
  public static function modifyActionLinks($membership_data, &$links) {
    // first remove the default ones that shouldn't be used any more
    $obsolete_actions = [CRM_Core_Action::RENEW, CRM_Core_Action::FOLLOWUP, CRM_Core_Action::DELETE, CRM_Core_Action::UPDATE];
    foreach ($links as $key => $link) {
      if (in_array($link['bit'], $obsolete_actions)) {
        unset($links[$key]);
      }
    }

    // add the replacement actions
    $status_name = CRM_Contract_Utils::getMembershipStatusName($membership_data['status_id']);
    foreach (self::getActivityTypeId2Class() as $change_class) {
      if (method_exists($change_class, 'modifyMembershipActionLinks')) {
        $change_class::modifyMembershipActionLinks($links, $status_name, $membership_data);
      }
    }
  }

  /**
   * Get the class for the given activity type
   *
   * @param $activity_type int|string acitivity type ID or name
   * @return string class name
   */
  public static function getClassByActivityType($activity_type_id) {
    // check name -> class mapping first
    if (isset(self::$type2class[$activity_type_id])) {
      return self::$type2class[$activity_type_id];
    }

    // check action -> class mapping second
    if (isset(self::$action2class[$activity_type_id])) {
      return self::$action2class[$activity_type_id];
    }

    // then try ID -> class
    $type_id2class = self::getActivityTypeId2Class();
    if (isset($type_id2class[$activity_type_id])) {
      return $type_id2class[$activity_type_id];
    }

    // not found? not one of ours!
    return NULL;
  }

  /**
   * Get the class for the given activity type
   *
   * @param $action string action name, e.g. 'cancel'
   * @return string class name
   */
  public static function getClassByAction($action) {
    return CRM_Utils_Array::value(strtolower($action), self::$action2class);
  }

  /**
   * Get the list of activity type ID to class
   *
   * @return array activity_type_id => class name
   */
  public static function getActivityTypeId2Class() {
    if (self::$_type_id2class === NULL) {
      // populate on demand:
      self::$_type_id2class = [];
      $query = civicrm_api3('OptionValue', 'get', [
          'option_group_id' => 'activity_type',
          'name'            => ['IN' => array_keys(self::$type2class)],
          'return'          => 'value,name',
          'option.limit'    => 0,
          'sequential'      => 1]);
      foreach ($query['values'] as $entry) {
        if (isset(self::$type2class[$entry['name']])) {
          self::$_type_id2class[$entry['value']] = self::$type2class[$entry['name']];
        }
      }
    }
    return self::$_type_id2class;
  }

  /**
   * Get the list of valid activity type IDs representing changes
   */
  public static function getActivityTypeIds() {
    $id2class = self::getActivityTypeId2Class();
    return array_keys($id2class);
  }

  /**
   * Get the activity type ID for the given change class
   *
   * @param string $change_class the change classe
   * @return int associated activity type ID
   */
  public static function getActivityIdForClass($change_class) {
    $class2id = array_flip(self::getActivityTypeId2Class());
    return CRM_Utils_Array::value($change_class, $class2id);
  }


  /**
   * Get a list of all change (activity) types with label
   *
   * @return array [activity_type_id => activity label]
   */
  public static function getChangeTypes() {
    static $change_types = NULL;
    if ($change_types === NULL) {
      $change_types = [];
      $query = civicrm_api3('OptionValue', 'get', [
          'option_group_id' => 'activity_type',
          'option.limit'    => 0,
          'value'           => ['IN' => self::getActivityTypeIds()],
          'return'          => 'value,label',
      ]);
      foreach ($query['values'] as $activity_type) {
        $change_types[$activity_type['value']] = $activity_type['label'];
      }
    }
    return $change_types;
  }

  /**
   * Get a change with data
   *
   * @param $data array data
   * @return CRM_Contract_Change change entity
   * @throws Exception if the change type couldn't be detected from the activity_type_id
   */
  public static function getChangeForData($data) {
    if (empty($data['activity_type_id'])) {
      throw new Exception("No activity_type_id given.");
    }

    $change_class = self::getClassByActivityType($data['activity_type_id']);
    if (empty($change_class)) {
      throw new Exception("Activity type ID '{$data['activity_type_id']}' is not a valid contract change type.");
    }

    // make sure we're using the descriptive indices, not the custom_[id] ones
    CRM_Contract_CustomData::labelCustomFields($data);

    // finally: create a change object on the data
    return new $change_class($data);
  }

  /**
   * Get a comma separated list of all change activity custom fields
   *
   * @return string list of field names
   */
  public static function getCustomFieldList() {
    $field_names = [];
    $fields = CRM_Contract_CustomData::getCustomFieldsForGroups(['contract_cancellation','contract_updates']);
    foreach ($fields as $field) {
      $field_names[] = "custom_{$field['id']}";
    }
    return implode(',', $field_names);
  }

  /**
   * Convert the given contract data and convert it to change activity data
   *
   * @param $data array       the data
   * @param $reverse boolean  reverse the transition
   */
  public static function convertContract2ChangeData($data, $reverse = FALSE) {
    $mapping = self::$field_mapping_change_contract;
    if ($reverse) {
      $mapping = array_flip($mapping);
    }

    foreach ($mapping as $old_attribute => $new_attribute) {
      if (isset($data[$old_attribute])) {
        $data[$new_attribute] = $data[$old_attribute];
        unset($data[$old_attribute]);
      }
    }
    return $data;
  }
}
