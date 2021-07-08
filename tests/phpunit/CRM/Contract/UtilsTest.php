<?php

use CRM_Contract_ExtensionUtil as E;
use Civi\Test\HeadlessInterface;
use Civi\Test\HookInterface;
use Civi\Test\TransactionalInterface;

/**
 * Test utility functions
 *
 * @group headless
 */
class CRM_Contract_UtilsTest extends CRM_Contract_ContractTestBase {

  public function testStripNonContractActivityCustomFields() {
    $fields = CRM_Contract_CustomData::getCustomFieldsForGroups(['contract_cancellation','contract_updates']);
    $activityData = [
      'id'                         => 1,
      'activity_date_time'         => '20200101000000',
      'custom_' . $fields[0]['id'] => 'foo',
      'custom_9997'                => 'bar',
      'custom_9998_1'              => 'baz',
    ];
    CRM_Contract_Utils::stripNonContractActivityCustomFields($activityData);
    $this->assertArraysEqual([
        'id'                         => 1,
        'activity_date_time'         => '20200101000000',
        'custom_' . $fields[0]['id'] => 'foo',
      ],
      $activityData
    );
  }

  public function testGetPaymentChangesForLegacyUpdate() {
    $activity = [
      'activity_type_id' => 123,
      'campaign_id' => 345,
      'contract_updates.ch_cycle_day' => 5,
      'contract_updates.ch_from_ba' => 1,
      'contract_updates.ch_annual' => 100.50,
      'contract_updates.ch_frequency' => 12,
      'contract_updates.defer_payment_start' => 0,
    ];
    $result = CRM_Contract_Utils::getPaymentChangesForLegacyUpdate($activity);
    $this->assertArraysEqual([
      'activity_type_id' => '123',
      'adapter' => CRM_Contract_PaymentAdapter_SEPAMandate::ADAPTER_ID,
      'parameters' => [
        'campaign_id'         => 345,
        'cycle_day'           => 5,
        'from_ba'             => 1,
        'annual'              => 100.50,
        'frequency'           => 12,
        'defer_payment_start' => 0
      ],
    ], $result);
  }

}
