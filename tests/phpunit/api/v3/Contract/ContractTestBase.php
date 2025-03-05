<?php

use Civi\Api4;
use Civi\Test;
use PHPUnit\Framework\TestCase;

/**
 * @group headless
 */
class api_v3_Contract_ContractTestBase
extends TestCase
implements Test\HeadlessInterface, Test\HookInterface, Test\TransactionalInterface {
  use Test\Api3TestTrait;

  protected $adyenProcessor;
  protected $campaign;
  protected $contact;
  protected $pspCreditor;
  protected $sepaCreditor;

  private $defaultErrorHandler;

  public function setUpHeadless() {
    return Test::headless()
      ->installMe(__DIR__)
      ->install('org.project60.sepa')
      ->install('org.project60.banking')
      ->install('mjwshared')
      ->install('adyen')
      ->apply();
  }

  public function setUp(): void {
    parent::setUp();

    $this->defaultErrorHandler = set_error_handler(function ($errno, $errstr) {
      return TRUE;
    }, E_USER_DEPRECATED);

    $this->createMembershipTypes();
    $this->createTestCampaign();
    $this->createTestContact();
    $this->setAdyenProcessor();
    $this->setDefaultPspCreditor();
    $this->setDefaultSepaCreditor();
    $this->defineCancelReasonsAndTags();
  }

  public function tearDown(): void {
    set_error_handler($this->defaultErrorHandler, E_USER_DEPRECATED);

    parent::tearDown();
  }

  protected function assertEachEquals(array $pairs) {
    foreach ($pairs as $pair) {
      list($expected, $actual) = $pair;
      $this->assertEquals($expected, $actual);
    }
  }

  protected function createContribution(array $params) {
    $contribution = Api4\Contribution::create(FALSE)
      ->addValue('contact_id'            , $this->contact['id'])
      ->addValue('contribution_recur_id' , $params['recurring_contribution_id'])
      ->addValue('financial_type_id.name', 'Member Dues')
      ->addValue('receive_date'          , $params['date'])
      ->addValue('total_amount'          , $params['amount'])
      ->execute()
      ->first();

    civicrm_api3('MembershipPayment', 'create', [
      'contribution_id' => $contribution['id'],
      'membership_id'   => $params['membership_id'],
    ]);

    return $contribution;
  }

  protected static function getActiveRecurringContribution(int $membership_id) {
    $membership = Api4\Membership::get(FALSE)
      ->addWhere('id', '=', $membership_id)
      ->addSelect('membership_payment.membership_recurring_contribution')
      ->setLimit(1)
      ->execute()
      ->first();

    return Api4\ContributionRecur::get(FALSE)
      ->addWhere('id', '=', $membership['membership_payment.membership_recurring_contribution'])
      ->addSelect(
        '*',
        'contribution_status_id:name',
        'payment_instrument_id:name'
      )
      ->setLimit(1)
      ->execute()
      ->first();
  }

  protected static function getMembershipByID(int $membership_id) {
    return Api4\Membership::get(FALSE)
      ->addWhere('id', '=', $membership_id)
      ->addSelect('*', 'status_id:name')
      ->setLimit(1)
      ->execute()
      ->first();
  }

  public static function getFinancialTypeID(string $name) {
    return (int) Api4\FinancialType::get(FALSE)
      ->addWhere('name', '=', $name)
      ->addSelect('id')
      ->setLimit(1)
      ->execute()
      ->first()['id'];
  }

  public static function getMembershipTypeID(string $name) {
    return (int) Api4\MembershipType::get(FALSE)
      ->addWhere('name', '=', $name)
      ->addSelect('id')
      ->setLimit(1)
      ->execute()
      ->first()['id'];
  }

  public static function getOptionValue(string $option_group, string $name) {
    return (int) Api4\OptionValue::get(FALSE)
      ->addWhere('name'                , '=', $name)
      ->addWhere('option_group_id:name', '=', $option_group)
      ->addSelect('value')
      ->setLimit(1)
      ->execute()
      ->first()['value'];
  }

  private function createMembershipTypes() {
    Api4\MembershipType::create(FALSE)
      ->addValue('duration_interval'     , 2)
      ->addValue('duration_unit'         , 'year')
      ->addValue('financial_type_id.name', 'Member Dues')
      ->addValue('member_of_contact_id'  , 1)
      ->addValue('name'                  , 'General')
      ->addValue('period_type'           , 'rolling')
      ->execute();
  }

  private function createTestCampaign() {
    $settings_result = Api4\Setting::get(FALSE)
      ->addSelect('enable_components')
      ->execute()
      ->first();

    Api4\Setting::set(FALSE)
      ->addValue('enable_components', array_merge($settings_result['value'], ['CiviCampaign']))
      ->execute();

    $create_campaign_result = Api4\Campaign::create(FALSE)
      ->addValue('title', 'DD')
      ->execute();

    $this->campaign = $create_campaign_result->first();
  }

  private function createTestContact() {
    $this->contact = Api4\Contact::create()
      ->addValue('contact_type', 'Individual')
      ->addValue('first_name'  , 'Test')
      ->addValue('last_name'   , 'Contact')
      ->execute()
      ->first();

    $this->contact['email'] = 'test-contact@example.org';
  }

  private function defineCancelReasonsAndTags() {
    Api4\OptionValue::create(FALSE)
      ->addValue('label', 'Adyen: Refused')
      ->addValue('name', 'adyen_refused')
      ->addValue('option_group_id.name', 'contract_cancel_reason')
      ->execute();

    Api4\OptionValue::create(FALSE)
      ->addValue('label', 'Cancellation via bank')
      ->addValue('name', 'cancellation_via_bank')
      ->addValue('option_group_id.name', 'contract_cancel_reason')
      ->execute();

    Api4\OptionValue::create(FALSE)
      ->addValue('label', 'RDNCC: Card expired')
      ->addValue('name', 'rdncc_card_expired')
      ->addValue('option_group_id.name', 'contract_cancel_reason')
      ->execute();

    Api4\OptionValue::create(FALSE)
      ->addValue('label', 'RDN: Insufficient Funds')
      ->addValue('name', 'rdn_insufficient_funds')
      ->addValue('option_group_id.name', 'contract_cancel_reason')
      ->execute();

    for ($i = 1; $i < 4; $i++) {
      Api4\Tag::create(FALSE)
        ->addValue('name', "cancel_tag_$i")
        ->addValue('label', "Cancel Tag $i")
        ->addValue('parent_id:name', 'contract_cancellation')
        ->execute();
    }
  }

  private function setAdyenProcessor() {
    $adyen_processor_type = Api4\PaymentProcessorType::get()
      ->addWhere('name', '=', 'Adyen')
      ->addSelect('id')
      ->setLimit(1)
      ->execute()
      ->first();

    $this->adyenProcessor = Api4\PaymentProcessor::create(FALSE)
      ->addValue('financial_account_id.name', 'Payment Processor Account')
      ->addValue('name'                     , 'adyen')
      ->addValue('payment_processor_type_id', $adyen_processor_type['id'])
      ->addValue('title'                    , 'Adyen')
      ->execute()
      ->first();
  }

  private function setDefaultPspCreditor() {
    $this->pspCreditor = Api4\SepaCreditor::create()
      ->addValue('creditor_type' , 'PSP')
      ->addValue('currency'      , 'EUR')
      ->addValue('iban'          , 'AT613200000000005678')
      ->addValue('mandate_active', TRUE)
      ->addValue('mandate_prefix', 'PSP')
      ->addValue('pi_ooff'       , '1')
      ->addValue('pi_rcur'       , '1,2,3,4')
      ->execute()
      ->first();

    CRM_Sepa_Logic_Settings::setSetting(
      implode(',', [5, 10, 15, 20, 25]),
      'cycledays',
      $this->pspCreditor['id']
    );
  }

  private function setDefaultSepaCreditor() {
    $creditor_id = CRM_Sepa_Logic_Settings::getSetting('batching_default_creditor');

    if (isset($creditor_id)) {
      $this->sepaCreditor = Api4\SepaCreditor::get()
        ->addWhere('id', '=', $creditor_id)
        ->addSelect('*')
        ->execute()
        ->first();
    } else {
      $this->sepaCreditor = Api4\SepaCreditor::create()
        ->addValue('creditor_type' , 'SEPA')
        ->addValue('currency'      , 'EUR')
        ->addValue('iban'          , 'AT483200000012345864')
        ->addValue('mandate_active', TRUE)
        ->addValue('mandate_prefix', 'SEPA')
        ->execute()
        ->first();
    }

    CRM_Sepa_Logic_Settings::setSetting($this->sepaCreditor['id'], 'batching_default_creditor');

    CRM_Sepa_Logic_Settings::setSetting(
      implode(',', [7, 14, 21, 28]),
      'cycledays',
      $this->sepaCreditor['id']
    );
  }
}

?>
