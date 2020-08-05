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

  public function setUp() {
    parent::setUp();
  }

  /**
   * Test a simple create
   */
  public function testSimpleCreate() {
    foreach ([0,1] as $is_sepa) {
      // create a new contract
      $contract = $this->createNewContract([
          'is_sepa'            => $is_sepa,
          'amount'             => '10.00',
          'frequency_unit'     => 'month',
          'frequency_interval' => '1',
      ]);

      // annual amount
      $this->assertEquals('120.00', $contract['membership_payment.membership_annual']);
      $this->assertEquals('2', $contract['status_id']);
      $this->assertNotEmpty($contract['membership_payment.membership_recurring_contribution']);
      $this->assertNotEmpty($contract['membership_payment.cycle_day']);
    }
  }

  /**
   * Test a simple cancellation
   */
  public function testSimpleCancel() {
    foreach ([1] as $is_sepa) {
      // create a new contract
      $contract = $this->createNewContract(['is_sepa' => $is_sepa]);

      // schedule and update for tomorrow
      $this->modifyContract($contract['id'], 'cancel', 'tomorrow', [
          'membership_cancellation.membership_cancel_reason' => 'Unknown'
      ]);

      // run engine see if anything changed
      $this->runContractEngine($contract['id']);

      // things should not have changed
      $contract_changed1 = $this->getContract($contract['id']);
      $this->assertEquals($contract, $contract_changed1, "This shouldn't have changed");

      // run engine again for tomorrow
      $this->runContractEngine($contract['id'], '+2 days');
      $contract_changed2 = $this->getContract($contract['id']);
      $this->assertNotEquals($contract, $contract_changed2, "This should have changed");

      // make sure status is cancelled
      $this->assertEquals($this->getMembershipStatusID('Cancelled'), $contract_changed2['status_id'], "The contract wasn't cancelled");
    }
  }

  /**
   * Test a simple upgrade
   */
  public function testSimpleUpgrade() {
    foreach ([1] as $is_sepa) {
      // create a new contract
      $contract = $this->createNewContract(['is_sepa' => $is_sepa]);

      // schedule and update for tomorrow
      $this->modifyContract($contract['id'], 'update', 'tomorrow', [
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

      // make sure status is current
      $this->assertEquals($this->getMembershipStatusID('Current'), $contract_changed2['status_id'], "The contract isn't active");
      $this->assertEquals(240.00, $contract_changed2['membership_payment.membership_annual'], "The contract has the wrong amount");
    }
  }

  /**
   * Test a simple pause/resume
   */
  public function testSimplePauseResume() {
    foreach ([1] as $is_sepa) {
      // create a new contract
      $contract = $this->createNewContract(['is_sepa' => $is_sepa]);

      // schedule and update for tomorrow
      $this->modifyContract($contract['id'], 'pause', 'tomorrow');
      $changes = $this->callAPISuccess('Activity', 'get', ['source_record_id' => $contract['id']]);


      // run engine see if anything changed
      $this->runContractEngine($contract['id']);

      // things should not have changed
      $contract_changed1 = $this->getContract($contract['id']);
      $this->assertEquals($contract, $contract_changed1, "This shouldn't have changed");

      // run engine again for tomorrow
      $this->runContractEngine($contract['id'], '+1 day');
      $contract_changed2 = $this->getContract($contract['id']);
      $this->assertEquals($this->getMembershipStatusID('Paused'), $contract_changed2['status_id'], "The contract isn't paused");
    }
  }

  /**
   * Test a simple revive
   */
  public function testSimpleRevive() {
    foreach ([1] as $is_sepa) {
      // create a new contract
      $contract = $this->createNewContract(['is_sepa' => $is_sepa]);

      // schedule and update for tomorrow
      $this->modifyContract($contract['id'], 'cancel', 'tomorrow', [
          'membership_cancellation.membership_cancel_reason' => 'Unknown'
      ]);

      // run engine again for tomorrow
      $this->runContractEngine($contract['id'], '+1 days');
      $contract_cancelled = $this->getContract($contract['id']);
      $this->assertNotEquals($contract, $contract_cancelled, "This should have changed");

      // make sure status is cancelled
      $this->assertEquals($this->getMembershipStatusID('Cancelled'), $contract_cancelled['status_id'], "The contract wasn't cancelled");

      // now: revive contract
      $this->modifyContract($contract['id'], 'revive', '+2 days', [
          'membership_payment.membership_annual'             => '240.00',
          'membership_cancellation.membership_cancel_reason' => $this->getRandomOptionValue('contract_cancel_reason')]);

      $this->runContractEngine($contract['id'], '+2 days');
      $contract_revived = $this->getContract($contract['id']);
      $this->assertNotEquals($contract_cancelled, $contract_revived, "This should have changed");

      // make sure status is cancelled
      $this->assertEquals($this->getMembershipStatusID('Current'), $contract_revived['status_id'], "The contract wasn't revived");
      $this->assertEquals(240.00, $contract_revived['membership_payment.membership_annual'], "The contract has the wrong amount");
    }
  }

  /**
   * Test that an update with invalid parameters causes failure
   */
  public function testUpdateFailure() {
    // create a new contract
    $contract = $this->createNewContract();

    // schedule and update with an invalid from_ba
    $this->modifyContract($contract['id'], 'update', 'tomorrow', [
      'membership_payment.from_ba'           => 12345,
      'membership_payment.membership_annual' => '123.00',
    ]);
    // run engine for tomorrow
    $result = $this->callEngineFailure(
      $contract['id'],
      '+1 days',
      "Expected one BankingAccount"
    );
    $activityId = $result['failed'][0];
    // contract update activity should be status failed and details should contain error
    $this->callAPISuccess('Activity', 'getsingle', [
      'id'        => $activityId,
      'status_id' => 'Failed',
      'details'   => ['LIKE' => '%Expected one BankingAccount%'],
    ]);
    $contract_changed = $this->getContract($contract['id']);
    $this->assertEquals(
      $contract,
      $contract_changed,
      'Contract shouldn\'t have changed after failure'
    );
  }

  /**
   * Test that cancellations of already-cancelled memberships aren't allowed
   */
  public function testAllowedStatusChangeCancellation() {
    // create a new contract
    $contract = $this->createNewContract();

    // schedule cancel
    $this->modifyContract($contract['id'], 'cancel', '+1 days', [
      'membership_cancellation.membership_cancel_reason' => 'Unknown'
    ]);
    // also schedule an update a week from now
    $this->modifyContract($contract['id'], 'update', '+7 days', [
      'membership_payment.membership_annual' => '123.00',
    ]);
    // resolve "Needs Review"
    CRM_Core_DAO::executeQuery("UPDATE civicrm_activity SET status_id = 1 WHERE source_record_id = {$contract['id']} AND status_id <> 2;");
    // run the cancellation (but not the update)
    $this->runContractEngine($contract['id'], '+2 days');

    $contract_changed = $this->getContract($contract['id']);
    $this->assertEquals(
      'Cancelled',
      CRM_Contract_Utils::getMembershipStatusName($contract_changed['status_id']),
      'Membership should be cancelled'
    );

    // cancel contract again (while it's already cancelled)
    $this->modifyContract($contract['id'], 'cancel', '+1 days', [
      'membership_cancellation.membership_cancel_reason' => 'Unknown'
    ]);
    // resolve "Needs Review"
    CRM_Core_DAO::executeQuery("UPDATE civicrm_activity SET status_id = 1 WHERE source_record_id = {$contract['id']} AND status_id <> 2;");
    // run cancellation
    $this->callEngineFailure(
      $contract['id'],
      '+2 days',
      "Cannot cancel a membership when its status is 'Cancelled'"
    );
  }

  /**
   * Test that reviving active memberships is not possible
   */
  public function testAllowedStatusChangeRevive() {
    // create a new contract
    $contract = $this->createNewContract();

    // schedule revive
    $this->modifyContract($contract['id'], 'revive', '+1 days', [
      'membership_payment.membership_annual' => '123.00',
    ]);
    // run revive
    $this->callEngineFailure(
      $contract['id'],
      '+2 days',
      "Cannot revive a membership when its status is 'Current'"
    );
  }

  /**
   * Test campaign propagation behaviour
   */
  public function testCampaign() {
    $campaign_id = $this->callAPISuccess('Campaign', 'create', [
      'title' => 'sign',
    ])['id'];
    $upgrade_campaign_id = $this->callAPISuccess('Campaign', 'create', [
      'title' => 'upgrade',
    ])['id'];
    $revive_campaign_id = $this->callAPISuccess('Campaign', 'create', [
      'title' => 'revive',
    ])['id'];
    // create contract with campaign_id
    $contract = $this->createNewContract([
      'is_sepa'            => 1,
      'amount'             => '10.00',
      'frequency_unit'     => 'month',
      'frequency_interval' => '1',
      'campaign_id'        => $campaign_id,
    ]);

    $this->assertEquals(
      $campaign_id,
      $contract['campaign_id'],
      'campaign_id should be set for contract'
    );
    $this->assertContributionRecurCampaignMatches($contract['membership_payment.membership_recurring_contribution'], $campaign_id);
    $this->assertLatestContractActivityCampaignMatches($contract['id'], $campaign_id);

    // upgrade contract using a different campaign
    $this->modifyContract($contract['id'], 'update', 'now', [
      'membership_payment.membership_annual' => '240.00',
      'campaign_id'                          => $upgrade_campaign_id,
    ]);
    $this->runContractEngine($contract['id']);

    $contract = $this->getContract($contract['id']);
    $this->assertEquals(240.00, $contract['membership_payment.membership_annual'], 'contract amount should have changed');
    $this->assertEquals(
      $campaign_id,
      $contract['campaign_id'],
      'campaign_id for contract should be unchanged'
    );
    $this->assertContributionRecurCampaignMatches($contract['membership_payment.membership_recurring_contribution'], $upgrade_campaign_id);
    $this->assertLatestContractActivityCampaignMatches($contract['id'], $upgrade_campaign_id);

    // cancel contract
    $this->modifyContract($contract['id'], 'cancel', 'now', [
      'membership_cancellation.membership_cancel_reason' => 'Unknown'
    ]);
    $this->runContractEngine($contract['id']);

    // revive contract using another campaign
    $this->modifyContract($contract['id'], 'revive', 'now', [
      'membership_payment.membership_annual'             => '240.00',
      'campaign_id'                                      => $revive_campaign_id,
    ]);
    $this->runContractEngine($contract['id']);

    $contract = $this->getContract($contract['id']);
    $this->assertContributionRecurCampaignMatches($contract['membership_payment.membership_recurring_contribution'], $revive_campaign_id);
    $this->assertLatestContractActivityCampaignMatches($contract['id'], $revive_campaign_id);
  }

  /**
   * Test updates in which recurring contributions were changed outside of CE
   */
  public function testContributionRecurChanged() {
    $contract = $this->createNewContract([
      'is_sepa'            => 1,
      'amount'             => '10.00',
      'frequency_unit'     => 'month',
      'frequency_interval' => '1',
    ]);
    $this->callAPISuccess('ContributionRecur', 'create', [
      'id'     => $contract['membership_payment.membership_recurring_contribution'],
      'amount' => '20.00',
    ]);
    echo "modify!\n";
    // upgrade contract using the same recurring contribution
    $this->modifyContract($contract['id'], 'update', 'now', [
      'membership_payment.membership_recurring_contribution' => $contract['membership_payment.membership_recurring_contribution'],
    ]);
    echo "run!\n";
    $this->runContractEngine($contract['id']);
    $contract = $this->getContract($contract['id']);
    $this->assertEquals(240.00, $contract['membership_payment.membership_annual'], 'contract amount should have changed');
  }

  private function assertContributionRecurCampaignMatches($contributionRecurId, $campaignId) {
    $rcur_campaign_id = $this->callAPISuccess('ContributionRecur', 'getvalue', [
      'id'     => $contributionRecurId,
      'return' => 'campaign_id',
    ]);
    $this->assertEquals(
      $campaignId,
      $rcur_campaign_id,
      'campaign_id of recurring contribution should match'
    );
  }

  private function assertLatestContractActivityCampaignMatches($contractId, $campaignId) {
    $activity_campaign_id = $this->callAPISuccess('Activity', 'getvalue', [
      'source_record_id' => $contractId,
      'return'           => 'campaign_id',
      'options'          => ['limit' => 1, 'sort' => 'activity_date_time DESC'],
    ]);
    $this->assertEquals(
      $campaignId,
      $activity_campaign_id,
      'campaign_id of contract activity should match'
    );
  }

}
