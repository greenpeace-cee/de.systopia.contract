<?php

use Civi\Test\Api3TestTrait;
use CRM_Contract_ExtensionUtil as E;
use Civi\Test\HeadlessInterface;
use Civi\Test\HookInterface;
use Civi\Test\TransactionalInterface;

/**
 * FIXME - Add test description.
 *
 * Tips:
 *  - With HookInterface, you may implement CiviCRM hooks directly in the test class.
 *    Simply create corresponding functions (e.g. "hook_civicrm_post(...)" or similar).
 *  - With TransactionalInterface, any data changes made by setUp() or test****() functions will
 *    rollback automatically -- as long as you don't manipulate schema or truncate tables.
 *    If this test needs to manipulate schema or truncate tables, then either:
 *       a. Do all that using setupHeadless() and Civi\Test.
 *       b. Disable TransactionalInterface, and handle all setup/teardown yourself.
 *
 * @group headless
 */
class CRM_Contract_ContractTestBase extends \PHPUnit_Framework_TestCase implements HeadlessInterface, HookInterface, TransactionalInterface
{
  use Api3TestTrait {
    callAPISuccess as public traitCallAPISuccess;
  }

  protected static $counter = 0;

  public function setUpHeadless()
  {
    // Civi\Test has many helpers, like install(), uninstall(), sql(), and sqlFile().
    // See: https://docs.civicrm.org/dev/en/latest/testing/phpunit/#civitest
    return \Civi\Test::headless()
        ->installMe(__DIR__)
        ->install('org.project60.sepa')
        ->install('org.project60.banking')
        ->apply();
  }

  public function setUp()
  {
    \PHPUnit_Framework_TestCase::setUp();

    // check if there is a default creditor
    $default_creditor_id = (int) CRM_Sepa_Logic_Settings::getSetting('batching_default_creditor');
    if (empty($default_creditor_id)) {
      // create if there isn't
      $creditor = $this->callAPISuccess('SepaCreditor', 'create', [
        'creditor_type'  => 'SEPA',
        'currency'       => 'EUR',
        'mandate_active' => 1,
        'iban'           => 'AT483200000012345864',
      ]);
      CRM_Sepa_Logic_Settings::setSetting($creditor['id'], 'batching_default_creditor');
    }

    // check again
    $default_creditor_id = (int) CRM_Sepa_Logic_Settings::getSetting('batching_default_creditor');
    $this->assertNotEmpty($default_creditor_id, "There is no default SEPA creditor set");
  }

  public function tearDown()
  {
    parent::tearDown();
  }

  /**
   * Run the contract engine, and make sure it works
   *
   * @param $contract_id
   */
  public function runContractEngine($contract_id, $now = 'now')
  {
    $this->assertNotEmpty($contract_id, "You can only run the contract engine on a specific contract ID.");
    $result = $this->callAPISuccess('Contract', 'process_scheduled_modifications', [
        'now' => $now,
        'id'  => $contract_id]);
    $this->assertTrue(empty($result['values']['failed']), "Contract Engine reports failure");
    return $result;
  }

  /**
   * Run the contract engine and expect a failure
   *
   * @param $contract_id
   * @param string $now
   * @param string $expectedError
   */
  public function callEngineFailure($contract_id, $now = 'now', $expectedError = NULL) {
    $result = $this->callAPISuccess('Contract', 'process_scheduled_modifications', [
      'now' => $now,
      'id'  => $contract_id
    ])['values'];
    $this->assertNotEmpty($result['failed'], "Contract Engine should report failure(s)");
    if (!is_null($expectedError)) {
      $errorDetails = implode("\n", $result['error_details']);
      $this->assertContains(
        $expectedError,
        $errorDetails,
        '$expectedError should be included in error_details'
      );
    }
    return $result;
  }


  /**
   * Create a new contact with a random email address. Good for simple
   *  tests via the 'CRM_Xcm_Matcher_EmailOnlyMatcher'
   *
   * @param array $contact_data
   */
  public function createContactWithRandomEmail($contact_data = [])
  {
    if (empty($contact_data['contact_type'])) {
      $contact_data['contact_type'] = 'Individual';
    }
    if (empty($contact_data['first_name'])) {
      $contact_data['first_name'] = 'Random';
    }
    if (empty($contact_data['last_name'])) {
      $contact_data['last_name'] = 'Bloke';
    }

    // add random email
    self::$counter++;
    $contact_data['email'] = sha1(microtime() . self::$counter) . '@nowhere.nil';

    $contact     = $this->callAPISuccess('Contact', 'create', $contact_data);
    $new_contact = $this->callAPISuccess('Contact', 'getsingle', ['id' => $contact['id']]);
    return $new_contact;
  }

