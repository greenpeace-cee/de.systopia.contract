<?php

use Civi\Api4;
use Civi\Test;
use PHPUnit\Framework\TestCase;

/**
 * @group headless
 */
class api_v3_Contract_DateTestBase
extends TestCase
implements Test\HeadlessInterface, Test\HookInterface, Test\TransactionalInterface {
  use Test\Api3TestTrait;

  protected $adyenProcessor;
  protected $contact;
  protected $pspCreditor;
  protected $sepaCreditor;

  public function setUpHeadless() {
    return Test::headless()
      ->installMe(__DIR__)
      ->install('org.project60.sepa')
      ->install('mjwshared')
      ->install('adyen')
      ->apply(TRUE);
  }

  public function setUp() {
    parent::setUp();

    $this->createTestContact();
    $this->setAdyenProcessor();
    $this->setDefaultPspCreditor();
    $this->setDefaultSepaCreditor();
  }

  public function tearDown() {
    parent::tearDown();
  }

  private function createTestContact() {
    $this->contact = Api4\Contact::create()
      ->addValue('contact_type', 'Individual')
      ->addValue('first_name'  , 'Test')
      ->addValue('last_name'   , 'Contact')
      ->execute()
      ->first();
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
        ->execute()
        ->first();
    }

    CRM_Sepa_Logic_Settings::setSetting($this->sepaCreditor['id'], 'batching_default_creditor');
  }
}

?>
