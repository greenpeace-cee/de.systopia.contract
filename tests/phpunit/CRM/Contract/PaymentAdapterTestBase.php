<?php

use Civi\Api4;
use Civi\Test\HeadlessInterface;
use Civi\Test\HookInterface;
use Civi\Test\TransactionalInterface;
use PHPUnit\Framework\TestCase;

/**
 * @group headless
 */
class CRM_Contract_PaymentAdapterTestBase
  extends TestCase
  implements HeadlessInterface, HookInterface, TransactionalInterface {

  protected $contact;

  private $optionValueCache = [];

  public function setUpHeadless() {
    return \Civi\Test::headless()
      ->installMe(__DIR__)
      ->install('org.project60.sepa')
      ->install('org.project60.banking')
      ->install('mjwshared')
      ->install('adyen')
      ->apply(TRUE);
  }

  public function setUp() {
    parent::setUp();

    $this->createContact();
  }

  public function tearDown() {
    parent::tearDown();
  }

  private function createContact() {
    $createContactResult = Api4\Contact::create()
      ->addValue('contact_type:name', 'Individual')
      ->addValue('first_name',        'Contact_1')
      ->addValue('last_name',         'Test')
      ->execute();

    $contact = $createContactResult->first();
    $contactEmail = $contact['first_name'] . '.' . $contact['last_name'] . '@example.org';
    $contact['email'] = $contactEmail;

    $this->contact = $contact;
  }

  protected function getOptionValue(string $optionGroup, string $name, bool $useCache = TRUE) {
    if (!isset($this->optionValueCache[$optionGroup])) {
      $this->optionValueCache[$optionGroup] = [];
    }

    if ($useCache && isset($this->optionValueCache[$optionGroup][$name])) {
      return $this->optionValueCache[$optionGroup][$name];
    }

    $optionValueQuery = Api4\OptionValue::get()
      ->addSelect('value')
      ->addWhere('option_group_id:name', '=', $optionGroup)
      ->addWhere('name',                 '=', $name)
      ->setLimit(1)
      ->execute();

    $optionValue = $optionValueQuery->first()['value'];
    $this->optionValueCache[$optionGroup][$name] = $optionValue;

    return $optionValue;
  }

  protected static function getFinancialTypeID(string $name) {
    return Api4\FinancialType::get()
      ->addSelect('id')
      ->addWhere('name', '=', $name)
      ->execute()
      ->first()['id'];
  }

}

?>