  /**
   * Get a random payment instrument ID
   *
   * @return integer payment instrument ID
   */
  public function getRandomPaymentInstrumentID() {
    $pis = $this->callAPISuccess('OptionValue', 'get', [
        'is_active'       => 1,
        'option_group_id' => 'payment_instrument'
    ]);
    $this->assertNotEmpty($pis['count'], 'No PaymentInstruments configured');
    $instrument = $pis['values'][array_rand($pis['values'])];
    return $instrument['value'];
  }

  /**
   * Get a random membership type ID
   *
   * @return integer membership type ID
   */
  public function getRandomMembershipTypeID()
  {
    $types = $this->callAPISuccess('MembershipType', 'get', ['is_active' => 1]);
    if ($types['count'] > 0) {
      $type = $types['values'][array_rand($types['values'])];
      return $type['id'];
    } else {
      // create a new one
      $contact  = $this->createContactWithRandomEmail();
      $new_type = $this->callAPISuccess('MembershipType', 'create', [
          'member_of_contact_id' => $contact['id'],
          'financial_type_id'    => "1",
          'duration_unit'        => "year",
          'duration_interval'    => "1",
          'period_type'          => "rolling",
          'name'                 => "Test Fallback",
          'is_active'            => "1",
      ]);
      return $new_type['id'];
    }
  }

  /**
   * Simply load the contract by ID
   * @param $contract_id integer contract ID
   * @return array contract data
   */
  public function getContract($contract_id) {
    $contract = $this->callAPISuccess('Membership', 'getsingle', ['id' => $contract_id]);
    CRM_Contract_CustomData::labelCustomFields($contract);
    return $contract;
  }

  /**
   * Create a new payment contract and return the recurring contribution
   *
   * @param $params  array specs
   * @param $is_sepa bool  if true, a SEPA mandate will be generated
   */
  public function createPayment($params, $is_sepa) {
    // fill common parameters
    if (empty($params['contact_id'])) {
      $contact = $this->createContactWithRandomEmail();
      $params['contact_id'] = $contact['id'];
    }
    if (empty($params['frequency_interval'])) {
      $params['frequency_interval'] = 12;
    }
    if (empty($params['frequency_unit'])) {
      $params['frequency_unit'] = 'month';
    }
    if (empty($params['amount'])) {
      $params['amount'] = '120.00';
    }
    if (empty($params['financial_type_id'])) {
      $params['financial_type_id'] = '1';
    }
    if (empty($params['currency'])) {
      $params['currency'] = 'EUR';
    }

    if ($is_sepa) {
      // SEPA Mandate:
      if (empty($params['iban'])) {
        $params['iban'] = 'DE89370400440532013000';
      }
      if (empty($params['bic'])) {
        $params['bic'] = 'GENODEM1GLS';
      }
      if (empty($params['type'])) {
        $params['type'] = 'RCUR';
      }

      $mandate = $this->callAPISuccess('SepaMandate', 'createfull', $params);
      $mandate = $this->callAPISuccess('SepaMandate', 'getsingle', ['id' => $mandate['id']]);
      return $this->callAPISuccess('ContributionRecur', 'getsingle', ['id' => $mandate['entity_id']]);

    } else {
      // Standing Order (recurring contribution)
      if (empty($params['payment_instrument_id'])) {
        $params['payment_instrument_id'] = $this->getRandomPaymentInstrumentID();
      }
      if (empty($params['status_id'])) {
        $params['status_id'] = 'Current';
      }

      $payment = $this->callAPISuccess('ContributionRecur', 'create', $params);
      return $this->callAPISuccess('ContributionRecur', 'getsingle', ['id' => $payment['id']]);
    }
  }

