<?php

use CRM_Contract_ExtensionUtil as E;
use Civi\Test\HeadlessInterface;
use Civi\Test\HookInterface;
use Civi\Test\TransactionalInterface;

include_once 'ContractTestBase.php';

/**
 * Basic Contract Engine Tests
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
class CRM_Contract_BasicEngineTest extends CRM_Contract_ContractTestBase {

  /**
   * Example: Test that a version is returned.
   */
  public function testSimpleUpgrade() {
    // create a new contract
    $contract = $this->createNewContract();

    // schedule and update for tomorrow
    $this->modifyContract($contract['id'], 'cancel', 'tomorrow', [
        'membership_payment.membership_annual'             => '240.00',
        'membership_cancellation.membership_cancel_reason' => $this->getRandomOptionValue('contract_cancel_reason')]);

    // run engine see if anything changed
    $this->runContractEngine($contract['id']);

    // things should not have changed
    $contract_changed1 = $this->getContract($contract['id']);
    $this->assertEquals($contract, $contract_changed1, "This shouldn't have changed");

    // run engine again for tomorrow
    $this->runContractEngine($contract['id'], '+2 days');
    $contract_changed2 = $this->getContract($contract['id']);
    $this->assertNotEquals($contract, $contract_changed2, "This should have changed");
  }
}
