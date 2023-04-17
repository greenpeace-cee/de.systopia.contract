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

  protected $campaign;
  protected $contact;

  private $defaultErrorHandler;
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

    $this->defaultErrorHandler = set_error_handler(function ($errno, $errstr) {
      return TRUE;
    }, E_USER_DEPRECATED);

    $this->createCampaign();
    $this->createContact();
    $this->createRequiredOptionValues();
  }

  public function tearDown() {
    set_error_handler($this->defaultErrorHandler, E_USER_DEPRECATED);

    parent::tearDown();
  }

  private function createCampaign() {
    $settingsResult = Api4\Setting::get(FALSE)
      ->addSelect('enable_components')
      ->execute()
      ->first();

    Api4\Setting::set(FALSE)
      ->addValue('enable_components', array_merge($settingsResult['value'], ['CiviCampaign']))
      ->execute();

    $createCampaignResult = Api4\Campaign::create(FALSE)
      ->addValue('title', 'DD')
      ->execute();

    $this->campaign = $createCampaignResult->first();
  }

  private function createContact() {
    $createContactResult = Api4\Contact::create(FALSE)
      ->addValue('contact_type:name', 'Individual')
      ->addValue('first_name',        'Contact_1')
      ->addValue('last_name',         'Test')
      ->execute();

    $contact = $createContactResult->first();
    $contactEmail = $contact['first_name'] . '.' . $contact['last_name'] . '@example.org';
    $contact['email'] = $contactEmail;

    $this->contact = $contact;
  }

  private function createRequiredOptionValues() {
    // ...
  }

  protected function getOptionValue(string $optionGroup, string $name, bool $useCache = TRUE) {
    if (!isset($this->optionValueCache[$optionGroup])) {
      $this->optionValueCache[$optionGroup] = [];
    }

    if ($useCache && isset($this->optionValueCache[$optionGroup][$name])) {
      return $this->optionValueCache[$optionGroup][$name];
    }

    $optionValueQuery = Api4\OptionValue::get(FALSE)
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
    return Api4\FinancialType::get(FALSE)
      ->addSelect('id')
      ->addWhere('name', '=', $name)
      ->execute()
      ->first()['id'];
  }

}

?>