  /**
   * Create a new contract
   *
   * @param $params
   */
  public function createNewContract($params = []) {
    $contact_id = (!empty($params['contact_id'])) ? $params['contact_id'] : $this->createContactWithRandomEmail()['id'];

    // first: make sure we have a contract payment
    if (empty($params['membership_payment.membership_recurring_contribution'])) {
      $payment = $this->createPayment($params, !empty($params['is_sepa']));
      $params['membership_payment.membership_recurring_contribution'] = $payment['id'];
    }


    //membership_payment.membership_recurring_contribution
    $contract = $this->callAPISuccess('Contract', 'create', [
        'contact_id'         => $contact_id,
        'membership_type_id' => (!empty($params['membership_type_id'])) ? $params['membership_type_id'] : $this->getRandomMembershipTypeID(),
        'join_date'          => (!empty($params['join_date'])) ? $params['join_date'] : date('YmdHis'),
        'start_date'         => (!empty($params['start_date'])) ? $params['start_date'] : date('YmdHis'),
        'end_date'           => (!empty($params['end_date'])) ? $params['end_date'] : NULL,
        'campaign_id'        => (!empty($params['campaign_id'])) ? $params['campaign_id'] : NULL,
        'note'               => (!empty($params['note'])) ? $params['note'] : 'Test',
        'medium_id'          => (!empty($params['medium_id'])) ? $params['medium_id'] : '1',
        // custom stuff:
        'membership_payment.membership_recurring_contribution' => $params['membership_payment.membership_recurring_contribution'],
        'membership_general.membership_dialoger'               => $contact_id,
        // membership_general.membership_contract   // Contract number
        // membership_general.membership_reference  // Reference number
        // membership_general.membership_contract   // Contract number
        // membership_general.membership_channel    // Membership Channel
    ]);
    $this->assertNotEmpty($contract['id'], "Contract couldn't be created");

    // load the contract
    return $this->getContract($contract['id']);
  }

  /**
   * Modify a contract
   *
   * @param $contract_id integer contract ID
   * @param $action      string  one of 'cancel', 'revive', 'update', ...
   * @param $date        string  date string
   * @param $params      array   update parameters, incl 'date'
   *
   * @return array API result
   */
  public function modifyContract($contract_id, $action, $date = 'now', $params = []) {
    $params['id'] = $contract_id;
    $params['modify_action'] = $action;
    $params['date'] = date('Y-m-d H:i:s', strtotime($date));
    if (empty($params['medium_id'])) {
      $params['medium_id'] = 1;
    }
    return $this->callAPISuccess('Contract', 'modify', $params);
  }

  /**
   * Get a random value from the given option group
   */
  public function getRandomOptionValue($option_group_id, $label = TRUE) {
    $all_option_values = $this->callAPISuccess('OptionValue', 'get', [
        'option.limit'    => 0,
        'return'          => 'value,label',
        'option_group_id' => $option_group_id]);
    $value = $all_option_values['values'][array_rand($all_option_values['values'])];
    if ($label) {
      return $value['label'];
    } else {
      return $value['value'];
    }
  }

  /**
   * Get bank account ID
   *
   * @param $contact_id  integer contact ID
   * @param string $iban string IBAN
   * @param string $bic  string BIC
   * @return int
   */
  public function getBankAccountID($contact_id, $iban = 'DE89370400440532013000', $bic = 'GENODEM1GLS') {
    try {
      $ba_id = CRM_Contract_BankingLogic::getOrCreateBankAccount($contact_id, $iban, $bic);
      $this->assertNotEmpty($ba_id, "Failed to create bank account");
      return $ba_id;
    } catch (Exception $ex) {
      $this->fail("Error while createing bank account: " . $ex->getMessage());
    }
  }

  /**
   * Get the max ID of an activity with the given criteria
   *
   * @param $params            array search criteria
   * @param $after_activity_id int only consider activities with ID greater that this
   * @return int ID if there is such an activity
   */
  public function getLastActivityID($params, $after_activity_id = NULL) {
    // add 'after ID' criteria
    $after_activity_id = (int) $after_activity_id;
    if ($after_activity_id) {
      $params['id'] = ['>', $after_activity_id];
    }

    // add standard parameters
    $params['option.sort'] = 'id desc';
    $params['option.limit'] = 1;
    $params['return'] = 'id';

    $search = $this->callAPISuccess('Activity', 'get', $params);
    return CRM_Utils_Array::value('id', $search);
  }

