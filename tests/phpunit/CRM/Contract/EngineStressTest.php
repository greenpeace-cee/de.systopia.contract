<?php

use CRM_Contract_ExtensionUtil as E;
use Civi\Test\HeadlessInterface;
use Civi\Test\HookInterface;
use Civi\Test\TransactionalInterface;

include_once 'ContractTestBase.php';

/**
 * Basic Contract Engine Tests
 *
 * @group headless
 */
class CRM_Contract_EngineStressTest extends CRM_Contract_ContractTestBase {

  public function setUp(): void {
    parent::setUp();
  }

  /**
   * Test execution of multiple updates and conflict handling
   */
  public function testMultiUpgrade() {
    $ITERATION_COUNT = 11;
    foreach ([1,0] as $is_sepa) {
      // create a new contract
      $contract = $this->createNewContract(['is_sepa' => $is_sepa]);
      $scheduled_updates = $this->callAPISuccess('Contract', 'get_open_modification_counts', ['id' => $contract['id']])['values'];
      $this->assertEquals(0, $scheduled_updates['scheduled'], "There should not be a scheduled change");

      // schedule a bunch of updates, one for each of the next $ITERATION_COUNT days
      for ($i = 1; $i <= $ITERATION_COUNT; $i++) {
        $update = [
            'membership_payment.membership_annual' => (1 * $i)
        ];
        if ($is_sepa) {
          $update['payment_method.reference'] = "SEPA-" . $contract['id'] . "-" . date("Ymd") . bin2hex(random_bytes(4));
        } else {
          // FIXME: if this is not a SEPA contract, we need to pass the bank account
          $update['membership_payment.from_ba'] = $this->getBankAccountID($contract['contact_id']);
        }
        $this->modifyContract($contract['id'], 'update', date('YmdHis', strtotime("now + {$i} days")), $update);
      }

      // now... these should all be conflicting
      $scheduled_updates = $this->callAPISuccess('Contract', 'get_open_modification_counts', ['id' => $contract['id']])['values'];
      $this->assertEquals($ITERATION_COUNT, $scheduled_updates['needs_review'], "There should be {$ITERATION_COUNT} updates that need review.");

      // cheekily set all of the 'needs review' ones to 'scheduled'
      CRM_Core_DAO::executeQuery("UPDATE civicrm_activity SET status_id = 1 WHERE source_record_id = {$contract['id']} AND status_id <> 2;");

      // now... these should all be scheduled
      $scheduled_updates = $this->callAPISuccess('Contract', 'get_open_modification_counts', ['id' => $contract['id']])['values'];
      $this->assertEquals($ITERATION_COUNT, $scheduled_updates['scheduled'], "There should be {$ITERATION_COUNT} update scheduled.");

      // run engine for 5 days from now (should execute 5 updates)
      $result = $this->runContractEngine($contract['id'], '+5 days');
      $contract_changed2 = $this->getContract($contract['id']);
      $this->assertNotEquals($contract, $contract_changed2, "This should have changed");

      // should have executed 5 updates and set the remaining to "needs review"
      $scheduled_updates = $this->callAPISuccess('Contract', 'get_open_modification_counts', ['id' => $contract['id']])['values'];
      $expectedReviews = $ITERATION_COUNT - 5;
      $this->assertEquals($expectedReviews, $scheduled_updates['needs_review'], "There should be {$expectedReviews} update that need review.");
      $this->assertEquals(0, $scheduled_updates['scheduled'], "There should be no scheduled updates.");
    }
  }
}
