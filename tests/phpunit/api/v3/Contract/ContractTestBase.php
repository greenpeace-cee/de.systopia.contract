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
      ->apply(TRUE);
  }

  public function setUp() {
    parent::setUp();

    $this->defaultErrorHandler = set_error_handler(function ($errno, $errstr) {
      return TRUE;
    }, E_USER_DEPRECATED);

    $this->createMembershipTypes();
    $this->createTestContact();
    $this->setAdyenProcessor();
    $this->setDefaultPspCreditor();
    $this->setDefaultSepaCreditor();
  }

  public function tearDown() {
    set_error_handler($this->defaultErrorHandler, E_USER_DEPRECATED);

    parent::tearDown();
  }

  public function assertEachEquals(array $pairs) {
    foreach ($pairs as $pair) {
      list($expected, $actual) = $pair;
      $this->assertEquals($expected, $actual);
    }
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

  private function createTestContact() {
    $this->contact = Api4\Contact::create()
      ->addValue('contact_type', 'Individual')
      ->addValue('first_name'  , 'Test')
      ->addValue('last_name'   , 'Contact')
      ->execute()
      ->first();

    $this->contact['email'] = 'test-contact@example.org';
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
  }
}

?>
