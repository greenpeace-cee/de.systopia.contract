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

}