  /**
   * Get the most recent change activity for the given contract
   * @param $contract_id int    Contract ID
   * @param $types       array  list of types
   * @param $status      array  list of activity status
   * @return array|null activity data
   */
  public function getLastChangeActivity($contract_id, $types = NULL, $status = []) {
    $activities = $this->getChangeActivities($contract_id, $types, $status);

    // Get the activity with the highest id value
    return array_reduce($activities, function ($carry, $item) {
      return (int) $carry['id'] > (int) $item['id'] ? $carry : $item;
    });
  }

  /**
   * Get all change activities for the given contract ID
   *
   * @param $contract_id int    Contract ID
   * @param $types       array  list of types
   * @param $status      array  list of activity status
   * @return array list of activities
   */
  public function getChangeActivities($contract_id, $types = NULL, $status = []) {
    // compile query
    $query = [
        'source_record_id' => $contract_id,
        'sequential'       => 1,
        'option.limit'     => 0,
        'option.sort'      => 'activity_date_time desc',
    ];
    if (!empty($types)) {
      $query['activity_type_id'] = ['IN' => $types];
    }
    if (!empty($status)) {
      $query['status_id'] = ['IN' => $status];
    }

    // load activities
    $result = $this->callAPISuccess('Activity', 'get', $query);
    return $result['values'];
  }


  /**
   * Compare two arrays by asserting all attributes are equal
   *
   * @param $expected_data       array expected data
   * @param $current_data        array test data
   * @param $source              string indication where this came from, only used for a failed assertion message
   * @param $attribute_list      array list of attributes to check. default is ALL
   * @param $exception_list      array list of attributes to NOT check. default is NONE
   */
  public function assertArraysEqual($expected_data, $current_data, $attribute_list = NULL, $exception_list = [], $source = 'Unknown') {
    if ($attribute_list == NULL) {
      $attribute_list = array_keys(array_merge($expected_data, $current_data));
    }

    foreach ($attribute_list as $attribute) {
      if (in_array($attribute, $exception_list)) continue;
      $expected_value = CRM_Utils_Array::value($attribute, $expected_data);
      $current_value  = CRM_Utils_Array::value($attribute, $current_data);
      //$this->assertEquals($expected_value, $current_value, "Attribute '{$attribute}' differs. Expected: '{$expected_value}', got '{$current_value}'.");
      $this->assertEquals($expected_value, $current_value, "Attribute '{$attribute}' differs. ({$source})");
    }
  }

  /**
   * Change activity usually have the contract ID encoded, which makes comparison hard
   * This function strips this id header
   *
   * @param $subject subject to be edited in-place
   */
  public function stripActivitySubjectID(&$subject) {
    $subject = preg_replace('/^id[0-9]+[:.]/', 'CONTRACT_ID', $subject);
  }

  /**
   * Get the status ID for the given membership status
   *
   * @param $status_name string membership status name
   * @return int status ID
   */
  public function getMembershipStatusID($status_name) {
    static $membership_status2id = NULL;
    if ($membership_status2id === NULL) {
      $membership_status2id = [];
      $query = civicrm_api3('MembershipStatus', 'get', [
         'option.limit' => 0,
      ]);
      foreach ($query['values'] as $membership_status) {
        $membership_status2id[$membership_status['name']] = $membership_status['id'];
      }
    }

    return CRM_Utils_Array::value($status_name, $membership_status2id);
  }

  /**
   * Remove 'xdebug' result key set by Civi\API\Subscriber\XDebugSubscriber
   *
   * This breaks some tests when xdebug is present, and we don't need it.
   *
   * @param $entity
   * @param $action
   * @param $params
   * @param null $checkAgainst
   *
   * @return array|int
   */
  public function callAPISuccess($entity, $action, $params, $checkAgainst = NULL) {
    $result = $this->traitCallAPISuccess($entity, $action, $params, $checkAgainst);
    if (is_array($result)) {
      unset($result['xdebug']);
    }
    return $result;
  }

  /**
   * Define which flavour for change activities should be used
   * @param $type string flavour type
   */
  public function setActivityFlavour($type) {
    // TODO: this needs to be implemented when other flavours are available
  }

  /**
   * Get mandate currently associated with a contract
   *
   * @param $contract_id
   *
   * @return array
   * @throws \CiviCRM_API3_Exception
   */
  public function getMandateForContract($contract_id) {
    $contract = $this->getContract($contract_id);
    return civicrm_api3('SepaMandate', 'getsingle', [
      'entity_table' => 'civicrm_contribution_recur',
      'entity_id' => $contract['membership_payment.membership_recurring_contribution'],
    ]);
  }
}
